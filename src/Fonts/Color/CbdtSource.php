<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Log\Context as LogContext;

/**
 * Colour glyphs as PNG bitmaps, in CBDT with CBLC to index them: Noto Color Emoji's format.
 *
 * A font holds its bitmaps at one or more sizes, each a strike. The largest is drawn, scaled to the
 * text, since a PDF is printed and zoomed well past the size its text is set at.
 *
 * CBLC gives each strike a list of index subtables, each covering a run of glyph ids in one of five
 * layouts, and naming the image format of the glyph data it points into CBDT: 17 and 18 carry their
 * own metrics before the PNG, 19 takes them from the index. Only the subtable headers are read up
 * front; a glyph is looked up in its subtable when it is drawn, and the subtables that list their
 * glyphs are read whole the first time one of them is.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/cblc
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/cbdt
 */
class CbdtSource extends BitmapSource
{

	/**
	 * @var int Where CBDT ends, which no image runs past
	 */
	private $cbdtEnd;

	/**
	 * @var array[] The largest strike's index subtables: first and last glyph, index format, image
	 *              format, where the glyph data starts in CBDT, where the subtable's own data starts
	 *              after its header, and for index formats 2, 4 and 5 what index() read of that data,
	 *              once a glyph of it has been drawn
	 */
	private $subtables = [];

	/**
	 * @param ColorFontFile $file The font, whose logger is told of a bitmap in an image format mPDF does
	 *                            not draw
	 */
	public function __construct(ColorFontFile $file)
	{
		parent::__construct($file);
		$cblc = $file->table('CBLC')[0];
		list($cbdt, $cbdtLength) = $file->table('CBDT');
		$this->cbdtEnd = $cbdt + $cbdtLength;

		$header = $this->reader->fieldsAt($cblc + 4, 4, 'N');
		$numSizes = $header === null ? 0 : $header[0];

		$strike = null;
		for ($i = 0; $i < $numSizes; $i++) {
			// indexSubTableArrayOffset, then numberOfIndexSubTables past indexTablesSize, then ppemY past
			// colorRef, both line metrics, the first and last glyph and ppemX
			$size = $this->reader->fieldsAt($cblc + 8 + $i * 48, 46, 'Narray/x4/Ncount/x33/Cppem');
			if ($size === null) {
				break;
			}

			list($arrayOffset, $subtableCount, $ppem) = $size;
			if ($ppem > 0 && ($strike === null || $ppem > $strike[0])) {
				$strike = [$ppem, $cblc + $arrayOffset, $subtableCount];
			}
		}

		if ($strike === null) {
			return;
		}

		list($ppem, $array, $subtableCount) = $strike;
		$this->scale = $file->unitsPerEm / $ppem;

		for ($i = 0; $i < $subtableCount; $i++) {
			// The array ends with the file, if not before
			$entry = $this->reader->fieldsAt($array + $i * 8, 8, 'nfirst/nlast/Noffset');
			if ($entry === null) {
				break;
			}

			$header = $this->reader->fieldsAt($array + $entry[2], 8, 'nindex/nimage/Ndata');
			if ($header === null) {
				continue;
			}

			$this->subtables[] = [
				'first' => $entry[0],
				'last' => $entry[1],
				'indexFormat' => $header[0],
				'imageFormat' => $header[1],
				'data' => $cbdt + $header[2],
				'position' => $array + $entry[2] + 8,
				'index' => null,
			];
		}
	}

