<?php

namespace Mpdf;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\Filter\AsciiHex;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;
use setasign\Fpdi\PdfReader\PageBoundaries;

/**
 * @mixin Mpdf
 */
trait FpdiTrait
{
	use \setasign\Fpdi\FpdiTrait {
		writePdfType as fpdiWritePdfType;
		useImportedPage as fpdiUseImportedPage;
		importPage as fpdiImportPage;
		adjustLastLink as fpdiAdjustLastLink;
		setSourceFile as fpdiSetSourceFile;
		setSourceFileWithParserParams as fpdiSetSourceFileWithParserParams;
	}

	protected $k = Mpdf::SCALE;

	/**
	 * The currently used object number.
	 *
	 * @var int
	 */
	public $currentObjectNumber;

	/**
	 * A counter for template ids.
	 *
	 * @var int
	 */
	protected $templateId = 0;

	/**
	 * The page ids handed out in PDF/UA mode for pages of an encrypted source, which FPDI
	 * cannot read. useImportedPage() draws a placeholder for them.
	 *
	 * @var array<string, array{file: string|null, pageNumber: int}> The source key and page number by page id
	 */
	protected $encryptedPageIds = [];

	/**
	 * The sources FPDI refused as encrypted, by encryptedSourceKey()
	 *
	 * @var array<string, true>
	 */
	protected $encryptedSourceFiles = [];

	/**
	 * The encryptedSourceKey() of the source set last, encrypted or not. importPage() reads
	 * from that source, so it alone decides whether a page becomes a placeholder.
	 *
	 * @var string|null
	 */
	protected $lastSetSourceKey = null;

	/**
	 * Where each page imported in PDF/UA mode came from, so an untagged one can be named in
	 * the warning or exception it raises
	 *
	 * @var array<string, array{source: string|null, pageNumber: int}> The source key and page number by page id
	 */
	protected $importedPageSources = [];

	protected function setPageFormat($format, $orientation)
	{
		// in mPDF this needs to be "P" (why ever)
		$orientation = 'P';
		$this->_setPageSize([$format['width'], $format['height']], $orientation);

		if ($orientation != $this->DefOrientation) {
			$this->OrientationChanges[$this->page] = true;
		}

		$this->wPt = $this->fwPt;
		$this->hPt = $this->fhPt;
		$this->w = $this->fw;
		$this->h = $this->fh;

		$this->CurOrientation = $orientation;
		$this->ResetMargins();
		$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
		$this->PageBreakTrigger = $this->h - $this->bMargin;

		$this->pageDim[$this->page]['w'] = $this->w;
		$this->pageDim[$this->page]['h'] = $this->h;
	}

	/**
	 * Set the minimal PDF version.
	 *
	 * @param string $pdfVersion
	 */
	protected function setMinPdfVersion($pdfVersion)
	{
		if (\version_compare($pdfVersion, $this->pdf_version, '>')) {
			$this->pdf_version = $pdfVersion;
		}
	}

	/**
	 * The reader of a source already opened, for collaborators such as FpdiStructMerger
	 *
	 * @param  string $readerId As held in $importedPages[$pageId]['readerId']
	 * @return \setasign\Fpdi\PdfReader\PdfReader
	 */
	public function getSourcePdfReader($readerId)
	{
		return $this->getPdfReader($readerId);
	}

	/**
	 * Set the source PDF file.
	 *
	 * FPDI takes no password, so it refuses any source with an /Encrypt entry. In PDF/UA
	 * mode that refusal becomes an MpdfException, or with PDFUAauto a placeholder for each
	 * of the source's pages.
	 *
	 * @param  string|resource|\setasign\Fpdi\PdfParser\StreamReader $file
	 * @return int The page count, read from the page tree of an encrypted source
	 * @throws \Mpdf\MpdfException In PDF/UA mode without PDFUAauto, for an encrypted source
	 * @throws CrossReferenceException
	 */
	public function setSourceFile($file)
	{
		try {
			$pageCount = $this->fpdiSetSourceFile($file);
			if ($this->PDFUA) {
				$this->lastSetSourceKey = $this->encryptedSourceKey($file);
			}
			return $pageCount;
		} catch (CrossReferenceException $e) {
			if ($e->getCode() !== CrossReferenceException::ENCRYPTED) {
				throw $e;
			}
			return $this->handleEncryptedSetSourceFile($file, $e);
		}
	}

