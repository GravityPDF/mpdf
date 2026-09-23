<?php

namespace Mpdf\Import;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\ReaderInterface;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * One cross-reference stream (PDF 1.5): where each object it lists is, as a byte offset in the file or as a place
 * in an object stream
 *
 * Part of mPDF, written from the PDF specification (ISO 32000-1, 7.5.8).
 */
class CrossReferenceStreamReader implements ReaderInterface
{

	/**
	 * @var \setasign\Fpdi\PdfParser\Type\PdfDictionary
	 */
	private $trailer;

	/**
	 * @var int[] The byte offset of each object written in the file, by object number
	 */
	private $offsets = [];

	/**
	 * @var int[] The object stream each compressed object is in, by object number
	 */
	private $streams = [];

	/**
	 * @var int[] Where in its object stream each compressed object is, by object number
	 */
	private $indexes = [];

	/**
	 * @var bool[] The objects this cross-reference frees or lists with a type no reader knows, by object number
	 */
	private $free = [];

	/**
	 * @var int[] The generation of each object written in the file whose generation is not 0, by object number
	 */
	private $generations = [];

	/**
	 * @param \setasign\Fpdi\PdfParser\Type\PdfStream $stream The cross-reference stream, its dictionary the trailer
	 *
	 * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException When /W or /Size is missing or wrong
	 */
	public function __construct(PdfStream $stream)
	{
		$this->trailer = $stream->value;

		$w = PdfDictionary::get($this->trailer, 'W');
		$widths = [];
		foreach ($w instanceof PdfArray ? $w->value : [] as $width) {
			$widths[] = $width instanceof PdfNumeric ? (int) $width->value : -1;
		}

		if (count($widths) !== 3 || min($widths) < 0 || array_sum($widths) === 0) {
			throw new CrossReferenceException('The cross-reference stream has no usable /W.', CrossReferenceException::INVALID_DATA);
		}

		$index = PdfDictionary::get($this->trailer, 'Index');
		$size = PdfDictionary::get($this->trailer, 'Size');
		if ($index instanceof PdfArray) {
			$index = array_map(function ($value) {
				return (int) $value->value;
			}, $index->value);
		} elseif ($size instanceof PdfNumeric) {
			$index = [0, (int) $size->value];
		} else {
			throw new CrossReferenceException('The cross-reference stream has no /Size.', CrossReferenceException::INVALID_DATA);
		}

		$this->read(PredictorDecoder::unfilteredStream($stream), $widths, $index);
	}

	/**
	 * @param int $objectNumber
	 *
	 * @return int|int[]|false A byte offset, [object stream number, index in it], or false when it is not listed
	 */
	public function getOffsetFor($objectNumber)
	{
		if (isset($this->offsets[$objectNumber])) {
			return $this->offsets[$objectNumber];
		}

		return isset($this->streams[$objectNumber]) ? [$this->streams[$objectNumber], $this->indexes[$objectNumber]] : false;
	}

	/**
	 * Whether the object is free here, or listed with a type other than 0, 1 or 2, which is a reference to the null
	 * object (ISO 32000-1, 7.5.8.3). Either way an older section's entry for it no longer counts (7.5.6).
	 *
	 * @param int $objectNumber
	 *
	 * @return bool
	 */
	public function isFree($objectNumber)
	{
		return isset($this->free[$objectNumber]);
	}

	/**
	 * The generation this cross-reference gives an object: 0 for an object in an object stream (ISO 32000-1, 7.5.7)
	 *
	 * @param int $objectNumber
	 *
	 * @return int|null Null when it does not list the object
	 */
	public function getGenerationFor($objectNumber)
	{
		if (isset($this->offsets[$objectNumber])) {
			return isset($this->generations[$objectNumber]) ? $this->generations[$objectNumber] : 0;
		}

		return isset($this->streams[$objectNumber]) ? 0 : null;
	}

	/**
	 * @return \setasign\Fpdi\PdfParser\Type\PdfDictionary
	 */
	public function getTrailer()
	{
		return $this->trailer;
	}

	/**
	 * Read the rows of each subsection /Index names. A type field of width 0 stands for type 1.
	 *
	 * @param string $data
	 * @param int[]  $widths
	 * @param int[]  $index  First object number and count of each subsection
	 */
	private function read($data, array $widths, array $index)
	{
		$rowLength = array_sum($widths);
		$position = 0;
		$length = strlen($data);

		for ($i = 0, $subsections = count($index) - 1; $i < $subsections; $i += 2) {
			$number = $index[$i];

			for ($j = 0; $j < $index[$i + 1] && $position + $rowLength <= $length; $j++, $number++) {
				$type = $widths[0] === 0 ? 1 : $this->field($data, $position, $widths[0]);
				$second = $this->field($data, $position + $widths[0], $widths[1]);
				$third = $this->field($data, $position + $widths[0] + $widths[1], $widths[2]);
				$position += $rowLength;

				if ($type === 1) {
					$this->offsets[$number] = $second;
					if ($third !== 0) {
						$this->generations[$number] = $third;
					}
				} elseif ($type === 2) {
					$this->streams[$number] = $second;
					$this->indexes[$number] = $third;
				} else {
					$this->free[$number] = true;
				}
			}
		}
	}

	/**
	 * A big-endian number of $width bytes
	 *
	 * @param string $data
	 * @param int    $position
	 * @param int    $width
	 *
	 * @return int
	 */
	private function field($data, $position, $width)
	{
		$value = 0;
		for ($k = 0; $k < $width; $k++) {
			$value = $value * 256 + ord($data[$position + $k]);
		}

		return $value;
	}

}
