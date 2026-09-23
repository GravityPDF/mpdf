<?php

namespace Mpdf\Ua;

use Mpdf\Mpdf;
use Mpdf\Writer\BaseWriter;

/**
 * Writes a StructureTree out as the document's StructTreeRoot, with its elements, ParentTree and
 * RoleMap
 */
class StructureWriter
{

	/** @var Mpdf */
	private $mpdf;

	/** @var BaseWriter */
	private $writer;

	/** @var StructureTree */
	private $tree;

	/**
	 * Object number of the StructTreeRoot, reserved first as the Document element names it as its /P
	 *
	 * @var int
	 */
	private $rootObjNum = 0;

	/**
	 * Page object number by /StructParents, built once per tree rather than per element
	 *
	 * @var array<int,int>|null
	 */
	private $pageRefMap = null;

	/**
	 * What firstContentKey() found for each element, by spl_object_hash(), while the tree is written
	 *
	 * @var array<string, int[]|null>
	 */
	private $firstContentKeys = [];

	/**
	 * @param Mpdf          $mpdf
	 * @param BaseWriter    $writer
	 * @param StructureTree $tree
	 */
	public function __construct(Mpdf $mpdf, BaseWriter $writer, StructureTree $tree)
	{
		$this->mpdf   = $mpdf;
		$this->writer = $writer;
		$this->tree   = $tree;
	}

	/**
	 * Write the structure tree, once the annotations are written so a Link can see its own.
	 *
	 * Every element's object number is reserved before any is written, as parents and children
	 * name each other.
	 *
	 * @param int $parentTreeNextKey One more than the highest /StructParents handed out
	 *
	 * @return int The object number of the StructTreeRoot
	 */
	public function writeStructTree($parentTreeNextKey = 0)
	{
		// A Link with neither content nor an annotation is invalid. Without PDFUAauto the author is
		// told which one; with it the Link is dropped, having nothing on the page to lose.
		if (empty($this->mpdf->PDFUAauto)) {
			$href = $this->tree->findFirstEmptyLinkHref();
			if ($href !== null) {
				throw new \Mpdf\MpdfException(
					'PDF/UA-1: <a href="' . $href . '"> wraps no accessible content '
					. '(empty body or only decorative children) and produces a Link '
					. 'struct element with no /K kids — Matterhorn 02-003. Provide '
					. 'visible link text, a non-empty alt on the inner <img>, or an '
					. 'aria-label on the anchor. Enable PDFUAauto to drop the empty '
					. 'Link silently and synthesise an /Alt fallback.'
				);
			}
		}
		$this->tree->pruneEmptyLinks();

		$this->writer->object(false, true);
		$this->rootObjNum = $this->mpdf->n;

		$this->reserveObjectNumbers($this->tree->getRoot());

		$this->pageRefMap = $this->buildPageRefMap();

		$this->firstContentKeys = [];
		$this->writeElement($this->tree->getRoot());
		$this->firstContentKeys = [];

		$parentTreeObjNum = $this->writeParentTree();

		// veraPDF rejects an empty /RoleMap in some profiles
		$roleMappings   = $this->tree->getRoleMappings();
		$roleMapObjNum  = 0;
		if (!empty($roleMappings)) {
			$roleMapObjNum = $this->writeRoleMap($roleMappings);
		}

		$this->writer->object($this->rootObjNum, false);
		$rootObjNum = $this->rootObjNum;

		$rootElem = $this->tree->getRoot();

		$this->writer->write('<</Type /StructTreeRoot');
		$this->writer->write('/K [' . $rootElem->getObjNum() . ' 0 R]');
		$this->writer->write('/ParentTree ' . $parentTreeObjNum . ' 0 R');
		$this->writer->write('/ParentTreeNextKey ' . $parentTreeNextKey);
		if ($roleMapObjNum > 0) {
			$this->writer->write('/RoleMap ' . $roleMapObjNum . ' 0 R');
		}
		$this->writer->write('>>');
		$this->writer->write('endobj');

		return $rootObjNum;
	}

	/**
	 * Reserve an object number for $elem and each of its descendants, writing nothing
	 *
	 * @param StructureElement $elem
	 */
	private function reserveObjectNumbers(StructureElement $elem)
	{
		foreach ($elem->getChildren() as $child) {
			$this->reserveObjectNumbers($child);
		}
		$this->writer->object(false, true);
		$elem->setObjNum($this->mpdf->n);
	}

