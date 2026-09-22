<?php

namespace Mpdf\Import;

use Mpdf\Image\PngPixels;
use setasign\Fpdi\PdfParser\Filter\FilterException;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * Undoes the TIFF or PNG predictor a FlateDecode or LZWDecode stream was encoded with, for the cross-reference and
 * object streams ObjectStreamCrossReference reads
 *
 * Part of mPDF, written from the PDF specification (ISO 32000-1, 7.4.4.4), with PNG rows unfiltered by
 * PngPixels::unfilter().
 */
class PredictorDecoder
{

	const TIFF = 2;

	const PNG_FIRST = 10;

	const PNG_LAST = 15;

	/**
	 * @var int
	 */
	private $predictor;

	/**
	 * @var int
	 */
	private $colors;

	/**
	 * @var int
	 */
	private $bitsPerComponent;

	/**
	 * @var int
	 */
	private $columns;

	/**
	 * A stream with its filters undone, including a /Predictor on the last of them
	 *
	 * A predictor on an earlier filter is not read; cross-reference and object streams put theirs on the last.
	 *
	 * @param \setasign\Fpdi\PdfParser\Type\PdfStream $stream
	 *
	 * @return string
	 */
	public static function unfilteredStream(PdfStream $stream)
	{
		$filters = PdfDictionary::get($stream->value, 'Filter');
		$filters = $filters instanceof PdfArray ? $filters->value : [$filters];
		$parameters = PdfDictionary::get($stream->value, 'DecodeParms');
		$parameters = $parameters instanceof PdfArray ? $parameters->value : [$parameters];

		$last = count($filters) - 1;
		$lastParameters = isset($parameters[$last]) ? $parameters[$last] : null;
		$predictor = $lastParameters instanceof PdfDictionary ? PdfDictionary::get($lastParameters, 'Predictor') : null;

		if (!$predictor instanceof PdfNumeric || (int) $predictor->value === 1) {
			return $stream->getUnfilteredStream();
		}

		// Without /Predictor the filters undo the rest, each with the parameters it was given
		$remaining = $lastParameters->value;
		unset($remaining['Predictor']);
		$parameters[$last] = PdfDictionary::create($remaining);

		$dictionary = $stream->value->value;
		$dictionary['DecodeParms'] = PdfArray::create($parameters);
		$data = PdfStream::create(PdfDictionary::create($dictionary), $stream->getStream())->getUnfilteredStream();

		$decoder = new self(
			$predictor->value,
			PdfDictionary::get($lastParameters, 'Colors', PdfNumeric::create(1))->value,
			PdfDictionary::get($lastParameters, 'BitsPerComponent', PdfNumeric::create(8))->value,
			PdfDictionary::get($lastParameters, 'Columns', PdfNumeric::create(1))->value
		);

		return $decoder->decode($data);
	}

	/**
	 * @param int $predictor        /Predictor
	 * @param int $colors           /Colors
	 * @param int $bitsPerComponent /BitsPerComponent
	 * @param int $columns          /Columns
	 */
	public function __construct($predictor, $colors, $bitsPerComponent, $columns)
	{
		$this->predictor = (int) $predictor;
		$this->colors = max(1, (int) $colors);
		$this->bitsPerComponent = (int) $bitsPerComponent;
		$this->columns = max(1, (int) $columns);
	}

	/**
	 * @param string $data
	 *
	 * @return string
	 *
	 * @throws \setasign\Fpdi\PdfParser\Filter\FilterException For a predictor the PDF specification does not define
	 */
	public function decode($data)
	{
		if ($this->predictor === self::TIFF) {
			return $this->tiff($data);
		}

		if ($this->predictor >= self::PNG_FIRST && $this->predictor <= self::PNG_LAST) {
			return $this->png($data);
		}

		throw new FilterException(sprintf('Unknown predictor %d', $this->predictor), FilterException::NOT_IMPLEMENTED);
	}

	/**
	 * TIFF predictor 2: each component is the difference from the same component of the pixel to its left, at any
	 * of the bit depths 7.4.4.4 allows, rows padded to a whole byte
	 *
	 * @param string $data
	 *
	 * @return string
	 *
	 * @throws \setasign\Fpdi\PdfParser\Filter\FilterException For a bit depth other than 1, 2, 4, 8 or 16
	 */
	private function tiff($data)
	{
		$bits = $this->bitsPerComponent;
		if (!in_array($bits, [1, 2, 4, 8, 16], true)) {
			throw new FilterException(sprintf('No TIFF predictor for %d bits per component', $bits), FilterException::NOT_IMPLEMENTED);
		}

		$samples = $this->columns * $this->colors;
		$bytesPerRow = (int) ceil($samples * $bits / 8);
		$decoded = '';

		foreach (str_split($data, $bytesPerRow) as $row) {
			if ($bits === 8) {
				for ($i = $this->colors, $length = strlen($row); $i < $length; $i++) {
					$row[$i] = chr((ord($row[$i]) + ord($row[$i - $this->colors])) & 0xFF);
				}
			} else {
				$last = min($samples, (int) floor(strlen($row) * 8 / $bits));
				for ($i = $this->colors; $i < $last; $i++) {
					$this->setSample($row, $i, $bits, $this->sample($row, $i, $bits) + $this->sample($row, $i - $this->colors, $bits));
				}
			}
			$decoded .= $row;
		}

		return $decoded;
	}

	/**
	 * The sample at index $i of a row of samples $bits wide, most significant first
	 *
	 * @param string $row
	 * @param int    $i
	 * @param int    $bits
	 *
	 * @return int
	 */
	private function sample($row, $i, $bits)
	{
		if ($bits === 16) {
			return ord($row[2 * $i]) << 8 | ord($row[2 * $i + 1]);
		}

		$position = $i * $bits;

		return (ord($row[$position >> 3]) >> (8 - $bits - ($position & 7))) & ((1 << $bits) - 1);
	}

	/**
	 * Write a sample, wrapped to its $bits, at index $i of a row
	 *
	 * @param string $row
	 * @param int    $i
	 * @param int    $bits
	 * @param int    $value
	 */
	private function setSample(&$row, $i, $bits, $value)
	{
		if ($bits === 16) {
			$row[2 * $i] = chr(($value >> 8) & 0xFF);
			$row[2 * $i + 1] = chr($value & 0xFF);

			return;
		}

		$position = $i * $bits;
		$shift = 8 - $bits - ($position & 7);
		$mask = ((1 << $bits) - 1) << $shift;
		$byte = $position >> 3;
		$row[$byte] = chr((ord($row[$byte]) & ~$mask & 0xFF) | (($value << $shift) & $mask));
	}

	/**
	 * PNG predictors: each row starts with the byte naming the filter it was encoded with (RFC 2083, section 6)
	 *
	 * @param string $data
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException For a row whose filter byte PNG does not define
	 */
	private function png($data)
	{
		$bytesPerPixel = max(1, (int) ceil($this->colors * $this->bitsPerComponent / 8));
		$bytesPerRow = (int) ceil($this->columns * $this->colors * $this->bitsPerComponent / 8);

		$row = str_repeat("\0", $bytesPerRow);
		$decoded = '';

		for ($position = 0, $length = strlen($data); $position < $length; $position += $bytesPerRow + 1) {
			$filtered = substr($data, $position + 1, $bytesPerRow);
			if (strlen($filtered) < $bytesPerRow) {
				$filtered = str_pad($filtered, $bytesPerRow, "\0");
			}

			$row = PngPixels::unfilter(ord($data[$position]), $filtered, $row, $bytesPerPixel);
			$decoded .= $row;
		}

		return $decoded;
	}

}