	/**
	 * Set the source PDF file with parser parameters, treating an encrypted source as
	 * setSourceFile() does.
	 *
	 * @param  string|resource|\setasign\Fpdi\PdfParser\StreamReader $file
	 * @param  array $parserParams
	 * @return int The page count, read from the page tree of an encrypted source
	 * @throws \Mpdf\MpdfException
	 * @throws CrossReferenceException
	 */
	public function setSourceFileWithParserParams($file, array $parserParams = [])
	{
		try {
			$pageCount = $this->fpdiSetSourceFileWithParserParams($file, $parserParams);
			if ($this->PDFUA) {
				$this->lastSetSourceKey = $this->encryptedSourceKey($file);
			}
			return $pageCount;
		} catch (CrossReferenceException $e) {
			if ($e->getCode() !== CrossReferenceException::ENCRYPTED) {
				throw $e;
			}
			return $this->handleEncryptedSetSourceFile($file, $e);
		}
	}

	/**
	 * Throw for a source FPDI refused as encrypted, or with PDFUAauto mark it so its pages
	 * are imported as placeholders.
	 *
	 * @param  mixed                   $file The argument given to setSourceFile()
	 * @param  CrossReferenceException $e    What FPDI threw, rethrown outside PDF/UA mode
	 * @return int The source's page count
	 * @throws \Mpdf\MpdfException
	 * @throws CrossReferenceException
	 */
	private function handleEncryptedSetSourceFile($file, CrossReferenceException $e)
	{
		if (empty($this->PDFUA)) {
			throw $e;
		}

		if (empty($this->PDFUAauto)) {
			throw new \Mpdf\MpdfException(
				'Imported PDF source is encrypted (ISO 32000-1:2008 §7.6) and cannot be tagged. '
				. 'vendor/setasign/fpdi exposes no password setter and refuses any document with '
				. 'an /Encrypt entry. Decrypt the source upstream (e.g. `qpdf --decrypt input.pdf '
				. 'output.pdf`), disable PDFUA on this import, or enable PDFUAauto to fall back '
				. 'to /Artifact wrapping (Matterhorn 01-007).',
				$e->getCode()
			);
		}

		$key = $this->encryptedSourceKey($file);
		$this->encryptedSourceFiles[$key] = true;
		$this->lastSetSourceKey = $key;

		$pageCount = $this->countEncryptedSourcePages($file);

		// Warned here as well as on each placeholder, in case no page is imported
		$this->ua->addWarning(sprintf(
			'Imported PDF source is encrypted (ISO 32000-1:2008 §7.6) and cannot be parsed by '
			. 'vendor/setasign/fpdi. Auto-mode fallback: importPage() will return a synthetic '
			. 'pageId and useImportedPage() will draw an /Artifact <</Type /Layout>> '
			. 'visible placeholder (border + caption) for each of the %d source page(s) in place '
			. 'of the original content. Matterhorn 01-007.',
			$pageCount
		));

		return $pageCount;
	}

	/**
	 * The page count of an encrypted source FPDI would not parse.
	 *
	 * Encryption covers only strings and streams, so the /Count of the page tree root can be
	 * read without the key. Where the bytes cannot be read or hold no page tree, 1.
	 *
	 * @param  mixed $file The argument given to setSourceFile()
	 * @return int
	 */
	private function countEncryptedSourcePages($file)
	{
		$bytes = $this->readSourceBytes($file);
		if ($bytes === null || $bytes === '') {
			return 1;
		}

		// [^>]*? stops at the end of a dictionary, so a /Count is only paired with the
		// /Pages of its own node. The root carries the largest count.
		$max = 0;
		if (preg_match_all(
			'#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)|/Count\s+(\d+)[^>]*?/Type\s*/Pages\b#s',
			$bytes,
			$matches,
			PREG_SET_ORDER
		)) {
			foreach ($matches as $m) {
				$n = max((int) $m[1], isset($m[2]) ? (int) $m[2] : 0);
				if ($n > $max) {
					$max = $n;
				}
			}
		}

		return $max > 0 ? $max : 1;
	}