	/**
	 * Write the StructElem of $elem, after those of its descendants, in the object reserved for it
	 *
	 * @param StructureElement $elem
	 */
	private function writeElement(StructureElement $elem)
	{
		foreach ($elem->getChildren() as $child) {
			$this->writeElement($child);
		}

		$this->writer->object($elem->getObjNum(), false);

		$attrs  = $elem->getAttributes();
		$mcids  = $elem->getMcids();
		$objrefs = $elem->getObjrefs();

		$this->writer->write('<</Type /StructElem');
		$this->writer->write('/S /' . $elem->getType());

		$parent = $elem->getParent();
		if ($parent !== null) {
			$this->writer->write('/P ' . $parent->getObjNum() . ' 0 R');
		} else {
			// The Document element's parent is the StructTreeRoot; without a /P validators cannot
			// walk down from it and call all content untagged
			$this->writer->write('/P ' . $this->rootObjNum . ' 0 R');
		}

		// A byte string rather than text, as the strings in a cell's /Headers are matched against it byte
		// for byte. The ID has been through StructureElement::sanitiseIdForPdf(); that is asserted by its
		// characters, as sanitising twice escapes '#' again.
		if ($elem->getId() !== null) {
			$id = $elem->getId();
			assert(
				is_string($id)
					&& strlen($id) <= 127
					&& preg_match('/\A[a-z0-9_.\-#]*\z/', $id) === 1,
				'StructureWriter: stored /ID is outside sanitiseIdForPdf() codomain: ' . var_export($id, true)
			);
			$this->writer->write('/ID ' . $this->writer->string($id));
		}

		// These belong on the element itself, not in its attribute objects
		$directKeys = ['Alt', 'ActualText', 'Lang', 'E', 'T'];
		foreach ($directKeys as $key) {
			if (isset($attrs[$key])) {
				$this->writer->write('/' . $key . ' ' . $this->writer->utf16BigEndianTextString($attrs[$key]));
			}
		}

		$tableKeys  = ['Scope', 'ColSpan', 'RowSpan', 'Headers', 'Summary'];
		$listKeys   = ['ListNumbering'];
		$layoutKeys = ['Placement', 'BBox', 'WritingMode'];

		$tableAttrs  = [];
		$listAttrs   = [];
		$layoutAttrs = [];

		foreach ($tableKeys as $key) {
			if (isset($attrs[$key])) {
				$tableAttrs[$key] = $attrs[$key];
			}
		}
		foreach ($listKeys as $key) {
			if (isset($attrs[$key])) {
				$listAttrs[$key] = $attrs[$key];
			}
		}
		foreach ($layoutKeys as $key) {
			if (isset($attrs[$key])) {
				$layoutAttrs[$key] = $attrs[$key];
			}
		}

		$attrObjects = [];
		if (!empty($tableAttrs)) {
			$attrObjects[] = $this->buildAttrObject('/Table', $tableAttrs);
		}
		if (!empty($listAttrs)) {
			$attrObjects[] = $this->buildAttrObject('/List', $listAttrs);
		}
		if (!empty($layoutAttrs)) {
			$attrObjects[] = $this->buildAttrObject('/Layout', $layoutAttrs);
		}

		if (!empty($attrObjects)) {
			if (count($attrObjects) === 1) {
				$this->writer->write('/A ' . $attrObjects[0]);
			} else {
				$this->writer->write('/A [' . implode(' ', $attrObjects) . ']');
			}
		}

		// The elements named by aria-owns and aria-controls, each once
		$relationships = $elem->getRelationships();
		if (!empty($relationships)) {
			$refParts = [];
			$seen     = [];
			foreach ($relationships as $rel) {
				$refObj = $rel['target']->getObjNum();
				if ($refObj > 0 && !isset($seen[$refObj])) {
					$seen[$refObj] = true;
					$refParts[]    = $refObj . ' 0 R';
				}
			}
			if (!empty($refParts)) {
				$this->writer->write('/Ref [' . implode(' ', $refParts) . ']');
			}
		}

		$kParts = [];

		$pageRefs = $this->pageRefMap;
		$singleSimpleMcid = (
			count($mcids) === 1
			&& empty($objrefs)
			&& empty($elem->getChildren())
			&& isset($mcids[0]['stm'])
			&& $mcids[0]['stm'] === 0
		);
		if ($singleSimpleMcid) {
			// A lone MCID on a page is written bare, with the page as the element's /Pg. An imported
			// page's key belongs to its form XObject, not a page, so it carries its own pageRef.
			$singlePageObjNum = (isset($mcids[0]['pageRef']) && $mcids[0]['pageRef'] > 0)
				? $mcids[0]['pageRef']
				: (isset($pageRefs[$mcids[0]['page']]) ? $pageRefs[$mcids[0]['page']] : 0);
			if ($singlePageObjNum > 0) {
				$this->writer->write('/Pg ' . $singlePageObjNum . ' 0 R');
			}
			$kParts[] = (string) $mcids[0]['mcid'];
		} else {
			foreach ($this->kidsInReadingOrder($elem) as $kid) {
				if ($kid instanceof StructureElement) {
					$kParts[] = $kid->getObjNum() . ' 0 R';
					continue;
				}
				$mcr = $kid;
				$pageObjNum = (isset($mcr['pageRef']) && $mcr['pageRef'] > 0)
					? $mcr['pageRef']
					: (isset($pageRefs[$mcr['page']]) ? $pageRefs[$mcr['page']] : 0);
				$stm = isset($mcr['stm']) ? (int) $mcr['stm'] : 0;
				if ($pageObjNum > 0) {
					if ($stm > 0) {
						// Content inside a form XObject names it as the /Stm
						$kParts[] = '<</Type /MCR /Pg ' . $pageObjNum . ' 0 R /Stm ' . $stm . ' 0 R /MCID ' . $mcr['mcid'] . '>>';
					} else {
						$kParts[] = '<</Type /MCR /Pg ' . $pageObjNum . ' 0 R /MCID ' . $mcr['mcid'] . '>>';
					}
				} else {
					$kParts[] = (string) $mcr['mcid'];
				}
			}
		}

		foreach ($objrefs as $objref) {
			$kParts[] = '<</Type /OBJR /Obj ' . $objref['obj'] . ' 0 R>>';
		}

		if (!empty($kParts)) {
			if (count($kParts) === 1) {
				$this->writer->write('/K ' . $kParts[0]);
			} else {
				$this->writer->write('/K [' . implode(' ', $kParts) . ']');
			}
		}

		$this->writer->write('>>');
		$this->writer->write('endobj');
	}