	/**
	 * @inheritdoc
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		$bitmap = $this->locate($glyph);
		if ($bitmap === null) {
			return null;
		}

		list($format, $offset, $metrics) = $bitmap;

		if ($format === 17) {
			// smallGlyphMetrics, its advance skipped, then dataLength
			$header = $this->reader->fieldsAt($offset, 9, 'Cheight/Cwidth/cx/cy/x/Nlength');
		} elseif ($format === 18) {
			// bigGlyphMetrics, its horiAdvance and vertical metrics skipped, then dataLength
			$header = $this->reader->fieldsAt($offset, 12, 'Cheight/Cwidth/cx/cy/x4/Nlength');
		} elseif ($format === 19) {
			// Where the index gives no metrics the font is broken, not in a format mPDF does not draw
			if ($metrics === null) {
				return null;
			}
			$header = $this->reader->fieldsAt($offset, 4, 'Nlength');
		} else {
			$this->logger->warning(sprintf('Colour glyph %d is a CBDT bitmap of image format %d, which mPDF does not draw', $glyph, $format), ['context' => LogContext::FONTS]);

			return null;
		}

		// A length running past CBDT is not read, since it could be a read of up to 4GB
		$dataLength = $header === null ? 0 : array_pop($header);
		if ($dataLength === 0 || $this->reader->tell() + $dataLength > $this->cbdtEnd) {
			return null;
		}

		if ($format !== 19) {
			$metrics = $header;
		}

		$png = $this->reader->fieldsAt($this->reader->tell(), $dataLength, 'a*');
		if ($png === null) {
			return null;
		}

		list($height, $width, $bearingX, $bearingY) = $metrics;

		$image = $resources->image($png[0]);
		if ($image === null) {
			return null;
		}

		return $this->place($image[0], $width, $height, $bearingX, $bearingY - $height);
	}

	/**
	 * Where one glyph's bitmap is, from the index subtable that covers it
	 *
	 * @param int $glyph The glyph id
	 *
	 * @return array|null [image format, absolute offset of its data, the index's metrics or null], or
	 *                    null where the strike has no bitmap for the glyph
	 */
	private function locate($glyph)
	{
		foreach ($this->subtables as $i => $subtable) {
			if ($glyph < $subtable['first'] || $glyph > $subtable['last']) {
				continue;
			}

			$metrics = null;
			$indexFormat = $subtable['indexFormat'];

			if ($indexFormat === 1 || $indexFormat === 3) {
				// An Offset32, or an Offset16, per glyph from first to last and one past it: a glyph's
				// data runs up to the next glyph's
				$size = $indexFormat === 1 ? 4 : 2;
				$range = $this->reader->fieldsAt($subtable['position'] + ($glyph - $subtable['first']) * $size, 2 * $size, $size === 4 ? 'N2' : 'n2');
			} elseif ($indexFormat === 2 || $indexFormat === 4 || $indexFormat === 5) {
				if ($subtable['index'] === null) {
					$subtable['index'] = $this->subtables[$i]['index'] = $this->index($subtable);
				}
				if ($subtable['index'] === false) {
					return null;
				}

				list($metrics, $imageSize, $ranges) = $subtable['index'];
				if ($ranges === null) {
					$position = $glyph - $subtable['first'];
					$range = [$position * $imageSize, ($position + 1) * $imageSize];
				} elseif (isset($ranges[$glyph])) {
					$range = $ranges[$glyph];
				} else {
					return null;
				}
			} else {
				return null;
			}

			return $range !== null && $range[1] > $range[0] ? [$subtable['imageFormat'], $subtable['data'] + $range[0], $metrics] : null;
		}

		return null;
	}

	/**
	 * The data of an index subtable that is the same for every glyph it covers, read once: the image
	 * size and metrics of formats 2 and 5, and the glyphs formats 4 and 5 list, each with where its
	 * data starts and ends
	 *
	 * @param array $subtable One of $this->subtables, of index format 2, 4 or 5
	 *
	 * @return array|false [metrics or null, image size or null, glyph id => [start, end] or null where
	 *                     the glyphs run from first to last], or false where the subtable runs past the
	 *                     end of the file or lists more glyphs than it covers
	 */
	private function index(array $subtable)
	{
		$position = $subtable['position'];

		// A subtable lists no more glyphs than it covers; a count past that is not read, since it
		// would be a read of up to 16GB
		$covered = $subtable['last'] - $subtable['first'] + 1;

		if ($subtable['indexFormat'] === 4) {
			// numGlyphs, then a glyph id and an offset for each and a closing offset for the last to
			// run up to
			$count = $this->reader->fieldsAt($position, 4, 'N');
			$pairs = $count === null || $count[0] > $covered ? null : $this->reader->fieldsAt($position + 4, 4 * ($count[0] + 1), 'n*');
			if ($pairs === null) {
				return false;
			}

			$ranges = [];
			for ($i = 0; $i < $count[0]; $i++) {
				// The first record of a glyph listed twice is the one it is drawn from
				if (!isset($ranges[$pairs[2 * $i]])) {
					$ranges[$pairs[2 * $i]] = [$pairs[2 * $i + 1], $pairs[2 * $i + 3]];
				}
			}

			return [null, null, $ranges];
		}

		// imageSize and bigGlyphMetrics, its horiAdvance and vertical metrics skipped
		$header = $this->reader->fieldsAt($position, 12, 'Nsize/Cheight/Cwidth/cx/cy');
		if ($header === null) {
			return false;
		}

		$imageSize = array_shift($header);
		if ($subtable['indexFormat'] === 2) {
			return [$header, $imageSize, null];
		}

		// numGlyphs, then the glyph ids, whose images follow one another in that order
		$count = $this->reader->fieldsAt($position + 12, 4, 'N');
		$glyphs = $count === null || $count[0] > $covered ? null : $this->reader->fieldsAt($position + 16, 2 * $count[0], 'n*');
		if ($glyphs === null) {
			return false;
		}

		$ranges = [];
		foreach ($glyphs as $i => $glyph) {
			if (!isset($ranges[$glyph])) {
				$ranges[$glyph] = [$i * $imageSize, ($i + 1) * $imageSize];
			}
		}

		return [$header, $imageSize, $ranges];
	}
}
