<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\FileReader;
use Mpdf\Fonts\GlyphOutline;
use Mpdf\TTFontFile;
use Psr\Log\LoggerInterface;

/**
 * An open font, as each ColorGlyphSource drawing it reads it: its tables, its file, the log a glyph
 * that cannot be drawn is reported to, and its outlines and palette, read once for every source that
 * draws with them.
 */
class ColorFontFile
{

	/**
	 * The palette index COLR gives a colour that is the colour of the text
	 */
	const FOREGROUND = 0xFFFF;

	/**
	 * @var FileReader
	 */
	public $reader;

	/**
	 * @var LoggerInterface Told of a glyph the font has that cannot be drawn
	 */
	public $logger;

	/**
	 * @var int
	 */
	public $unitsPerEm;

	/**
	 * @var TTFontFile
	 */
	private $font;

	/**
	 * @var GlyphOutline|null
	 */
	private $outline;

	/**
	 * @var int[][]|null
	 */
	private $palette;

	/**
	 * @param TTFontFile      $font       The font, its table directory read
	 * @param FileReader      $reader     The font file
	 * @param int             $unitsPerEm
	 * @param LoggerInterface $logger     Told of a glyph the font has that cannot be drawn
	 */
	public function __construct(TTFontFile $font, FileReader $reader, $unitsPerEm, LoggerInterface $logger)
	{
		$this->font = $font;
		$this->reader = $reader;
		$this->unitsPerEm = $unitsPerEm;
		$this->logger = $logger;
	}

	/**
	 * @param string $tag
	 *
	 * @return int[] [where the table starts, its length], each 0 where the font has none
	 */
	public function table($tag)
	{
		return $this->font->getTablePosition($tag);
	}

	/**
	 * @return GlyphOutline
	 */
	public function outline()
	{
		if ($this->outline === null) {
			$this->outline = new GlyphOutline($this->font, $this->reader, $this->logger);
		}

		return $this->outline;
	}

	/**
	 * The first palette of CPAL, the one a COLR font is drawn in. Every read is checked for coming up
	 * short - see FontReader::fieldsAt().
	 *
	 * @return int[][] Each colour as [red, green, blue, alpha] from 0 to 255, or none where CPAL has no
	 *                 palette or its first runs past the colour records
	 */
	public function palette()
	{
		if ($this->palette !== null) {
			return $this->palette;
		}

		$this->palette = [];
		$cpal = $this->table('CPAL')[0];

		// numPaletteEntries, numPalettes, numColorRecords, colorRecordsArrayOffset, colorRecordIndices[0]
		$header = $this->reader->fieldsAt($cpal + 2, 12, 'nentries/npalettes/nrecords/Ncolors/nfirst');
		if ($header === null || $header[1] === 0 || $header[4] + $header[0] > $header[2]) {
			return $this->palette;
		}

		list($entries, , , $colors, $first) = $header;

		// Each colour is stored blue, green, red, alpha
		$bgra = $this->reader->fieldsAt($cpal + $colors + $first * 4, $entries * 4, 'C*');
		for ($i = 0; $bgra !== null && $i < 4 * $entries; $i += 4) {
			$this->palette[] = [$bgra[$i + 2], $bgra[$i + 1], $bgra[$i], $bgra[$i + 3]];
		}

		return $this->palette;
	}

	/**
	 * @param int   $index An index into the first palette, or FOREGROUND
	 * @param float $alpha The paint's alpha, from 0 to 1, which the colour's own is multiplied by
	 *
	 * @return array [[red, green, blue] from 0 to 1, or null for the colour of the text, alpha]. An index
	 *               the palette does not hold is the colour of the text too.
	 */
	public function colour($index, $alpha)
	{
		$palette = $this->palette();
		if ($index === self::FOREGROUND || !isset($palette[$index])) {
			return [null, min(1, $alpha)];
		}

		list($red, $green, $blue, $own) = $palette[$index];

		return [[$red / 255, $green / 255, $blue / 255], min(1, $alpha) * $own / 255];
	}
}
