<?php

namespace Mpdf\Ua\Import;

use Mpdf\Mpdf;
use Mpdf\Ua\StructureElement;
use Mpdf\Ua\StructureTree;
use Mpdf\Ua\StructType;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * Brings the structure tree of a tagged PDF page imported with FPDI into the document's own,
 * so the page's content stays tagged. FpdiTrait decides what happens to untagged and
 * encrypted sources, and passes on the warnings collected here.
 */
class FpdiStructMerger
{

	/**
	 * How the page ids FpdiTrait hands out for pages of an encrypted source begin. It lives
	 * here because a trait cannot declare a constant before PHP 8.2.
	 */
	const ENCRYPTED_PAGE_PLACEHOLDER_ID_PREFIX = 'mpdf-ua-encrypted-page:';

	/**
	 * How deep a structure tree is walked before the rest is dropped, so a malformed source
	 * cannot exhaust the call stack. Real documents nest a few dozen levels at most.
	 */
	const MAX_RECURSION_DEPTH = 1024;

	/**
	 * How many structure elements of one imported page are walked before the rest is dropped
	 */
	const NODE_BUDGET = 50000;

	/** @var Mpdf */
	private $mpdf;

	/** @var StructureTree */
	private $tree;

	/** @var string[] Warnings not yet passed on to UaState */
	private $untaggedWarnings = [];

	/**
	 * The top-level elements brought across for each imported page, set the first time it is placed
	 *
	 * @var array<string, StructureElement[]>
	 */
	private $mergedSubtrees = [];

	/**
	 * The /StructParents key of each imported page's form XObject
	 *
	 * @var array<string, int>
	 */
	private $pageStructParents = [];

	/**
	 * The pages each imported page was placed on, in order
	 *
	 * @var array<string, int[]>
	 */
	private $pageIdHostPages = [];

	/**
	 * The imported pages whose structure failed verifyAndPrepareMerge() under PDFUAauto
	 *
	 * @var array<string, true>
	 */
	private $verificationFailedPages = [];

	/**
	 * The elements on the path cloneElement() is walking. Only ancestors are held, so an
	 * element two parents share is not taken for a cycle.
	 *
	 * @var array<int, true>
	 */
	private $cloneVisited = [];

	/** @var int Elements cloneElement() has walked for the current page */
	private $cloneNodeCount = 0;

	/** @var bool Whether the current page's walk hit the depth or node limit, and stopped */
	private $cloneAborted = false;

	/**
	 * As $cloneVisited, for collectSanityCandidates()
	 *
	 * @var array<int, true>
	 */
	private $sanityVisited = [];

	/** @var int As $cloneNodeCount, for collectSanityCandidates() */
	private $sanityNodeCount = 0;

	/** @var bool As $cloneAborted, for collectSanityCandidates() */
	private $sanityAborted = false;

	/**
	 * The marked-content references cloneElement() made for each imported page, so their
	 * object numbers can be filled in without touching the document's own
	 *
	 * @var array<string, array<int, array{elem: StructureElement, idx: int}>> The element and the index in its MCIDs
	 */
	private $mergedMcrs = [];

	/**
	 * The element holding the OBJR of each link annotation FPDI imported with a page, by the
	 * annotation's object number in the source
	 *
	 * @var array<string, array<int, StructureElement>>
	 */
	private $importedLinkElements = [];

	/**
	 * The source object numbers of the links imported with the page being merged
	 *
	 * @var array<int, true>
	 */
	private $importedLinkObjects = [];

	/**
	 * It is built before UaState, so it collects warnings for FpdiTrait to pass on instead
	 * of holding UaState.
	 *
	 * @param Mpdf          $mpdf
	 * @param StructureTree $tree The document's structure tree
	 */
	public function __construct(Mpdf $mpdf, StructureTree $tree)
	{
		$this->mpdf = $mpdf;
		$this->tree = $tree;
	}

	/**
	 * Record a warning about an imported page
	 *
	 * @param string $message
	 */
	public function addUntaggedWarning($message)
	{
		$this->untaggedWarnings[] = $message;
	}

	/**
	 * @return string[] The warnings recorded since the last call
	 */
	public function getUntaggedWarnings()
	{
		$w = $this->untaggedWarnings;
		$this->untaggedWarnings = [];
		return $w;
	}

