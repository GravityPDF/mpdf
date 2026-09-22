<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\TTFontFile;

/**
 * Fonts of nothing but the tables a colour source reads, written to temporary files and opened, for
 * what a fixture font cannot show. A test using it calls closeFonts() from tear_down().
 */
trait SyntheticFonts
{

	/**
	 * @var \Mpdf\Fonts\FileReader[] The fonts openFont() opened
	 */
	private $opened = [];

	/**
	 * @var string[] The fonts openFont() wrote
	 */
	private $written = [];

	/**
	 * @param string[] $tables Tag => the table, in the order they are written
	 * @param int      $cut    Bytes to leave off the end of the font, which the table directory still
	 *                         counts
	 *
	 * @return string A font: the offset table, the table directory and the tables
	 */
	private function sfnt(array $tables, $cut = 0)
	{
		$directory = '';
		$body = '';
		$offset = 12 + 16 * count($tables);
		foreach ($tables as $tag => $table) {
			$directory .= $tag . pack('N3', 0, $offset + strlen($body), strlen($table));
			$body .= $table;
		}
		$font = pack('Nn4', 0x00010000, count($tables), 0, 0, 0) . $directory . $body;

		return substr($font, 0, strlen($font) - $cut);
	}

	/**
	 * @param string[] $glyphs Each glyph's glyf data, by glyph id
	 *
	 * @return string[] head, maxp, loca and glyf for those glyphs, loca in its long format, the font's
	 *                  bounding box a square of 1000 units
	 */
	private function glyphTables(array $glyphs)
	{
		$loca = pack('N', 0);
		$glyf = '';
		foreach ($glyphs as $glyph) {
			$glyf .= $glyph;
			$loca .= pack('N', strlen($glyf));
		}

		// unitsPerEm at 18, the bounding box from 0,0 to 1000,1000 at 36, indexToLocFormat at 50
		$head = str_repeat("\0", 18) . pack('n', 1000) . str_repeat("\0", 16) . pack('n4', 0, 0, 1000, 1000) . str_repeat("\0", 6) . pack('n2', 1, 0);

		return ['head' => $head, 'maxp' => pack('Nn', 0x00005000, count($glyphs)), 'loca' => $loca, 'glyf' => $glyf];
	}

	/**
	 * @return string A simple glyph of one contour: the triangle 0,0 500,700 1000,0, each point on the
	 *                curve and each coordinate a two-byte delta
	 */
	private function triangle()
	{
		return pack('n5', 1, 0, 0, 1000, 700) . pack('n2', 2, 0) . "\1\1\1" . pack('n3', 0, 500, 500) . pack('n3', 0, 700, 0x10000 - 700);
	}

	/**
	 * @return string A simple glyph of two contours, each the triangle, one over the other, laid out as
	 *                triangle() is
	 */
	private function twoTriangles()
	{
		return pack('n5', 2, 0, 0, 1000, 700) . pack('n3', 2, 5, 0) . str_repeat("\1", 6)
			. pack('n6', 0, 500, 500, 0x10000 - 1000, 500, 500) . pack('n6', 0, 700, 0x10000 - 700, 0, 700, 0x10000 - 700);
	}

	/**
	 * @return string A CPAL table of one palette of one colour, opaque red
	 */
	private function cpal()
	{
		// version, numPaletteEntries, numPalettes, numColorRecords, colorRecordsArrayOffset, then the
		// palette's first colour record and the colour, blue, green, red, alpha
		return pack('n4Nn', 0, 1, 1, 1, 14, 0) . "\0\0\xFF\xFF";
	}

	/**
	 * Writes a font and opens it
	 *
	 * @param string $font The font's bytes
	 *
	 * @return array [the TTFontFile, its table directory read, and the open file]
	 */
	private function openFont($font)
	{
		$file = tempnam(sys_get_temp_dir(), 'mpdf-color-');
		file_put_contents($file, $font);
		$this->written[] = $file;

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->opened[] = $reader = $ttf->openFont($file);

		return [$ttf, $reader];
	}

	/**
	 * Closes and removes every font openFont() wrote
	 */
	private function closeFonts()
	{
		foreach ($this->opened as $reader) {
			$reader->close();
		}
		foreach ($this->written as $file) {
			unlink($file);
		}

		$this->opened = [];
		$this->written = [];
	}
}
