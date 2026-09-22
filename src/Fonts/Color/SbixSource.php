<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\FontReader;
use Mpdf\Log\Context as LogContext;

/**
 * Colour glyphs as bitmaps in sbix: Apple Color Emoji's format.
 *
 * Like CBDT, a font holds its bitmaps at one or more sizes, and the largest is drawn. Each glyph's
 * record is its bitmap's offset from the glyph origin in pixels, a graphic type, and the data:
 *
 *   'png ' and 'jpg '  an image, placed by its bottom left corner
 *   'dupe'             another glyph's record, by glyph id
 *   'flip'             another glyph's record drawn mirrored left to right
 *   'tiff', 'mask'     not drawn: nothing a PDF viewer is sure to read, and nothing a font uses
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/sbix
 */
class SbixSource extends BitmapSource
{

	/**
	 * @var int|null Where the strike drawn starts, or null where the font has none
	 */
	private $strike;

	/**
	 * @var int Where sbix ends, which no record runs past
	 */
	private $sbixEnd;

	/**
	 * @var int
	 */
	private $numGlyphs = 0;

	/**
	 * @param ColorFontFile $file The font, whose logger is told of a record in a graphic type mPDF does
	 *                            not draw
	 */
	public function __construct(ColorFontFile $file)
	{
		parent::__construct($file);

		$maxp = $this->reader->fieldsAt($file->table('maxp')[0] + 4, 2, 'n');
		if ($maxp !== null) {
			$this->numGlyphs = $maxp[0];
		}

		list($sbix, $sbixLength) = $file->table('sbix');
		$this->sbixEnd = $sbix + $sbixLength;

		// The strike offsets end with the table, if not before
		$header = $this->reader->fieldsAt($sbix + 4, 4, 'N');
		$count = $header === null ? 0 : (int) min($header[0], max(0, ($sbixLength - 8) >> 2));
		$offsets = $this->reader->fieldsAt($sbix + 8, 4 * $count, 'N*');

		$ppem = 0;
		foreach ($offsets ?: [] as $offset) {
			$strikePpem = $this->reader->fieldsAt($sbix + $offset, 2, 'n');
			if ($strikePpem !== null && $strikePpem[0] > $ppem) {
				$ppem = $strikePpem[0];
				$this->strike = $sbix + $offset;
			}
		}

		if ($ppem) {
			$this->scale = $file->unitsPerEm / $ppem;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		return $this->drawRecord($glyph, $resources, false, 0);
	}

	/**
	 * @param int            $glyph     The glyph id
	 * @param GlyphResources $resources Where the image drawn is registered
	 * @param bool           $mirror    Whether a 'flip' asked for the glyph drawn mirrored
	 * @param int            $depth     How many 'dupe' and 'flip' records led here, which a font cannot
	 *                                  make a cycle of beyond
	 *
	 * @return string|null The content drawing the glyph, or null where it draws nothing
	 */
	private function drawRecord($glyph, GlyphResources $resources, $mirror, $depth)
	{
		if ($this->strike === null || $glyph >= $this->numGlyphs || $depth > 8) {
			return null;
		}

		// A glyph's record runs up to the next glyph's
		$range = $this->reader->fieldsAt($this->strike + 4 + $glyph * 4, 8, 'N2');
		if ($range === null) {
			return null;
		}

		// An empty record is a glyph the strike has no bitmap for. One running past sbix is not read,
		// since it could be a read of up to 4GB.
		list($start, $end) = $range;
		if ($end - $start <= 8 || $this->strike + $end > $this->sbixEnd) {
			return null;
		}

		$record = $this->reader->fieldsAt($this->strike + $start, $end - $start, 'a2x/a2y/a4type/a*data');
		if ($record === null) {
			return null;
		}

		list($x, $y, $type, $data) = $record;

		switch ($type) {
			case 'png ':
			case 'jpg ':
				$image = $resources->image($data);

				return $image === null ? null : $this->place($image[0], $image[1], $image[2], FontReader::int16($x), FontReader::int16($y), $mirror);

			case 'dupe':
			case 'flip':
				if (strlen($data) < 2) {
					return null;
				}

				// A flip of a flip is the right way round
				return $this->drawRecord(unpack('n', $data)[1], $resources, $type === 'flip' ? !$mirror : $mirror, $depth + 1);
		}

		$this->logger->warning(sprintf('Colour glyph %d is an sbix "%s" bitmap, which mPDF does not draw', $glyph, trim($type)), ['context' => LogContext::FONTS]);

		return null;
	}
}