	/**
	 * Whether a source has a /StructTreeRoot. A source whose catalog cannot be read is
	 * taken as untagged.
	 *
	 * @param  string $readerId
	 * @return bool
	 */
	public function sourceIsTagged($readerId)
	{
		try {
			$reader  = $this->mpdf->getSourcePdfReader($readerId);
			$catalog = $reader->getParser()->getCatalog();
			$structTreeRoot = PdfDictionary::get($catalog, 'StructTreeRoot');
			return !($structTreeRoot instanceof PdfNull);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Whether a source's trailer has an /Encrypt entry. FPDI refuses such a source before a
	 * reader exists, so this only matters should it stop doing so.
	 *
	 * @param  string $readerId
	 * @return bool False where the trailer cannot be read
	 */
	public function sourceIsEncrypted($readerId)
	{
		try {
			$reader  = $this->mpdf->getSourcePdfReader($readerId);
			$trailer = $reader->getParser()->getCrossReference()->getTrailer();
			$encrypt = PdfDictionary::get($trailer, 'Encrypt');
			return !($encrypt instanceof PdfNull);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Copy the structure tree of an imported page's source under the element now open,
	 * along with its role map. Only the first placement of a page does anything; the others
	 * are added by addPerPageMcrKids().
	 *
	 * @param string $pageId
	 * @param int    $foXObjectObjNum The form XObject's object number, 0 until it is written
	 * @param int    $hostPageObjNum  The page's object number, 0 until it is written
	 */
	public function mergePageStructSubtree($pageId, $foXObjectObjNum, $hostPageObjNum)
	{
		if (isset($this->mergedSubtrees[$pageId])) {
			return;
		}

		$this->mergedSubtrees[$pageId]       = [];
		$this->mergedMcrs[$pageId]           = [];
		$this->importedLinkElements[$pageId] = [];
		$this->importedLinkObjects           = [];

		$this->cloneVisited    = [];
		$this->cloneNodeCount  = 0;
		$this->cloneAborted    = false;

		$importedPages = $this->mpdf->getImportedPages();
		if (!isset($importedPages[$pageId])) {
			return;
		}

		$readerId = $importedPages[$pageId]['readerId'];

		foreach ($importedPages[$pageId]['externalLinks'] as $link) {
			if (isset($link['sourceObjectNumber'])) {
				$this->importedLinkObjects[$link['sourceObjectNumber']] = true;
			}
		}

		try {
			$reader  = $this->mpdf->getSourcePdfReader($readerId);
			$parser  = $reader->getParser();
			$catalog = $parser->getCatalog();

			$structParents = $this->mpdf->getPdfUaNextStructParents();
			$this->pageStructParents[$pageId] = $structParents;

			$this->mergeRoleMap($catalog, $parser);

			$structTreeRootRef = PdfDictionary::get($catalog, 'StructTreeRoot');
			if ($structTreeRootRef instanceof PdfNull) {
				return;
			}

			$structTreeRoot = PdfType::resolve($structTreeRootRef, $parser);
			if (!($structTreeRoot instanceof PdfDictionary)) {
				return;
			}

			$kEntry = PdfDictionary::get($structTreeRoot, 'K');
			if ($kEntry instanceof PdfNull) {
				return;
			}

			$kEntry = PdfType::resolve($kEntry, $parser);
			$kids   = $this->normaliseKidsToArray($kEntry, $parser);

			$hostParent = $this->tree->getCurrent();

			foreach ($kids as $kid) {
				$cloned = $this->cloneElement($kid, $parser, $hostParent, $structParents, $foXObjectObjNum, $hostPageObjNum, $pageId);
				if ($cloned !== null) {
					$this->mergedSubtrees[$pageId][] = $cloned;
				}
				if ($this->cloneAborted) {
					break;
				}
			}

		} catch (\Exception $e) {
			// A source that cannot be parsed brings no structure across
		}
	}

	/**
	 * Give each link FPDI imported from a source page the object number of its annotation,
	 * so the annotation can be matched to the OBJR of the element that tags it. FPDI keeps
	 * a page's links to a URI in the order of its /Annots, without their /StructParent.
	 *
	 * @param  string $readerId
	 * @param  int    $pageNumber
	 * @param  array  $externalLinks The links as FPDI imported them
	 * @return array The links, with 'sourceObjectNumber' where the annotation was found
	 */
	public function identifyImportedLinks($readerId, $pageNumber, array $externalLinks)
	{
		if ($externalLinks === []) {
			return $externalLinks;
		}

		try {
			$reader = $this->mpdf->getSourcePdfReader($readerId);
			$parser = $reader->getParser();
			$page   = $reader->getPage($pageNumber);
			$annots = PdfType::resolve(PdfDictionary::get($page->getPageDictionary(), 'Annots'), $parser);
		} catch (\Exception $e) {
			return $externalLinks;
		}

		if (!($annots instanceof PdfArray)) {
			return $externalLinks;
		}

		$i = 0;
		foreach ($annots->value as $entry) {
			if (!isset($externalLinks[$i])) {
				break;
			}
			// Only an annotation that is an object of its own can be the target of an OBJR
			if (!($entry instanceof PdfIndirectObjectReference)) {
				continue;
			}
			try {
				$action = PdfType::resolve(PdfDictionary::get(PdfType::resolve($entry, $parser), 'A'), $parser);
				$uri    = $action instanceof PdfDictionary ? PdfType::resolve(PdfDictionary::get($action, 'URI'), $parser) : null;
			} catch (\Exception $e) {
				continue;
			}
			if ($uri instanceof PdfString) {
				$uri = PdfString::unescape($uri->value);
			} elseif ($uri instanceof PdfHexString) {
				$uri = hex2bin($uri->value);
			} else {
				continue;
			}
			if ($uri === $externalLinks[$i]['uri']) {
				$externalLinks[$i]['sourceObjectNumber'] = (int) $entry->value;
				$i++;
			}
		}

		return $externalLinks;
	}

	/**
	 * @param  string $pageId
	 * @param  int    $sourceObjectNumber The object number of a link annotation in the page's source
	 * @return StructureElement|null The element brought across that tags the annotation
	 */
	public function getImportedLinkElement($pageId, $sourceObjectNumber)
	{
		return isset($this->importedLinkElements[$pageId][$sourceObjectNumber])
			? $this->importedLinkElements[$pageId][$sourceObjectNumber]
			: null;
	}

	/**
	 * @param  string $pageId
	 * @return int The /StructParents key of the page's form XObject, or -1 where its structure was not brought across
	 */
	public function getFormXObjectStructParents($pageId)
	{
		if (isset($this->pageStructParents[$pageId])) {
			return $this->pageStructParents[$pageId];
		}
		return -1;
	}

	/**
	 * Record a page an imported page was placed on, for patchMergedSubtreeObjectNumbers()
	 *
	 * @param string $pageId
	 * @param int    $hostPage
	 */
	public function recordHostPage($pageId, $hostPage)
	{
		if (!isset($this->pageIdHostPages[$pageId])) {
			$this->pageIdHostPages[$pageId] = [];
		}
		$this->pageIdHostPages[$pageId][] = $hostPage;
	}

	/**
	 * Fill in the page and form XObject object numbers of the marked-content references
	 * brought across for an imported page, and add references for each further page it was
	 * placed on. The pages are written by now.
	 *
	 * @param string $pageId
	 * @param int    $foXObjectObjNum
	 */
	public function patchMergedSubtreeObjectNumbers($pageId, $foXObjectObjNum)
	{
		if (!isset($this->mergedMcrs[$pageId])) {
			return;
		}

		$hostPages = isset($this->pageIdHostPages[$pageId]) ? $this->pageIdHostPages[$pageId] : [];
		if (empty($hostPages)) {
			return;
		}

		$firstHostPage   = $hostPages[0];
		$firstHostObjNum = isset($this->mpdf->pageDim[$firstHostPage]['n'])
			? (int) $this->mpdf->pageDim[$firstHostPage]['n']
			: 0;

		foreach ($this->mergedMcrs[$pageId] as $slot) {
			$elem = $slot['elem'];
			$idx  = $slot['idx'];
			$mcids = $elem->getMcids();
			if (!isset($mcids[$idx])) {
				continue;
			}
			// Anything but zeros was already filled in by addPerPageMcrKids()
			if ($mcids[$idx]['pageRef'] === 0 && $mcids[$idx]['stm'] === 0) {
				$elem->patchMcr($idx, $firstHostObjNum, $foXObjectObjNum);
			}
		}

		for ($i = 1; $i < count($hostPages); $i++) {
			$reusePage   = $hostPages[$i];
			$reuseObjNum = isset($this->mpdf->pageDim[$reusePage]['n'])
				? (int) $this->mpdf->pageDim[$reusePage]['n']
				: 0;
			$this->addPerPageMcrKids($pageId, $reuseObjNum, $foXObjectObjNum);
		}
	}

	/**
	 * Give each element brought across for an imported page a further marked-content
	 * reference, to the same content on another page the page was placed on
	 *
	 * @param string $pageId
	 * @param int    $hostPageObjNum
	 * @param int    $foXObjectObjNum
	 */
	public function addPerPageMcrKids($pageId, $hostPageObjNum, $foXObjectObjNum)
	{
		if (!isset($this->mergedSubtrees[$pageId])) {
			return;
		}

		$structParents = isset($this->pageStructParents[$pageId]) ? $this->pageStructParents[$pageId] : -1;
		if ($structParents < 0) {
			return;
		}

		foreach ($this->mergedMcrs[$pageId] as $slot) {
			$elem  = $slot['elem'];
			$idx   = $slot['idx'];
			$mcids = $elem->getMcids();
			if (!isset($mcids[$idx])) {
				continue;
			}
			$elem->addMcid($structParents, $mcids[$idx]['mcid'], $hostPageObjNum, $foXObjectObjNum);
		}
	}

	/**
	 * Whether the first eight /Alt, /ActualText and /Lang strings of a source's structure
	 * read as text rather than ciphertext.
	 *
	 * FPDI refuses encrypted sources today, but one encrypted only in its strings
	 * (ISO 32000-1 §7.6.5) would otherwise carry ciphertext into the document's structure.
	 *
	 * @param  string $pageId
	 * @return bool False, under PDFUAauto, where the structure should not be brought across
	 * @throws \Mpdf\MpdfException Without PDFUAauto, where a string fails
	 */
	public function verifyAndPrepareMerge($pageId)
	{
		// A page placed again is not warned about again
		if (isset($this->verificationFailedPages[$pageId])) {
			return false;
		}

		$this->sanityVisited   = [];
		$this->sanityNodeCount = 0;
		$this->sanityAborted   = false;

		$importedPages = $this->mpdf->getImportedPages();
		if (!isset($importedPages[$pageId])) {
			return true;
		}

		$readerId = $importedPages[$pageId]['readerId'];

		try {
			$reader  = $this->mpdf->getSourcePdfReader($readerId);
			$parser  = $reader->getParser();
			$catalog = $parser->getCatalog();

			$structTreeRootRef = PdfDictionary::get($catalog, 'StructTreeRoot');
			if ($structTreeRootRef instanceof PdfNull) {
				return true;
			}
			$structTreeRoot = PdfType::resolve($structTreeRootRef, $parser);
			if (!($structTreeRoot instanceof PdfDictionary)) {
				return true;
			}

			$kEntry = PdfDictionary::get($structTreeRoot, 'K');
			if ($kEntry instanceof PdfNull) {
				return true;
			}

			$candidates = [];
			$this->collectSanityCandidates($kEntry, $parser, $candidates, 8);

			foreach ($candidates as $cand) {
				$decoded = $this->decodeImportedTextString($cand['value']);
				if ($decoded === null) {
					continue;
				}
				if (!$this->stringPassesSanityGauntlet($decoded)) {
					return $this->failVerification($pageId, $cand['attr']);
				}
			}

			return true;
		} catch (\Mpdf\MpdfException $e) {
			throw $e;
		} catch (\Exception $e) {
			// A source that cannot be parsed is left to mergePageStructSubtree()
			return true;
		}
	}

	/**
	 * Whether an imported page's structure failed verifyAndPrepareMerge() under PDFUAauto
	 *
	 * @param  string $pageId
	 * @return bool
	 */
	public function wasSanityCheckFailed($pageId)
	{
		return isset($this->verificationFailedPages[$pageId]);
	}

	/**
	 * Add a source's role map to the document's. A role already mapped keeps its mapping,
	 * and a standard type is never remapped.
	 *
	 * @param PdfDictionary                       $catalog The source's catalog
	 * @param \setasign\Fpdi\PdfParser\PdfParser $parser
	 */
	private function mergeRoleMap($catalog, $parser)
	{
		try {
			$structTreeRootRef = PdfDictionary::get($catalog, 'StructTreeRoot');
			if ($structTreeRootRef instanceof PdfNull) {
				return;
			}
			$structTreeRoot = PdfType::resolve($structTreeRootRef, $parser);
			if (!($structTreeRoot instanceof PdfDictionary)) {
				return;
			}
			$roleMapRef = PdfDictionary::get($structTreeRoot, 'RoleMap');
			if ($roleMapRef instanceof PdfNull) {
				return;
			}
			$roleMap = PdfType::resolve($roleMapRef, $parser);
			if (!($roleMap instanceof PdfDictionary)) {
				return;
			}
			foreach ($roleMap->value as $custom => $standardRef) {
				$standard = PdfType::resolve($standardRef, $parser);
				if (!($standard instanceof PdfName)) {
					continue;
				}
				$standardType = $standard->value;
				if (!StructType::isValid($custom) && StructType::isValid($standardType)) {
					$this->tree->addRoleMapping($custom, $standardType);
				}
			}
		} catch (\Exception $e) {
			// The source's own types still come across without it
		}
	}

	/**
	 * Collect the /Alt, /ActualText and /Lang strings of a source's structure, depth first,
	 * until there are $limit of them
	 *
	 * @param PdfType                            $node       A /K entry, resolved or not
	 * @param \setasign\Fpdi\PdfParser\PdfParser $parser
	 * @param array                              $candidates Each ['attr' => string, 'value' => PdfType]
	 * @param int                                $limit
	 * @param int                                $depth
	 */
	private function collectSanityCandidates($node, $parser, &$candidates, $limit, $depth = 0)
	{
		if (count($candidates) >= $limit) {
			return;
		}

		if ($this->sanityAborted) {
			return;
		}
		if ($depth > self::MAX_RECURSION_DEPTH) {
			$this->sanityAborted = true;
			$this->addUntaggedWarning(
				'Imported PDF struct sanity walk depth exceeded '
				. self::MAX_RECURSION_DEPTH
				. '; verify truncated.'
			);
			return;
		}
		$this->sanityNodeCount++;
		if ($this->sanityNodeCount > self::NODE_BUDGET) {
			$this->sanityAborted = true;
			$this->addUntaggedWarning(
				'Imported PDF struct sanity walk exceeded '
				. self::NODE_BUDGET
				. ' nodes; verify truncated.'
			);
			return;
		}

		// By object number, as FPDI resolves a reference to a new object each time
		$refKey = null;
		if ($node instanceof PdfIndirectObjectReference) {
			$refKey = 'ref:' . (int) $node->value;
			if (isset($this->sanityVisited[$refKey])) {
				return;
			}
			$this->sanityVisited[$refKey] = true;
		}

		try {
			try {
				$resolved = PdfType::resolve($node, $parser);
			} catch (\Exception $e) {
				return;
			}

			if ($resolved instanceof PdfArray) {
				foreach ($resolved->value as $entry) {
					if (count($candidates) >= $limit || $this->sanityAborted) {
						return;
					}
					$this->collectSanityCandidates($entry, $parser, $candidates, $limit, $depth + 1);
				}
				return;
			}

			if (!($resolved instanceof PdfDictionary)) {
				return;
			}

			$inlineKey = null;
			if ($refKey === null) {
				$inlineKey = 'obj:' . spl_object_hash($resolved);
				if (isset($this->sanityVisited[$inlineKey])) {
					return;
				}
				$this->sanityVisited[$inlineKey] = true;
			}

			try {
				$this->collectSanityCandidatesInner($resolved, $parser, $candidates, $limit, $depth);
			} finally {
				if ($inlineKey !== null) {
					unset($this->sanityVisited[$inlineKey]);
				}
			}
		} finally {
			if ($refKey !== null) {
				unset($this->sanityVisited[$refKey]);
			}
		}
	}

	/**
	 * Collect the strings of one structure element and walk its kids, for
	 * collectSanityCandidates()
	 *
	 * @param PdfDictionary                      $resolved
	 * @param \setasign\Fpdi\PdfParser\PdfParser $parser
	 * @param array                              $candidates
	 * @param int                                $limit
	 * @param int                                $depth
	 */
	private function collectSanityCandidatesInner($resolved, $parser, &$candidates, $limit, $depth)
	{

		// A marked-content or object reference carries no strings
		try {
			$typeEntry = PdfDictionary::get($resolved, 'Type');
			if (!($typeEntry instanceof PdfNull)) {
				$typeResolved = PdfType::resolve($typeEntry, $parser);
				if ($typeResolved instanceof PdfName
					&& ($typeResolved->value === 'MCR' || $typeResolved->value === 'OBJR')) {
					return;
				}
			}
		} catch (\Exception $e) {
		}

		foreach (['Alt', 'ActualText', 'Lang'] as $attrKey) {
			if (count($candidates) >= $limit) {
				return;
			}
			try {
				$attrRef = PdfDictionary::get($resolved, $attrKey);
				if ($attrRef instanceof PdfNull) {
					continue;
				}
				$attrVal = PdfType::resolve($attrRef, $parser);
				if ($attrVal instanceof PdfString || $attrVal instanceof PdfHexString) {
					$candidates[] = ['attr' => $attrKey, 'value' => $attrVal];
				}
			} catch (\Exception $e) {
				continue;
			}
		}

		try {
			$kRef = PdfDictionary::get($resolved, 'K');
			if (!($kRef instanceof PdfNull)) {
				$kResolved = PdfType::resolve($kRef, $parser);
				$kids      = $this->normaliseKidsToArray($kResolved, $parser);
				foreach ($kids as $kid) {
					if (count($candidates) >= $limit || $this->sanityAborted) {
						return;
					}
					$this->collectSanityCandidates($kid, $parser, $candidates, $limit, $depth + 1);
				}
			}
		} catch (\Exception $e) {
		}
	}

	/**
	 * Whether a decoded string reads as text rather than ciphertext. Ciphertext read as
	 * PDFDocEncoding is mostly control characters and U+FFFD; text in any script is not.
	 *
	 * @param  string $decoded UTF-8
	 * @return bool
	 */
	private function stringPassesSanityGauntlet($decoded)
	{
		// No alternative text runs to 4KB, even in a script of three-byte characters
		if (strlen($decoded) > 4096) {
			return false;
		}

		if ($decoded === '') {
			return true;
		}

		if (function_exists('mb_check_encoding') && !mb_check_encoding($decoded, 'UTF-8')) {
			return false;
		}

		// Control characters other than tab, LF and CR, DEL, and the U+FFFD
		// pdfDocEncodingToUtf8() gives an unmapped byte, count against the string
		$len           = strlen($decoded);
		$totalCp       = 0;
		$suspiciousCp  = 0;
		for ($i = 0; $i < $len;) {
			$b = ord($decoded[$i]);
			if ($b < 0x80) {
				$cp = $b;
				$i++;
			} elseif (($b & 0xE0) === 0xC0 && $i + 1 < $len) {
				$cp = (($b & 0x1F) << 6) | (ord($decoded[$i + 1]) & 0x3F);
				$i += 2;
			} elseif (($b & 0xF0) === 0xE0 && $i + 2 < $len) {
				$cp = (($b & 0x0F) << 12)
					| ((ord($decoded[$i + 1]) & 0x3F) << 6)
					| (ord($decoded[$i + 2]) & 0x3F);
				$i += 3;
			} elseif (($b & 0xF8) === 0xF0 && $i + 3 < $len) {
				$cp = (($b & 0x07) << 18)
					| ((ord($decoded[$i + 1]) & 0x3F) << 12)
					| ((ord($decoded[$i + 2]) & 0x3F) << 6)
					| (ord($decoded[$i + 3]) & 0x3F);
				$i += 4;
			} else {
				$cp = 0xFFFD;
				$i++;
			}

			$totalCp++;
			if ($cp === 0xFFFD) {
				$suspiciousCp++;
			} elseif ($cp < 0x20 && $cp !== 0x09 && $cp !== 0x0A && $cp !== 0x0D) {
				$suspiciousCp++;
			} elseif ($cp === 0x7F) {
				$suspiciousCp++;
			}
		}

		// Half is enough: a string that is half U+FFFD is not legible
		if ($totalCp > 0 && ($suspiciousCp / $totalCp) >= 0.5) {
			return false;
		}

		return true;
	}

	/**
	 * Throw for a page whose structure failed verifyAndPrepareMerge(), or under PDFUAauto
	 * mark it and warn
	 *
	 * @param  string $pageId
	 * @param  string $attrKey The attribute that failed
	 * @return bool False
	 * @throws \Mpdf\MpdfException
	 */
	private function failVerification($pageId, $attrKey)
	{
		$message = 'Imported PDF struct subtree contains an /' . $attrKey . ' value '
			. 'that failed the printable-codepoint sanity gauntlet (ISO 32000-1:2008 '
			. '§7.6.5 / §7.9.2.2). The source may carry still-encrypted text-string '
			. 'ciphertext that vendor/setasign/fpdi did not decrypt. The page is '
			. 'wrapped as an /Artifact instead. Matterhorn 01-007.';

		if (empty($this->mpdf->PDFUAauto)) {
			throw new \Mpdf\MpdfException($message);
		}

		$this->verificationFailedPages[$pageId] = true;
		$this->addUntaggedWarning($message);
		return false;
	}

	/**
	 * The kids a /K entry holds, which may be an array or a single kid
	 *
	 * @param  PdfType                            $k
	 * @param  \setasign\Fpdi\PdfParser\PdfParser $parser
	 * @return PdfType[]
	 */
	private function normaliseKidsToArray($k, $parser)
	{
		try {
			$resolved = PdfType::resolve($k, $parser);
		} catch (\Exception $e) {
			return [];
		}

		if ($resolved instanceof PdfArray) {
			return $resolved->value;
		}

		if ($resolved instanceof PdfDictionary) {
			return [$resolved];
		}

		if ($resolved instanceof PdfNumeric) {
			// An element with one marked-content sequence usually writes its MCID as /K itself
			return [$resolved];
		}

		return [];
	}

	/**
	 * Copy a source structure element and its kids under $hostParent, its marked content
	 * referred to in the form XObject. A type neither standard nor role-mapped becomes Div.
	 *
	 * Walking stops, with a warning, at a cycle or past MAX_RECURSION_DEPTH or NODE_BUDGET.
	 *
	 * @param  PdfType                            $sourceElem      Resolved or not
	 * @param  \setasign\Fpdi\PdfParser\PdfParser $parser
	 * @param  StructureElement                   $hostParent
	 * @param  int                                $structParents   The form XObject's /StructParents key
	 * @param  int                                $foXObjectObjNum
	 * @param  int                                $hostPageObjNum
	 * @param  string                             $pageId          Where the references made are recorded
	 * @param  int                                $depth
	 * @return StructureElement|null Null where the source is not a structure element
	 */
	private function cloneElement($sourceElem, $parser, $hostParent, $structParents, $foXObjectObjNum, $hostPageObjNum, $pageId = '', $depth = 0)
	{
		if ($this->cloneAborted) {
			return null;
		}
		if ($depth > self::MAX_RECURSION_DEPTH) {
			$this->cloneAborted = true;
			$this->addUntaggedWarning(
				'Imported PDF struct subtree depth exceeded '
				. self::MAX_RECURSION_DEPTH
				. '; merge truncated to prevent stack exhaustion.'
			);
			return null;
		}
		$this->cloneNodeCount++;
		if ($this->cloneNodeCount > self::NODE_BUDGET) {
			$this->cloneAborted = true;
			$this->addUntaggedWarning(
				'Imported PDF struct subtree exceeded '
				. self::NODE_BUDGET
				. ' nodes; merge truncated.'
			);
			return null;
		}

		// By object number, as FPDI resolves a reference to a new object each time
		$visitedKey = null;
		if ($sourceElem instanceof PdfIndirectObjectReference) {
			$visitedKey = 'ref:' . (int) $sourceElem->value;
			if (isset($this->cloneVisited[$visitedKey])) {
				$this->addUntaggedWarning(
					'Cycle detected in imported PDF struct subtree at object '
					. (int) $sourceElem->value . '; subtree truncated.'
				);
				return null;
			}
			$this->cloneVisited[$visitedKey] = true;
		}

		try {
			try {
				$resolved = PdfType::resolve($sourceElem, $parser);
			} catch (\Exception $e) {
				return null;
			}

			if (!($resolved instanceof PdfDictionary)) {
				return null;
			}

			// A direct dictionary can only form a cycle in a crafted file
			$inlineKey = null;
			if ($visitedKey === null) {
				$inlineKey = 'obj:' . spl_object_hash($resolved);
				if (isset($this->cloneVisited[$inlineKey])) {
					$this->addUntaggedWarning(
						'Cycle detected in imported PDF struct subtree; subtree truncated.'
					);
					return null;
				}
				$this->cloneVisited[$inlineKey] = true;
			}

			try {
				return $this->cloneElementInner($resolved, $parser, $hostParent, $structParents, $foXObjectObjNum, $hostPageObjNum, $pageId, $depth);
			} finally {
				if ($inlineKey !== null) {
					unset($this->cloneVisited[$inlineKey]);
				}
			}
		} finally {
			if ($visitedKey !== null) {
				unset($this->cloneVisited[$visitedKey]);
			}
		}
	}

	/**
	 * Copy one resolved structure element and its kids, for cloneElement()
	 *
	 * @param  PdfDictionary                      $resolved
	 * @param  \setasign\Fpdi\PdfParser\PdfParser $parser
	 * @param  StructureElement                   $hostParent
	 * @param  int                                $structParents
	 * @param  int                                $foXObjectObjNum
	 * @param  int                                $hostPageObjNum
	 * @param  string                             $pageId
	 * @param  int                                $depth
	 * @return StructureElement|null
	 */
	private function cloneElementInner($resolved, $parser, $hostParent, $structParents, $foXObjectObjNum, $hostPageObjNum, $pageId, $depth)
	{

		// A marked-content or object reference is not an element
		$typeEntry = PdfDictionary::get($resolved, 'Type');
		if (!($typeEntry instanceof PdfNull)) {
			try {
				$typeResolved = PdfType::resolve($typeEntry, $parser);
				if ($typeResolved instanceof PdfName) {
					$typeVal = $typeResolved->value;
					if ($typeVal === 'MCR' || $typeVal === 'OBJR') {
						return null;
					}
				}
			} catch (\Exception $e) {
			}
		}

		$sEntry = PdfDictionary::get($resolved, 'S');
		if ($sEntry instanceof PdfNull) {
			return null;
		}

		$hostType = 'Div';
		try {
			$sResolved = PdfType::resolve($sEntry, $parser);
			if ($sResolved instanceof PdfName) {
				$typeName = $sResolved->value;
				if (StructType::isValid($typeName)) {
					$hostType = $typeName;
				} elseif (isset($this->tree->getRoleMappings()[$typeName])) {
					$hostType = $this->tree->getRoleMappings()[$typeName];
				}
			}
		} catch (\Exception $e) {
		}

		$hostElem = new StructureElement($hostType);
		$hostParent->addChild($hostElem);

		// StructureWriter encodes these from UTF-8, so they are decoded rather than copied
		foreach (['Alt', 'ActualText', 'Lang'] as $attrKey) {
			try {
				$attrRef = PdfDictionary::get($resolved, $attrKey);
				if (!($attrRef instanceof PdfNull)) {
					$attrVal = PdfType::resolve($attrRef, $parser);
					$decoded = $this->decodeImportedTextString($attrVal);
					if ($decoded !== null) {
						$hostElem->setAttribute($attrKey, $decoded);
					} elseif ($this->mpdf->PDFUA && empty($this->mpdf->PDFUAauto)) {
						throw new \Mpdf\MpdfException(
							'Imported PDF struct element /S /' . $hostType
							. ' carries an undecodable /' . $attrKey . ' attribute '
							. '(ISO 32000-1:2008 §7.9.2.2). Decrypt or sanitise the '
							. 'source upstream, or enable PDFUAauto to skip the failed '
							. 'attribute (Matterhorn 01-007).'
						);
					} else {
						$this->addUntaggedWarning(
							'Imported PDF struct element /S /' . $hostType
							. ' carries an undecodable /' . $attrKey . ' attribute '
							. '(ISO 32000-1:2008 §7.9.2.2); attribute dropped under '
							. 'PDFUAauto. Matterhorn 13-004 / 01-007.'
						);
					}
				}
			} catch (\Mpdf\MpdfException $e) {
				throw $e;
			} catch (\Exception $e) {
			}
		}

		$this->copyStructureAttributes($resolved, $parser, $hostElem);

		$tagsImportedLink = false;
		$kRef = PdfDictionary::get($resolved, 'K');
		if (!($kRef instanceof PdfNull)) {
			try {
				$kResolved = PdfType::resolve($kRef, $parser);
				$kids = $this->normaliseKidsToArray($kResolved, $parser);

				foreach ($kids as $kid) {
					if ($this->cloneAborted) {
						break;
					}
					try {
						$kidResolved = PdfType::resolve($kid, $parser);
					} catch (\Exception $e) {
						continue;
					}

					if ($kidResolved instanceof PdfNumeric) {
						$mcid    = (int) $kidResolved->value;
						$mcrIdx  = count($hostElem->getMcids());
						$hostElem->addMcid($structParents, $mcid, $hostPageObjNum, $foXObjectObjNum);
						if ($pageId !== '') {
							$this->mergedMcrs[$pageId][] = ['elem' => $hostElem, 'idx' => $mcrIdx];
						}
						$this->registerMcrInParentTree($structParents, $mcid, $hostElem);
					} elseif ($kidResolved instanceof PdfDictionary) {
						$kidTypeRef = PdfDictionary::get($kidResolved, 'Type');
						$kidType    = '';
						try {
							$kidTypeResolved = PdfType::resolve($kidTypeRef, $parser);
							if ($kidTypeResolved instanceof PdfName) {
								$kidType = $kidTypeResolved->value;
							}
						} catch (\Exception $e) {
						}

						if ($kidType === 'MCR') {
							$mcidRef = PdfDictionary::get($kidResolved, 'MCID');
							try {
								$mcidResolved = PdfType::resolve($mcidRef, $parser);
								if ($mcidResolved instanceof PdfNumeric) {
									$mcid   = (int) $mcidResolved->value;
									$mcrIdx = count($hostElem->getMcids());
									$hostElem->addMcid($structParents, $mcid, $hostPageObjNum, $foXObjectObjNum);
									if ($pageId !== '') {
										$this->mergedMcrs[$pageId][] = ['elem' => $hostElem, 'idx' => $mcrIdx];
									}
									$this->registerMcrInParentTree($structParents, $mcid, $hostElem);
								}
							} catch (\Exception $e) {
							}
						} elseif ($kidType === 'OBJR') {
							// The annotation of an imported link is tagged by the element that tagged it in the source
							$obj = PdfDictionary::get($kidResolved, 'Obj');
							if ($pageId !== '' && $obj instanceof PdfIndirectObjectReference && isset($this->importedLinkObjects[(int) $obj->value])) {
								$this->importedLinkElements[$pageId][(int) $obj->value] = $hostElem;
								$tagsImportedLink = true;
							}
						} else {
							$this->cloneElement($kid, $parser, $hostElem, $structParents, $foXObjectObjNum, $hostPageObjNum, $pageId, $depth + 1);
						}
					}
				}
			} catch (\Exception $e) {
				// The element is kept without its kids
			}
		}

		// A widget is never imported and FPDI imports only links to a URI, so a Form or Link element
		// whose annotation was not brought across and which holds nothing else is dropped
		if (($hostType === 'Form' || $hostType === 'Link')
			&& $hostElem->getMcids() === []
			&& $hostElem->getChildren() === []
			&& !$tagsImportedLink
		) {
			$hostParent->removeChild($hostElem);
			return null;
		}

		return $hostElem;
	}

	/**
	 * Copy the attributes of a source element that name nothing else in its document: a header
	 * cell's /Scope, a cell's spans, a list's numbering and a block's placement. Without them its
	 * table no longer reads as one (Matterhorn 15-003, 15-005). /Headers and /ID are left out, as
	 * they would have to be renamed to stay unique here.
	 *
	 * @param PdfDictionary                      $resolved
	 * @param \setasign\Fpdi\PdfParser\PdfParser $parser
	 * @param StructureElement                   $hostElem
	 */
	private function copyStructureAttributes(PdfDictionary $resolved, $parser, StructureElement $hostElem)
	{
		$kinds = ['Scope' => 'name', 'ListNumbering' => 'name', 'Placement' => 'name', 'ColSpan' => 'span', 'RowSpan' => 'span'];

		// An array of attribute objects may carry revision numbers between them, which are skipped
		foreach ($this->normaliseKidsToArray(PdfDictionary::get($resolved, 'A'), $parser) as $object) {
			try {
				$object = PdfType::resolve($object, $parser);
				if (!($object instanceof PdfDictionary)) {
					continue;
				}
				foreach ($kinds as $key => $kind) {
					$value = PdfType::resolve(PdfDictionary::get($object, $key), $parser);
					// A name is written back as it is, so only a plain one is taken
					if ($kind === 'name' && $value instanceof PdfName && preg_match('/\A[A-Za-z]+\z/', $value->value)) {
						$hostElem->setAttribute($key, $value->value);
					} elseif ($kind === 'span' && $value instanceof PdfNumeric && (int) $value->value > 1) {
						$hostElem->setAttribute($key, (int) $value->value);
					}
				}
			} catch (\Exception $e) {
				continue;
			}
		}
	}

	/**
	 * Enter a source MCID in the parent tree as it is. addContentForElement() would allocate
	 * a new one, which the form XObject's content does not use.
	 *
	 * @param int              $structParents
	 * @param int              $mcid
	 * @param StructureElement $elem
	 */
	private function registerMcrInParentTree($structParents, $mcid, StructureElement $elem)
	{
		$this->tree->registerImportedMcr($structParents, $mcid, $elem);
	}

	/**
	 * A text string of the source in UTF-8. It is UTF-16 or UTF-8 behind a byte order mark,
	 * or PDFDocEncoding without one (ISO 32000-1 §7.9.2.2).
	 *
	 * @param  PdfType|mixed $node Resolved
	 * @return string|null Null for anything but a string, or one that cannot be decoded
	 */
	private function decodeImportedTextString($node)
	{
		if ($node instanceof PdfHexString) {
			$hex = preg_replace('/\s+/', '', (string) $node->value);
			if ($hex === '') {
				return '';
			}
			// A missing last digit is taken as 0 (ISO 32000-1 §7.3.4.3)
			if (strlen($hex) % 2 === 1) {
				$hex .= '0';
			}
			// pack() warns on anything but hex digits, or from PHP 8 throws
			if (!preg_match('/\A[0-9A-Fa-f]+\z/', $hex)) {
				return null;
			}
			$raw = pack('H*', $hex);
			if ($raw === false || $raw === '') {
				return null;
			}
		} elseif ($node instanceof PdfString) {
			$raw = PdfString::unescape((string) $node->value);
		} else {
			return null;
		}

		if (strlen($raw) >= 2 && substr($raw, 0, 2) === "\xFE\xFF") {
			$utf16 = substr($raw, 2);
			$utf8  = @mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
			return $utf8 === false ? null : $utf8;
		}
		if (strlen($raw) >= 2 && substr($raw, 0, 2) === "\xFF\xFE") {
			// Not allowed, but written by some older producers
			$utf16 = substr($raw, 2);
			$utf8  = @mb_convert_encoding($utf16, 'UTF-8', 'UTF-16LE');
			return $utf8 === false ? null : $utf8;
		}
		if (strlen($raw) >= 3 && substr($raw, 0, 3) === "\xEF\xBB\xBF") {
			return substr($raw, 3);
		}

		return $this->pdfDocEncodingToUtf8($raw);
	}

	/**
	 * A PDFDocEncoding string in UTF-8 (ISO 32000-1 Annex D), U+FFFD standing for a byte the
	 * encoding leaves undefined
	 *
	 * @param  string $bytes
	 * @return string
	 */
	private function pdfDocEncodingToUtf8($bytes)
	{
		static $table = null;
		if ($table === null) {
			// Null where the byte is undefined
			$t = array_fill(0, 256, null);

			$t[0x09] = 0x0009; // TAB
			$t[0x0A] = 0x000A; // LF
			$t[0x0C] = 0x000C; // FF
			$t[0x0D] = 0x000D; // CR
			$t[0x08] = 0x0008; // BS
			$t[0x18] = 0x02D8; // BREVE
			$t[0x19] = 0x02C7; // CARON
			$t[0x1A] = 0x02C6; // CIRCUMFLEX
			$t[0x1B] = 0x02D9; // DOT ABOVE
			$t[0x1C] = 0x02DD; // DOUBLE ACUTE
			$t[0x1D] = 0x02DB; // OGONEK
			$t[0x1E] = 0x02DA; // RING ABOVE
			$t[0x1F] = 0x02DC; // SMALL TILDE

			for ($i = 0x20; $i <= 0x7E; $i++) {
				$t[$i] = $i;
			}

			$t[0x80] = 0x2022; // BULLET
			$t[0x81] = 0x2020; // DAGGER
			$t[0x82] = 0x2021; // DOUBLE DAGGER
			$t[0x83] = 0x2026; // HORIZONTAL ELLIPSIS
			$t[0x84] = 0x2014; // EM DASH
			$t[0x85] = 0x2013; // EN DASH
			$t[0x86] = 0x0192; // FLORIN
			$t[0x87] = 0x2044; // FRACTION SLASH
			$t[0x88] = 0x2039; // SINGLE LEFT-POINTING ANGLE QUOTE
			$t[0x89] = 0x203A; // SINGLE RIGHT-POINTING ANGLE QUOTE
			$t[0x8A] = 0x2212; // MINUS SIGN
			$t[0x8B] = 0x2030; // PER MILLE
			$t[0x8C] = 0x201E; // DOUBLE LOW-9 QUOTE
			$t[0x8D] = 0x201C; // LEFT DOUBLE QUOTE
			$t[0x8E] = 0x201D; // RIGHT DOUBLE QUOTE
			$t[0x8F] = 0x2018; // LEFT SINGLE QUOTE
			$t[0x90] = 0x2019; // RIGHT SINGLE QUOTE
			$t[0x91] = 0x201A; // SINGLE LOW-9 QUOTE
			$t[0x92] = 0x2122; // TRADEMARK
			$t[0x93] = 0xFB01; // LATIN SMALL LIGATURE FI
			$t[0x94] = 0xFB02; // LATIN SMALL LIGATURE FL
			$t[0x95] = 0x0141; // LATIN CAPITAL LETTER L WITH STROKE
			$t[0x96] = 0x0152; // LATIN CAPITAL LIGATURE OE
			$t[0x97] = 0x0160; // LATIN CAPITAL LETTER S WITH CARON
			$t[0x98] = 0x0178; // LATIN CAPITAL LETTER Y WITH DIAERESIS
			$t[0x99] = 0x017D; // LATIN CAPITAL LETTER Z WITH CARON
			$t[0x9A] = 0x0131; // LATIN SMALL LETTER DOTLESS I
			$t[0x9B] = 0x0142; // LATIN SMALL LETTER L WITH STROKE
			$t[0x9C] = 0x0153; // LATIN SMALL LIGATURE OE
			$t[0x9D] = 0x0161; // LATIN SMALL LETTER S WITH CARON
			$t[0x9E] = 0x017E; // LATIN SMALL LETTER Z WITH CARON
			$t[0xA0] = 0x20AC; // EURO SIGN

			for ($i = 0xA1; $i <= 0xFF; $i++) {
				$t[$i] = $i;
			}
			$t[0xAD] = null;

			$table = $t;
		}

		$out = '';
		$len = strlen($bytes);
		for ($i = 0; $i < $len; $i++) {
			$cp = $table[ord($bytes[$i])];
			if ($cp === null) {
				$out .= "\xEF\xBF\xBD"; // U+FFFD REPLACEMENT CHARACTER
				continue;
			}
			if ($cp < 0x80) {
				$out .= chr($cp);
			} elseif ($cp < 0x800) {
				$out .= chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
			} elseif ($cp < 0x10000) {
				$out .= chr(0xE0 | ($cp >> 12))
					. chr(0x80 | (($cp >> 6) & 0x3F))
					. chr(0x80 | ($cp & 0x3F));
			} else {
				$out .= chr(0xF0 | ($cp >> 18))
					. chr(0x80 | (($cp >> 12) & 0x3F))
					. chr(0x80 | (($cp >> 6) & 0x3F))
					. chr(0x80 | ($cp & 0x3F));
			}
		}
		return $out;
	}
}
