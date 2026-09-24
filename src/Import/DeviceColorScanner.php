<?php

namespace Mpdf\Import;

use setasign\Fpdi\PdfParser\PdfParser as FpdiParser; // Mpdf\Import\PdfParser takes the plain name here
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfToken;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * Finds the device colour spaces an imported page paints in: those its content streams set by operator
 * or by name, and those of the images, shadings, patterns and forms it draws, followed as deep as they
 * nest (ISO 32000-1, 8.6.4 and 8.6.6). PDF/X permits only some device colour spaces, by output intent,
 * and mPDF cannot rewrite what it imports, only name a default colour space for it (8.6.5.6).
 *
 * Part of mPDF, written from the PDF specification.
 */
class DeviceColorScanner
{

	/**
	 * How deep forms and patterns drawn inside one another are followed
	 */
	const MAX_DEPTH = 16;

	/**
	 * @var FpdiParser The parser of the document the page was imported from
	 */
	private $parser;

	/**
	 * @var bool[] The device colour spaces found, 'RGB', 'Gray' or 'CMYK', as keys
	 */
	private $used = [];

	/**
	 * @var bool[] The numbers of the objects that are resource dictionaries, as keys
	 */
	private $resourceObjects = [];

	/**
	 * @var bool[] The numbers of the form, pattern, image and colour space objects already scanned, as keys
	 */
	private $scanned = [];

	/**
	 * @param FpdiParser $parser The parser of the document the page was imported from
	 */
	public function __construct(FpdiParser $parser)
	{
		$this->parser = $parser;
	}

	/**
	 * @param PdfStream $form An imported page, as the form XObject it is written as
	 */
	public function scan(PdfStream $form)
	{
		$this->form($form, 0);
	}

	/**
	 * @return string[] The device colour spaces what was scanned paints in: 'RGB', 'Gray' or 'CMYK'
	 */
	public function used()
	{
		return array_keys($this->used);
	}

	/**
	 * @return bool[] The numbers of the objects, in the imported document, that are resource dictionaries of
	 *                what was scanned, which a default colour space is named in, as keys
	 */
	public function resourceObjects()
	{
		return $this->resourceObjects;
	}

	/**
	 * A form XObject or a tiling pattern: its transparency group's colour space, and its content
	 *
	 * @param PdfStream $stream
	 * @param int       $depth
	 */
	private function form(PdfStream $stream, $depth)
	{
		if ($depth > self::MAX_DEPTH) {
			return;
		}

		$dictionary = $stream->value;

		$resources = PdfDictionary::get($dictionary, 'Resources');
		if ($resources instanceof PdfIndirectObjectReference) {
			$this->resourceObjects[$resources->value] = true;
		}
		$resources = $this->resolve($resources);
		if (!$resources instanceof PdfDictionary) {
			$resources = new PdfDictionary();
		}

		$group = $this->resolve(PdfDictionary::get($dictionary, 'Group'));
		if ($group instanceof PdfDictionary && isset($group->value['CS'])) {
			$this->colorSpace($group->value['CS'], $resources, $depth);
		}

		try {
			$content = $stream->getUnfilteredStream();
		} catch (\Exception $e) {
			return; // a filter mPDF cannot decode: nothing in the content can be found
		}

		$this->content($content, $resources, $depth);
	}