	/**
	 * The bytes of a setSourceFile() argument, leaving a stream where it was found
	 *
	 * @param  mixed $file A path, a stream resource or a StreamReader
	 * @return string|null Null where they cannot be read
	 */
	private function readSourceBytes($file)
	{
		if (is_string($file)) {
			$bytes = @file_get_contents($file);
			return $bytes !== false ? $bytes : null;
		}

		$stream = null;
		if ($file instanceof \setasign\Fpdi\PdfParser\StreamReader) {
			$stream = $file->getStream();
		} elseif (is_resource($file)) {
			$stream = $file;
		}

		if (is_resource($stream)) {
			$pos   = @ftell($stream);
			@rewind($stream);
			$bytes = @stream_get_contents($stream);
			if ($pos !== false) {
				@fseek($stream, $pos);
			}
			return $bytes !== false ? $bytes : null;
		}

		return null;
	}

	/**
	 * A key identifying a setSourceFile() argument, made as FPDI's getPdfReaderId() makes
	 * one but without opening a reader, which would throw for an encrypted source.
	 *
	 * @param  mixed $file
	 * @return string
	 */
	private function encryptedSourceKey($file)
	{
		if (is_resource($file)) {
			return 'resource:' . (string) $file;
		}
		if (is_string($file)) {
			// The key finds its way into page ids and warnings, so it carries the basename
			// and a hash of the full path rather than the path itself
			$rp     = @realpath($file);
			$canon  = $rp !== false ? $rp : $file;
			$digest = substr(sha1($canon), 0, 12);
			return 'file:' . $this->redactPath($canon) . '@' . $digest;
		}
		if (is_object($file)) {
			return 'object:' . spl_object_hash($file);
		}
		return 'unknown:' . gettype($file);
	}

	/**
	 * A path as it may appear in a warning: its basename, so the layout of the server is
	 * not given away
	 *
	 * @param  mixed $path
	 * @return string Empty for anything but a non-empty string
	 */
	private function redactPath($path)
	{
		if (!is_string($path) || $path === '') {
			return '';
		}
		return basename($path);
	}

	/**
	 * Whether a page id stands for a page of an encrypted source
	 *
	 * @param  mixed $pageId
	 * @return bool
	 */
	public function isEncryptedPlaceholder($pageId)
	{
		return is_string($pageId)
			&& strpos($pageId, \Mpdf\Ua\Import\FpdiStructMerger::ENCRYPTED_PAGE_PLACEHOLDER_ID_PREFIX) === 0
			&& isset($this->encryptedPageIds[$pageId]);
	}

	/**
	 * Get the next template id.
	 *
	 * @return int
	 */
	protected function getNextTemplateId()
	{
		return $this->templateId++;
	}

	/**
	 * Draws an imported page or a template onto the page or another template.
	 *
	 * Omit one of the size parameters (width, height) to calculate the other one automatically in view to the aspect
	 * ratio.
	 *
	 * @param mixed $tpl The template id
	 * @param float|int|array $x The abscissa of upper-left corner. Alternatively you could use an assoc array
	 *                           with the keys "x", "y", "width", "height", "adjustPageSize".
	 * @param float|int $y The ordinate of upper-left corner.
	 * @param float|int|null $width The width.
	 * @param float|int|null $height The height.
	 * @param bool $adjustPageSize
	 * @return array The size
	 * @see Fpdi::getTemplateSize()
	 */
	public function useTemplate($tpl, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false)
	{
		return $this->useImportedPage($tpl, $x, $y, $width, $height, $adjustPageSize);
	}

