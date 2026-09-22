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