	/**
	 * @param string        $content   A content stream, unfiltered
	 * @param PdfDictionary $resources The resources it draws from
	 * @param int           $depth
	 */
	private function content($content, PdfDictionary $resources, $depth)
	{
		$parser = new FpdiParser(StreamReader::createByString($content));
		$operands = [];

		while (true) {
			try {
				$value = $parser->readValue();
			} catch (\Exception $e) {
				return; // content that does not parse: what came before it has been read
			}

			if ($value === false) {
				return;
			}

			if (!$value instanceof PdfToken) {
				$operands[] = $value;
				continue;
			}

			$operand = end($operands);
			$name = $operand instanceof PdfName ? $operand->value : null;
			$operands = [];

			switch ($value->value) {
				case 'rg':
				case 'RG':
					$this->used['RGB'] = true;
					break;

				case 'g':
				case 'G':
					$this->used['Gray'] = true;
					break;

				case 'k':
				case 'K':
					$this->used['CMYK'] = true;
					break;

				case 'cs':
				case 'CS':
					if ($name !== null) {
						$this->colorSpace(PdfName::create($name), $resources, $depth);
					}
					break;

				case 'scn':
				case 'SCN':
					if ($name !== null) {
						$this->pattern($this->resource($resources, 'Pattern', $name), $depth);
					}
					break;

				case 'sh':
					if ($name !== null) {
						$this->shading($this->resource($resources, 'Shading', $name), $resources, $depth);
					}
					break;

				case 'Do':
					if ($name !== null) {
						$this->xObject($this->resource($resources, 'XObject', $name), $resources, $depth);
					}
					break;

				case 'BI':
					if (!$this->inlineImage($parser, $content, $resources, $depth)) {
						return;
					}
					break;
			}
		}
	}

	/**
	 * Reads an inline image's dictionary for its colour space, and steps past its data to its EI
	 *
	 * @param FpdiParser    $parser    Reading the content stream, just past the BI operator
	 * @param string        $content   The content stream
	 * @param PdfDictionary $resources
	 * @param int           $depth
	 *
	 * @return bool Whether the end of the image was found, so that the content can be read on
	 */
	private function inlineImage(FpdiParser $parser, $content, PdfDictionary $resources, $depth)
	{
		$entries = [];
		while (true) {
			try {
				$value = $parser->readValue();
			} catch (\Exception $e) {
				return false;
			}

			if ($value === false) {
				return false;
			}

			if ($value instanceof PdfToken && $value->value === 'ID') {
				break;
			}

			$entries[] = $value;
		}

		$imageMask = false;
		for ($i = 0; $i + 1 < count($entries); $i += 2) {
			$key = $entries[$i] instanceof PdfName ? $entries[$i]->value : '';
			if ($key === 'IM' || $key === 'ImageMask') {
				$imageMask = $entries[$i + 1]->value === true;
			}
			if (($key === 'CS' || $key === 'ColorSpace') && !$imageMask) {
				$this->colorSpace($entries[$i + 1], $resources, $depth);
			}
		}

		// The data runs to an EI operator standing alone between white space (8.9.7)
		$reader = $parser->getStreamReader();
		$start = $reader->getOffset() + $reader->getPosition();
		if (!preg_match('/\sEI(?=\s|$)/', $content, $match, PREG_OFFSET_CAPTURE, $start)) {
			return false;
		}

		$reader->reset($match[0][1] + strlen($match[0][0]));
		$parser->getTokenizer()->clearStack();

		return true;
	}

	/**
	 * @param PdfType|null  $xObject
	 * @param PdfDictionary $resources The resources the XObject is drawn from
	 * @param int           $depth
	 */
	private function xObject($xObject, PdfDictionary $resources, $depth)
	{
		if ($this->alreadyScanned($xObject)) {
			return;
		}

		$xObject = $this->resolve($xObject);
		if (!$xObject instanceof PdfStream) {
			return;
		}

		$subtype = PdfDictionary::get($xObject->value, 'Subtype');

		if ($subtype instanceof PdfName && $subtype->value === 'Image') {
			// A mask paints in no colour space of its own, and a soft mask's DeviceGray is the one PDF/X permits
			$mask = $this->resolve(PdfDictionary::get($xObject->value, 'ImageMask'));
			if (!($mask !== null && $mask->value === true) && isset($xObject->value->value['ColorSpace'])) {
				$this->colorSpace($xObject->value->value['ColorSpace'], $resources, $depth);
			}

			return;
		}

		if ($subtype instanceof PdfName && $subtype->value === 'Form') {
			$this->form($xObject, $depth + 1);
		}
	}