	/**
	 * Draws an imported page onto the page.
	 *
	 * Omit one of the size parameters (width, height) to calculate the other one automatically in view to the aspect
	 * ratio.
	 *
	 * @param mixed $pageId The page id
	 * @param float|int|array $x The abscissa of upper-left corner. Alternatively you could use an assoc array
	 *                           with the keys "x", "y", "width", "height", "adjustPageSize".
	 * @param float|int $y The ordinate of upper-left corner.
	 * @param float|int|null $width The width.
	 * @param float|int|null $height The height.
	 * @param bool $adjustPageSize
	 * @return array The size.
	 * @see Fpdi::getTemplateSize()
	 */
	public function useImportedPage($pageId, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false)
	{
		if ($this->state == 0) {
			$this->AddPage();
		}

		// 'alt' names an untagged page in PDF/UA mode. It has no local variable, so
		// extract() would drop it.
		$importAlt = null;
		if (is_array($x)) {
			if (array_key_exists('alt', $x)) {
				$importAlt = $x['alt'];
			}
			unset($x['pageId'], $x['alt']);
			extract($x, EXTR_IF_EXISTS);
			if (is_array($x)) {
				$x = 0;
			}
		}

		// A page of an encrypted source has no form XObject; an outline and a caption
		// naming the source stand in for it, as an artifact
		if ($this->PDFUA && $this->isEncryptedPlaceholder($pageId)) {
			$pdfuaMerger = $this->ua->getFpdiStructMerger();

			// With no size given, the placeholder fills the content area
			$resolvedWidth  = ($width !== null) ? $width : $this->pgwidth;
			$resolvedHeight = ($height !== null) ? $height : ($this->h - $this->tMargin - $this->bMargin);

			$this->writer->write('/Artifact <</Type /Layout>> BDC');
			$this->drawEncryptedSourcePlaceholder(
				$x,
				$y,
				$resolvedWidth,
				$resolvedHeight,
				$this->encryptedPlaceholderCaption($pageId)
			);
			$this->writer->write('EMC');

			$pdfuaMerger->addUntaggedWarning(
				'Imported PDF page is encrypted (ISO 32000-1:2008 §7.6) and cannot be parsed by '
				. 'vendor/setasign/fpdi. A placeholder /Artifact <</Type /Layout>> '
				. 'BDC … EMC pair is drawn in place of the original page content. Decrypt the source '
				. 'upstream (e.g. `qpdf --decrypt`) for accessible imports. Matterhorn 01-007.'
			);

			foreach ($pdfuaMerger->getUntaggedWarnings() as $w) {
				$this->ua->addWarning($w);
			}

			return [
				'width'       => $resolvedWidth,
				'height'      => $resolvedHeight,
				0             => $resolvedWidth,
				1             => $resolvedHeight,
				'orientation' => $resolvedWidth >= $resolvedHeight ? 'L' : 'P',
			];
		}

		// A tagged source brings its structure tree across with the page. An untagged one, or
		// a tagged one whose structure fails verifyAndPrepareMerge(), is tagged as a Figure
		// when given 'alt', and otherwise throws or, with PDFUAauto, becomes an artifact.
		// currentReaderId is only set while writing, so the reader comes from importedPages.
		$pdfuaMerger    = null;
		$useTaggedMerge = false;
		$tier1Figure    = false;
		if ($this->PDFUA && isset($this->importedPages[$pageId])) {
			$pdfuaMerger = $this->ua->getFpdiStructMerger();
			$readerId    = $this->importedPages[$pageId]['readerId'];

			if ($pdfuaMerger->sourceIsTagged($readerId) && $pdfuaMerger->verifyAndPrepareMerge($pageId)) {
				$useTaggedMerge = true;
				// The object numbers are not known until the document is written, when
				// patchMergedSubtreeObjectNumbers() fills them in
				$pdfuaMerger->mergePageStructSubtree($pageId, 0, 0);
				foreach ($this->importedPages[$pageId]['externalLinks'] as $i => $link) {
					if (isset($link['sourceObjectNumber'])) {
						$this->importedPages[$pageId]['externalLinks'][$i]['structElem'] = $pdfuaMerger->getImportedLinkElement($pageId, $link['sourceObjectNumber']);
					}
				}
				// Every placement, a page template reused included
				$pdfuaMerger->recordHostPage($pageId, $this->page);
			} else {
				// An artifact hides the whole page from assistive technology, so it is only
				// used with PDFUAauto and never silently
				$altText = is_string($importAlt) ? trim($importAlt) : null;
				if ($altText !== null && $altText !== '') {
					$this->ua->getStructureTree()->open('Figure', ['Alt' => $altText]);
					$importMcid = $this->ua->getStructureTree()->addContent($this->pdfuaStructParents());
					$this->ua->getMarkedContentHelper()->begin('Figure', $importMcid);
					$tier1Figure = true;
				} elseif (empty($this->PDFUAauto)) {
					throw new \Mpdf\MpdfException(
						'PDF/UA-1: imported PDF ' . $this->importedPageSourceLabel($pageId)
						. ' is untagged; wrapping it as /Artifact <</Type /Layout>> would hide '
						. 'the real page content from assistive technology (ISO 14289-1:2014 §7.1; '
						. 'Matterhorn 01-007). Import a source that preserves its struct tagging, '
						. "pass an accessible name via useImportedPage(\$id, ['alt' => 'description']) "
						. 'to tag the page as a Figure, or enable PDFUAauto to wrap it as an Artifact '
						. 'and record a warning instead of throwing.'
					);
				} else {
					$this->writer->write('/Artifact <</Type /Layout>> BDC');
				}
			}
		}

		$newSize = $this->fpdiUseImportedPage($pageId, $x, $y, $width, $height, $adjustPageSize);

		if ($this->PDFUA && $pdfuaMerger !== null) {
			if ($useTaggedMerge) {
				foreach ($pdfuaMerger->getUntaggedWarnings() as $w) {
					$this->ua->addWarning($w);
				}
			} elseif ($tier1Figure) {
				$this->ua->getMarkedContentHelper()->end();
				$this->ua->getStructureTree()->close();
			} else {
				$this->writer->write('EMC');
				$pdfuaMerger->addUntaggedWarning(
					'Imported PDF ' . $this->importedPageSourceLabel($pageId)
					. ' is untagged and was wrapped as /Artifact <</Type /Layout>> — its content '
					. 'is not exposed to assistive technology. Pass an accessible name via '
					. "useImportedPage(\$id, ['alt' => 'description']) to tag it as a Figure, or "
					. 'import a source that preserves struct tagging. Matterhorn 01-007.'
				);

				foreach ($pdfuaMerger->getUntaggedWarnings() as $w) {
					$this->ua->addWarning($w);
				}
			}
		}

		return $newSize;
	}

