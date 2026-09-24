<?php

namespace Mpdf;

use Mpdf\Import\DeviceColorScanner;
use Mpdf\Import\ObjectStreamCrossReference;
use Mpdf\Import\PdfParser;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\Filter\AsciiHex;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfToken;
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
		getPdfParserInstance as fpdiGetPdfParserInstance;
	}

	protected $k = Mpdf::SCALE;

	/**
	 * A counter for template ids.
	 *
	 * @var int
	 */
	protected $templateId = 0;

	/**
	 * The default colour spaces each imported document's resource dictionaries name, by reader id: the
	 * ICC-based space a device colour space PDF/X does not permit is painted in instead
	 *
	 * @var string[][] Object references, by DefaultRGB or DefaultGray
	 */
	private $importDefaultSpaces = [];

	/**
	 * The objects of each imported document that are resource dictionaries, by reader id
	 *
	 * @var bool[][] By object number
	 */
	private $importResourceObjects = [];

	/**
	 * Whether a reference in an imported document names a generation of its object other than the one the document's
	 * cross-reference now lists, which makes it a reference to the null object (ISO 32000-1, 7.3.10 and 7.5.4)
	 *
	 * @param \setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference $reference
	 *
	 * @return bool
	 */
	private function isStale(PdfIndirectObjectReference $reference)
	{
		$crossReference = $this->getPdfReader($this->currentReaderId)->getParser()->getCrossReference();
		if (!$crossReference instanceof ObjectStreamCrossReference) {
			return false;
		}

		$generation = $crossReference->getGenerationFor($reference->value);

		return $generation !== null && $generation !== (int) $reference->generationNumber;
	}

	/**
	 * The parser FPDI chooses, except that its own base parser is replaced by mPDF's, which also reads
	 * cross-reference and object streams (PDF 1.5)
	 *
	 * @param \setasign\Fpdi\PdfParser\StreamReader $streamReader
	 * @param array                                 $parserParams Passed on to FPDI's choice
	 *
	 * @return \setasign\Fpdi\PdfParser\PdfParser
	 */
	protected function getPdfParserInstance(StreamReader $streamReader, array $parserParams = [])
	{
		$parser = $this->fpdiGetPdfParserInstance($streamReader, $parserParams);

		return get_class($parser) === 'setasign\Fpdi\PdfParser\PdfParser' ? new PdfParser($streamReader) : $parser;
	}

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
	 * Not under PDF/A, which keeps the version its standard is built on. PDF/X-1a:2003 is written as PDF 1.4 and
	 * PDF/X-4 as PDF 1.6, and neither may say more, so a page imported from a later version leaves the version
	 * where it is.
	 *
	 * @param string $pdfVersion
	 */
	protected function setMinPdfVersion($pdfVersion)
	{
		if ($this->PDFX) {
			$ceiling = $this->isPdfx4() ? '1.6' : '1.4';
			if (\version_compare($pdfVersion, $ceiling, '>')) {
				$this->pdfaxWarning(sprintf('A page imported from a PDF %s file may use more than the PDF %s that %s is written as.', $pdfVersion, $ceiling, $this->pdfxVersionLabel()));
				$pdfVersion = $ceiling;
			}
		}

		if (!$this->PDFA && \version_compare($pdfVersion, $this->pdf_version, '>')) {
			$this->pdf_version = $pdfVersion;
		}
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

		/* Extract $x if an array */
		if (is_array($x)) {
			unset($x['pageId']);
			extract($x, EXTR_IF_EXISTS);
			if (is_array($x)) {
				$x = 0;
			}
		}

		$newSize = $this->fpdiUseImportedPage($pageId, $x, $y, $width, $height, $adjustPageSize);

		return $newSize;
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
		$pageId = $this->fpdiImportPage($pageNumber, $box, $groupXObject, $importExternalLinks);

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

		if ($this->PDFX) {
			$this->conformImportedColor();
		}

		foreach ($this->importedPages as $key => $pageData) {
			// An imported page is drawn in a transparency group of its own, which PDF/X-1a and PDF/A-1 forbid
			if (!$this->transparencyAllowed()) {
				unset($pageData['stream']->value->value['Group']);
			}

			$this->writer->object();
			$this->importedPages[$key]['objectNumber'] = $this->n;
			$this->currentReaderId = $pageData['readerId'];
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

	/**
	 * PDF/X permits only the device colour spaces its output intent prints to, and mPDF writes what it
	 * imports as it was. So where an imported page paints in one PDF/X-4 does not permit, its resource
	 * dictionaries name a default colour space (ISO 32000-1, 8.6.5.6): the ICC-based space mPDF paints its
	 * own RGB or grey in, or for CMYK under an RGB or grey intent the bundled SWOP profile - a guess at the
	 * press the CMYK was made for, so made only under PDFXauto, as mPDF's other conversions are. DeviceRGB in
	 * PDF/X-1a, which permits no ICC-based colour, cannot be remapped, and is warned of, and refused
	 * without PDFXauto.
	 *
	 * Asked between objects, as the ICC-based colour spaces are written when first asked for.
	 */
	private function conformImportedColor()
	{
		$this->importDefaultSpaces = [];
		$this->importResourceObjects = [];

		$scanners = [];
		foreach ($this->importedPages as $pageData) {
			$readerId = $pageData['readerId'];
			if (!isset($scanners[$readerId])) {
				$scanners[$readerId] = new DeviceColorScanner($this->getPdfReader($readerId)->getParser());
			}
			$scanners[$readerId]->scan($pageData['stream']);
		}

		$permitted = $this->isPdfx4()
			? [1 => ['Gray'], 3 => ['RGB'], 4 => ['Gray', 'CMYK']][$this->pdfxOutputChannels()]
			: ['Gray', 'CMYK'];
		$intent = [1 => 'grey', 3 => 'RGB', 4 => 'CMYK'][$this->pdfxOutputChannels()];
		$remaps = ['RGB' => 'calibratedRgb', 'Gray' => 'calibratedGray', 'CMYK' => 'calibratedCmyk'];

		foreach ($scanners as $readerId => $scanner) {
			$defaults = [];
			foreach (array_diff($scanner->used(), $permitted) as $family) {
				$space = $this->writer->{$remaps[$family]}();

				if ($space === null) {
					$this->pdfaxWarning(sprintf(
						'An imported page paints in Device%s, which %s files printing to %s do not permit, and mPDF cannot convert what it imports. (Left as it is)',
						$family,
						$this->pdfxVersionLabel(),
						$intent
					));
					continue;
				}

				if ($family === 'CMYK') {
					$this->pdfaxWarning(sprintf('An imported page paints in DeviceCMYK, which PDF/X-4 files printing to %s do not permit. (Painted as the CMYK of the bundled SWOP profile)', $intent));
				}

				$defaults['Default' . $family] = $space . ' 0 R';
			}

			if ($defaults) {
				$this->importDefaultSpaces[$readerId] = $defaults;
				$this->importResourceObjects[$readerId] = $scanner->resourceObjects();
			}
		}
	}

	/**
	 * @param PdfType $value An object of the document being imported from, as it is about to be written
	 *
	 * @return PdfType The object, with the default colour spaces its document's resource dictionaries name
	 */
	private function withImportDefaultSpaces(PdfType $value)
	{
		if (!isset($this->importDefaultSpaces[$this->currentReaderId])) {
			return $value;
		}

		if ($value instanceof PdfIndirectObject && $value->value instanceof PdfDictionary
			&& isset($this->importResourceObjects[$this->currentReaderId][$value->objectNumber])) {
			return PdfIndirectObject::create($value->objectNumber, $value->generationNumber, $this->resourcesWithDefaults($value->value));
		}

		if ($value instanceof PdfDictionary && isset($value->value['Resources']) && $value->value['Resources'] instanceof PdfDictionary) {
			$entries = $value->value;
			$entries['Resources'] = $this->resourcesWithDefaults($entries['Resources']);

			return PdfDictionary::create($entries);
		}

		return $value;
	}

	/**
	 * @param PdfDictionary $resources A resource dictionary of the document being imported from
	 *
	 * @return PdfDictionary A copy naming the default colour spaces, where it names none of its own
	 */
	private function resourcesWithDefaults(PdfDictionary $resources)
	{
		$spaces = [];
		if (isset($resources->value['ColorSpace'])) {
			$named = PdfType::resolve($resources->value['ColorSpace'], $this->getPdfReader($this->currentReaderId)->getParser());
			if ($named instanceof PdfDictionary) {
				$spaces = $named->value;
			}
		}

		foreach ($this->importDefaultSpaces[$this->currentReaderId] as $name => $reference) {
			if (!isset($spaces[$name])) {
				$spaces[$name] = PdfToken::create($reference);
			}
		}

		$entries = $resources->value;
		$entries['ColorSpace'] = PdfDictionary::create($spaces);

		return PdfDictionary::create($entries);
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
		if ($this->importDefaultSpaces && ($value instanceof PdfDictionary || $value instanceof PdfIndirectObject)) {
			$value = $this->withImportDefaultSpaces($value);
		}

		// A reference whose generation is not the object's current one is to an object that no longer exists
		if ($value instanceof PdfIndirectObjectReference && $this->isStale($value)) {
			$value = new PdfNull();
		}

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

		// Encrypted into a new object: an imported link is written from the same parsed one each time its page is used
		if ($value instanceof PdfString) {
			$string = PdfString::unescape($value->value);
			$string = $this->protection->encrypt($string);
			$value = PdfString::create($this->writer->escape($string));

		} elseif ($value instanceof PdfHexString) {
			$filter = new AsciiHex();
			$string = $filter->decode($value->value);
			$string = $this->protection->encrypt($string);
			$value = PdfHexString::create($filter->encode($string, true));

		} elseif ($value instanceof PdfStream) {
			$stream = $value->getStream();
			$stream = $this->protection->encrypt($stream);
			$dictionary = $value->value;
			$dictionary->value['Length'] = PdfNumeric::create(\strlen($stream));
			$value = PdfStream::create($dictionary, $stream);

		} elseif ($value instanceof PdfIndirectObject) {
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