	/**
	 * The children of an element and its own marked content, in the order they are read.
	 *
	 * An inline element such as a Link is opened when its tag is read, before the text of the block
	 * around it is drawn, so the order elements were added in does not tell where the block's own
	 * text falls between them. Content drawn on a page is numbered as it is drawn, so a child is put
	 * after the block's content drawn before its own; a child without any, such as a Form, after
	 * the content the block had when the child was added. Children keep their order, but for a
	 * table's footer, which HTML lets come before the body, and content of an imported page keeps
	 * the order its source gave it.
	 *
	 * @param StructureElement $elem
	 *
	 * @return array<int, StructureElement|array{page:int, mcid:int, pageRef:int, stm:int}>
	 */
	private function kidsInReadingOrder(StructureElement $elem)
	{
		$children = $elem->getChildren();
		if ($elem->getType() === 'Table') {
			$children = $this->footAfterBody($children);
		}

		$mcids = $elem->getMcids();
		$count = count($mcids);
		$kids = [];
		$next = 0;
		foreach ($children as $child) {
			$key = $this->firstContentKey($child);
			while ($next < $count) {
				$mcr = $mcids[$next];
				$before = ($key === null || !empty($mcr['stm']))
					? $next < $child->getParentContentBefore()
					: [$mcr['page'], $mcr['mcid']] < $key;
				if (!$before) {
					break;
				}
				$kids[] = $mcr;
				$next++;
			}
			$kids[] = $child;
		}

		return array_merge($kids, array_slice($mcids, $next));
	}

	/**
	 * A table's row groups with its TFoot after the last TBody, where the footer is drawn
	 *
	 * @param StructureElement[] $children
	 *
	 * @return StructureElement[]
	 */
	private function footAfterBody(array $children)
	{
		$feet = [];
		$others = [];
		$lastBody = -1;
		foreach ($children as $child) {
			if ($child->getType() === 'TFoot') {
				$feet[] = $child;
				continue;
			}
			if ($child->getType() === 'TBody') {
				$lastBody = count($others);
			}
			$others[] = $child;
		}
		if ($feet === [] || $lastBody < 0) {
			return $children;
		}

		array_splice($others, $lastBody + 1, 0, $feet);

		return $others;
	}

	/**
	 * Where the first content an element or its descendants drew on a page is: its /StructParents
	 * key and MCID. Content inside a form XObject is left out, as its MCIDs are numbered apart.
	 *
	 * @param StructureElement $elem
	 *
	 * @return int[]|null Null when there is none
	 */
	private function firstContentKey(StructureElement $elem)
	{
		$hash = spl_object_hash($elem);
		if (array_key_exists($hash, $this->firstContentKeys)) {
			return $this->firstContentKeys[$hash];
		}

		$first = null;
		foreach ($elem->getMcids() as $mcr) {
			$key = [$mcr['page'], $mcr['mcid']];
			if (empty($mcr['stm']) && ($first === null || $key < $first)) {
				$first = $key;
			}
		}
		foreach ($elem->getChildren() as $child) {
			$key = $this->firstContentKey($child);
			if ($key !== null && ($first === null || $key < $first)) {
				$first = $key;
			}
		}

		return $this->firstContentKeys[$hash] = $first;
	}