	/**
	 * Tag the link just made for an imported link annotation with the element that tagged it in
	 * its source, rather than a Link element of its own at the end of the document
	 *
	 * @param array     $externalLink
	 * @param float|int $xPt
	 * @param float|int $scaleX
	 * @param float|int $yPt
	 * @param float|int $newHeightPt
	 * @param float|int $scaleY
	 * @param array     $importedPage
	 */
	protected function adjustLastLink($externalLink, $xPt, $scaleX, $yPt, $newHeightPt, $scaleY, $importedPage)
	{
		$this->fpdiAdjustLastLink($externalLink, $xPt, $scaleX, $yPt, $newHeightPt, $scaleY, $importedPage);

		if (isset($externalLink['structElem'])) {
			$this->PageLinks[$this->page][count($this->PageLinks[$this->page]) - 1]['structElem'] = $externalLink['structElem'];
		}
	}

	/**
	 * Outline where a page of an encrypted source would have gone and caption it, so a
	 * sighted reader can see the page is missing. The caller wraps it as an artifact.
	 *
	 * @param  float|int $x       Left edge
	 * @param  float|int $y       Top edge
	 * @param  float|int $width
	 * @param  float|int $height
	 * @param  string    $caption Drawn in the top-left corner
	 */
	protected function drawEncryptedSourcePlaceholder($x, $y, $width, $height, $caption = '')
	{
		if ($width <= 0 || $height <= 0) {
			return;
		}

		// Q restores the graphics state but not mPDF's record of it, which is put back by hand
		$prevLineWidth  = $this->LineWidth;
		$prevDrawColor  = $this->DrawColor;
		$prevFillColor  = $this->FillColor;
		$prevTextColor  = $this->TextColor;
		$prevColorFlag  = $this->ColorFlag;
		$prevFontFamily = $this->FontFamily;
		$prevFontStyle  = $this->FontStyle;
		$prevFontSizePt = $this->FontSizePt;

		$this->writer->write('q');

		$this->SetDrawColor(128);
		$this->SetLineWidth(0.2);
		$this->Rect($x, $y, $width, $height, 'S');

		// In the current font, which is embedded as PDF/UA requires
		if ($caption !== '') {
			$this->SetFont($prevFontFamily !== '' ? $prevFontFamily : '', '', 8);
			$this->SetTextColor(128);
			$this->Text($x + 2, $y + $this->FontSize + 1, $caption);
		}

		$this->writer->write('Q');

		$this->LineWidth = $prevLineWidth;
		$this->DrawColor = $prevDrawColor;
		$this->FillColor = $prevFillColor;
		$this->TextColor = $prevTextColor;
		$this->ColorFlag = $prevColorFlag;
		if (isset($this->pageoutput[$this->page])) {
			unset(
				$this->pageoutput[$this->page]['LineWidth'],
				$this->pageoutput[$this->page]['DrawColor'],
				$this->pageoutput[$this->page]['FillColor']
			);
		}
		if ($prevFontFamily !== '') {
			$this->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt);
		}
	}

	/**
	 * The caption of the placeholder for a page of an encrypted source, naming the source
	 * by its basename
	 *
	 * @param  string $pageId
	 * @return string
	 */
	private function encryptedPlaceholderCaption($pageId)
	{
		$meta = isset($this->encryptedPageIds[$pageId]) ? $this->encryptedPageIds[$pageId] : [];
		$label = (isset($meta['file']) && is_string($meta['file'])) ? $meta['file'] : '';
		if (preg_match('/^file:(?P<name>.*)@[0-9a-f]+$/', $label, $m) && $m['name'] !== '') {
			$label = $m['name'];
		}
		if ($label === '') {
			$label = 'encrypted PDF';
		}
		$pageNumber = isset($meta['pageNumber']) ? (int) $meta['pageNumber'] : 0;

		return sprintf('Encrypted PDF source omitted (page %d): %s', $pageNumber, $label);
	}

	/**
	 * "page N of <basename>" for an imported page, to name it in a warning or exception
	 *
	 * @param  mixed $pageId
	 * @return string
	 */
	private function importedPageSourceLabel($pageId)
	{
		$meta   = isset($this->importedPageSources[$pageId]) ? $this->importedPageSources[$pageId] : [];
		$source = (isset($meta['source']) && is_string($meta['source'])) ? $meta['source'] : '';
		if (preg_match('/^file:(?P<name>.*)@[0-9a-f]+$/', $source, $m) && $m['name'] !== '') {
			$source = $m['name'];
		}
		if ($source === '') {
			$source = 'source';
		}
		$pageNumber = isset($meta['pageNumber']) ? (int) $meta['pageNumber'] : 0;

		return $pageNumber > 0
			? sprintf('page %d of %s', $pageNumber, $source)
			: sprintf('page of %s', $source);
	}

	/**
	 * Imports a page.
	 *
	 * @param int $pageNumber The page number.
	 * @param string $box The page boundary to import. Default set to PageBoundaries::CROP_BOX.
	 * @param bool $groupXObject Define the form XObject as a group XObject to support transparency (if used).
	 * @param bool $importExternalLinks
	 * @return string A unique string identifying the imported page.
	 * @throws CrossReferenceException
	 * @throws FilterException
	 * @throws PdfParserException
	 * @throws PdfTypeException
	 * @throws PdfReaderException
	 * @see PageBoundaries
	 */
	public function importPage($pageNumber, $box = PageBoundaries::CROP_BOX, $groupXObject = true, $importExternalLinks = true)
	{
		// FPDI has no parser for an encrypted source and would throw again
		if ($this->PDFUA
			&& $this->lastSetSourceKey !== null
			&& isset($this->encryptedSourceFiles[$this->lastSetSourceKey])
		) {
			return $this->handleEncryptedImportInUaMode($pageNumber, $this->lastSetSourceKey);
		}

		try {
			$pageId = $this->fpdiImportPage($pageNumber, $box, $groupXObject, $importExternalLinks);
		} catch (CrossReferenceException $e) {
			if ($this->PDFUA && $e->getCode() === CrossReferenceException::ENCRYPTED) {
				// Encryption FPDI only found on reading the page
				if ($this->lastSetSourceKey !== null) {
					$this->encryptedSourceFiles[$this->lastSetSourceKey] = true;
				}
				return $this->handleEncryptedImportInUaMode($pageNumber, $this->lastSetSourceKey);
			}
			throw $e;
		}

		if ($this->PDFUA) {
			$this->importedPageSources[$pageId] = [
				'source'     => $this->lastSetSourceKey,
				'pageNumber' => (int) $pageNumber,
			];
			$this->importedPages[$pageId]['externalLinks'] = $this->ua->getFpdiStructMerger()->identifyImportedLinks(
				$this->importedPages[$pageId]['readerId'],
				(int) $pageNumber,
				$this->importedPages[$pageId]['externalLinks']
			);
		}

		return $pageId;
	}

	/**
	 * The page id standing for a page of an encrypted source, or without PDFUAauto an
	 * exception
	 *
	 * @param  int         $pageNumber
	 * @param  string|null $sourceKey  The encryptedSourceKey() of the source
	 * @return string
	 * @throws \Mpdf\MpdfException
	 */
	private function handleEncryptedImportInUaMode($pageNumber, $sourceKey = null)
	{
		if (empty($this->PDFUAauto)) {
			throw new \Mpdf\MpdfException(
				'Imported PDF source is encrypted (ISO 32000-1:2008 §7.6) and cannot be tagged. '
				. 'vendor/setasign/fpdi exposes no password setter and refuses any document with '
				. 'an /Encrypt entry. Decrypt the source upstream (e.g. `qpdf --decrypt input.pdf '
				. 'output.pdf`), disable PDFUA on this import, or enable PDFUAauto to fall back '
				. 'to /Artifact wrapping (Matterhorn 01-007).'
			);
		}

		$pageId = \Mpdf\Ua\Import\FpdiStructMerger::ENCRYPTED_PAGE_PLACEHOLDER_ID_PREFIX
			. ($sourceKey !== null ? $sourceKey . ':' : '')
			. ((int) $pageNumber)
			. ':' . count($this->encryptedPageIds);

		$this->encryptedPageIds[$pageId] = [
			'file'       => is_string($sourceKey) ? $sourceKey : null,
			'pageNumber' => (int) $pageNumber,
		];

		return $pageId;
	}

	/**
	 * Imports the external page links
	 *
	 * @param int $pageNumber The page number.
	 * @return array
	 * @throws CrossReferenceException
	 * @throws PdfTypeException
	 * @throws \setasign\Fpdi\PdfParser\PdfParserException
	 * @deprecated Handled automatically in self::importPage() when $importExternalLinks = true (default))
	 */
	public function getImportedExternalPageLinks($pageNumber)
	{
		return [];
	}

	/**
	 * @param mixed $pageId The page id
	 * @param int|float $x The abscissa of upper-left corner.
	 * @param int|float $y The ordinate of upper-right corner.
	 * @param array $newSize The size.
	 * @deprecated Handled automatically in self::importPage() when $importExternalLinks = true (default)
	 */
	public function setImportedPageLinks($pageId, $x, $y, $newSize)
	{
	}

	/**
	 * Get the size of an imported page or template.
	 *
	 * Omit one of the size parameters (width, height) to calculate the other one automatically in view to the aspect
	 * ratio.
	 *
	 * @param mixed $tpl The template id
	 * @param float|int|null $width The width.
	 * @param float|int|null $height The height.
	 * @return array|bool An array with following keys: width, height, 0 (=width), 1 (=height), orientation (L or P)
	 */
	public function getTemplateSize($tpl, $width = null, $height = null)
	{
		return $this->getImportedPageSize($tpl, $width, $height);
	}

	/**
	 * @throws CrossReferenceException
	 * @throws PdfTypeException
	 * @throws \setasign\Fpdi\PdfParser\PdfParserException
	 */
	public function writeImportedPagesAndResolvedObjects()
	{
		$this->currentReaderId = null;

		foreach ($this->importedPages as $key => $pageData) {
			$this->writer->object();
			$foXObjectObjNum                           = $this->n;
			$this->importedPages[$key]['objectNumber'] = $foXObjectObjNum;
			$this->currentReaderId = $pageData['readerId'];

			// The form XObject of a page whose structure was brought across needs /StructParents
			// for the parent tree to reach it (ISO 32000-1 §14.7.4.4)
			if ($this->PDFUA) {
				$merger         = $this->ua->getFpdiStructMerger();
				$structParentsN = $merger->getFormXObjectStructParents($key);
				if ($structParentsN >= 0 && $pageData['stream'] instanceof PdfStream) {
					$pageData['stream']->value->value['StructParents'] = PdfNumeric::create($structParentsN);
				}

				// The pages were written before this, so both object numbers are known
				$merger->patchMergedSubtreeObjectNumbers($key, $foXObjectObjNum);
			}

			$this->writePdfType($pageData['stream']);
			$this->_put('endobj');
		}

		foreach (\array_keys($this->readers) as $readerId) {
			$parser = $this->getPdfReader($readerId)->getParser();
			$this->currentReaderId = $readerId;

			while (($objectNumber = \array_pop($this->objectsToCopy[$readerId])) !== null) {
				try {
					$object = $parser->getIndirectObject($objectNumber);

				} catch (CrossReferenceException $e) {
					if ($e->getCode() === CrossReferenceException::OBJECT_NOT_FOUND) {
						$object = PdfIndirectObject::create($objectNumber, 0, new PdfNull());
					} else {
						throw $e;
					}
				}

				$this->writePdfType($object);
			}
		}

		$this->currentReaderId = null;
	}

	public function getImportedPages()
	{
		return $this->importedPages;
	}

	protected function _put($s, $newLine = true)
	{
		$this->buffer->append($s, $newLine);
	}

	/**
	 * Writes a PdfType object to the resulting buffer.
	 *
	 * @param PdfType $value
	 * @throws PdfTypeException
	 */
	public function writePdfType(PdfType $value)
	{
		if (!$this->encrypted) {
			if ($value instanceof PdfIndirectObject) {
				/**
				 * @var $value PdfIndirectObject
				 */
				$n = $this->objectMap[$this->currentReaderId][$value->objectNumber];
				$this->writer->object($n);
				$this->writePdfType($value->value);
				$this->_put('endobj');
				return;
			}

			$this->fpdiWritePdfType($value);
			return;
		}

		if ($value instanceof PdfString) {
			$string = PdfString::unescape($value->value);
			$string = $this->protection->rc4($this->protection->objectKey($this->currentObjectNumber), $string);
			$value->value = $this->writer->escape($string);

		} elseif ($value instanceof PdfHexString) {
			$filter = new AsciiHex();
			$string = $filter->decode($value->value);
			$string = $this->protection->rc4($this->protection->objectKey($this->currentObjectNumber), $string);
			$value->value = $filter->encode($string, true);

		} elseif ($value instanceof PdfStream) {
			$stream = $value->getStream();
			$stream = $this->protection->rc4($this->protection->objectKey($this->currentObjectNumber), $stream);
			$dictionary = $value->value;
			$dictionary->value['Length'] = PdfNumeric::create(\strlen($stream));
			$value = PdfStream::create($dictionary, $stream);

		} elseif ($value instanceof PdfIndirectObject) {
			/**
			 * @var $value PdfIndirectObject
			 */
			$this->currentObjectNumber = $this->objectMap[$this->currentReaderId][$value->objectNumber];
			/**
			 * @var $value PdfIndirectObject
			 */
			$n = $this->objectMap[$this->currentReaderId][$value->objectNumber];
			$this->writer->object($n);
			$this->writePdfType($value->value);
			$this->_put('endobj');
			return;
		}

		$this->fpdiWritePdfType($value);
	}
}