	/**
	 * @param PdfType|null $pattern
	 * @param int          $depth
	 */
	private function pattern($pattern, $depth)
	{
		if ($this->alreadyScanned($pattern)) {
			return;
		}

		$pattern = $this->resolve($pattern);

		if ($pattern instanceof PdfStream) {
			$this->form($pattern, $depth + 1); // a tiling pattern, whose cell is drawn as a form is

			return;
		}

		if ($pattern instanceof PdfDictionary && isset($pattern->value['Shading'])) {
			$this->shading($pattern->value['Shading'], new PdfDictionary(), $depth);
		}
	}

	/**
	 * @param PdfType|null  $shading
	 * @param PdfDictionary $resources
	 * @param int           $depth
	 */
	private function shading($shading, PdfDictionary $resources, $depth)
	{
		$shading = $this->resolve($shading);
		if ($shading instanceof PdfStream) {
			$shading = $shading->value;
		}

		if ($shading instanceof PdfDictionary && isset($shading->value['ColorSpace'])) {
			$this->colorSpace($shading->value['ColorSpace'], $resources, $depth);
		}
	}

	/**
	 * @param PdfType       $space     A colour space by name or as an array, or a reference to one
	 * @param PdfDictionary $resources Where a name that is not a colour space family is looked up
	 * @param int           $depth
	 */
	private function colorSpace(PdfType $space, PdfDictionary $resources, $depth)
	{
		if ($depth > self::MAX_DEPTH) {
			return;
		}

		$space = $this->resolve($space);

		if ($space instanceof PdfName) {
			$families = ['DeviceRGB' => 'RGB', 'RGB' => 'RGB', 'DeviceGray' => 'Gray', 'G' => 'Gray', 'DeviceCMYK' => 'CMYK', 'CMYK' => 'CMYK'];
			if (isset($families[$space->value])) {
				$this->used[$families[$space->value]] = true;
			} elseif ($space->value !== 'Pattern') {
				$named = $this->resource($resources, 'ColorSpace', $space->value);
				if ($named !== null && !$this->alreadyScanned($named)) {
					$this->colorSpace($named, $resources, $depth + 1);
				}
			}

			return;
		}

		if (!$space instanceof PdfArray || !isset($space->value[0])) {
			return;
		}

		$family = $this->resolve($space->value[0]);
		$family = $family instanceof PdfName ? $family->value : '';

		// The space an indexed or pattern space is based on, and the alternate a separation falls back to
		$within = ['Indexed' => 1, 'I' => 1, 'Pattern' => 1, 'Separation' => 2, 'DeviceN' => 2];
		if (isset($within[$family], $space->value[$within[$family]])) {
			$this->colorSpace($space->value[$within[$family]], $resources, $depth + 1);
		}
	}

	/**
	 * @param PdfDictionary $resources
	 * @param string        $category  e.g. 'XObject' or 'ColorSpace'
	 * @param string        $name
	 *
	 * @return PdfType|null The resource, unresolved
	 */
	private function resource(PdfDictionary $resources, $category, $name)
	{
		$entries = $this->resolve(PdfDictionary::get($resources, $category));

		return $entries instanceof PdfDictionary && isset($entries->value[$name]) ? $entries->value[$name] : null;
	}

	/**
	 * Marks an object as scanned, so that one drawn many times, or drawing itself, is read once
	 *
	 * @param PdfType|null $value A form, pattern, image or colour space, by reference or direct
	 *
	 * @return bool Whether it was scanned already; a direct object never was, as it has no number to know it by
	 */
	private function alreadyScanned($value)
	{
		if (!$value instanceof PdfIndirectObjectReference) {
			return false;
		}

		if (isset($this->scanned[$value->value])) {
			return true;
		}

		$this->scanned[$value->value] = true;

		return false;
	}

	/**
	 * @param PdfType|null $value
	 *
	 * @return PdfType|null The value, followed through any reference, or null where it cannot be
	 */
	private function resolve($value)
	{
		if ($value === null) {
			return null;
		}

		try {
			return PdfType::resolve($value, $this->parser);
		} catch (\Exception $e) {
			return null;
		}
	}

}