	/**
	 * @param string $owner Such as '/Table', '/List' or '/Layout'
	 * @param array  $attrs
	 *
	 * @return string An attribute object of that owner, written inline, with strings as names
	 */
	private function buildAttrObject($owner, $attrs)
	{
		$parts = ['<</O ' . $owner];
		foreach ($attrs as $key => $value) {
			if ($key === 'BBox' && is_array($value)) {
				$coords = [];
				foreach ($value as $coord) {
					$coords[] = $this->formatNumber($coord);
				}
				$parts[] = '/' . $key . ' [' . implode(' ', $coords) . ']';
			} elseif ($key === 'Headers' && is_array($value)) {
				// The /ID of each header cell, as the byte string ISO 32000-1 Table 344 asks for
				$idList = [];
				foreach ($value as $id) {
					$idList[] = $this->writer->string($id);
				}
				$parts[] = '/' . $key . ' [' . implode(' ', $idList) . ']';
			} elseif (is_int($value) || is_float($value)) {
				$parts[] = '/' . $key . ' ' . $this->formatNumber($value);
			} else {
				$parts[] = '/' . $key . ' /' . $value;
			}
		}
		$parts[] = '>>';
		return implode(' ', $parts);
	}

	/**
	 * A number as PDF writes it. Casting a float to a string follows LC_NUMERIC, and a comma decimal
	 * locale would write "1,5"; '%F' does not.
	 *
	 * @param int|float $value
	 *
	 * @return string
	 */
	private function formatNumber($value)
	{
		if (is_int($value)) {
			return (string) $value;
		}
		$formatted = rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
		return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
	}

	/**
	 * Write the ParentTree, which leads from each page's MCIDs and each annotation to its element
	 *
	 * @return int Its object number
	 */
	private function writeParentTree()
	{
		$this->writer->object();
		$objNum = $this->mpdf->n;

		$parentTree     = $this->tree->getParentTree();
		$annotTree      = $this->tree->getAnnotParentTree();

		$entries = [];

		// A page's array is indexed by MCID. Imported MCIDs keep the numbers their document gave them
		// and can leave gaps, which are filled with null so the rest stay in place.
		foreach ($parentTree as $key => $mcidMap) {
			ksort($mcidMap);
			$refs   = [];
			$maxMcid = empty($mcidMap) ? -1 : max(array_keys($mcidMap));
			for ($mcid = 0; $mcid <= $maxMcid; $mcid++) {
				$refs[] = isset($mcidMap[$mcid])
					? $mcidMap[$mcid]->getObjNum() . ' 0 R'
					: 'null';
			}
			$entries[$key] = '[' . implode(' ', $refs) . ']';
		}

		foreach ($annotTree as $key => $structElem) {
			$entries[$key] = $structElem->getObjNum() . ' 0 R';
		}

		// A number tree lists its keys in order
		ksort($entries);

		$this->writer->write('<</Nums [');
		foreach ($entries as $key => $value) {
			$this->writer->write($key . ' ' . $value);
		}
		$this->writer->write(']>>');
		$this->writer->write('endobj');

		return $objNum;
	}

	/**
	 * @param array<string,string> $roleMappings Standard type by custom role
	 *
	 * @return int The object number of the RoleMap written
	 */
	private function writeRoleMap($roleMappings)
	{
		$this->writer->object();
		$objNum = $this->mpdf->n;

		$this->writer->write('<<');
		foreach ($roleMappings as $custom => $standard) {
			$this->writer->write('/' . $custom . ' /' . $standard);
		}
		$this->writer->write('>>');
		$this->writer->write('endobj');

		return $objNum;
	}

	/**
	 * @return array<int,int> Page object number by /StructParents
	 */
	private function buildPageRefMap()
	{
		$map = [];
		if (!isset($this->mpdf->pageDim) || !is_array($this->mpdf->pageDim)) {
			return $map;
		}
		foreach ($this->mpdf->pageDim as $pageNum => $dim) {
			if (isset($dim['structParents'], $dim['n'])) {
				$map[$dim['structParents']] = $dim['n'];
			}
		}
		return $map;
	}
}
