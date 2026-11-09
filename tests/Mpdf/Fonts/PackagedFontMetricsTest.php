<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Fonts\Table\GsubOutputs;
use Mpdf\TTFontFile;

/**
 * The tables of every packaged font agree with its glyph count. The OpenType Sanitizer in Chromium and
 * Firefox rejects a whole font for metrics that don't, and stricter builds of it for substitutions that don't.
 */
class PackagedFontMetricsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each font's horizontal and vertical metrics fit its glyph count.
	 *
	 * @dataProvider packagedFontProvider
	 */
	public function testMetricsTablesFitTheGlyphCount($path)
	{
		$font = $this->openFont($path);
		$reader = new FileReader($path);
		$glyphs = $reader->uint16At($font->getTablePosition('maxp')[0] + 4);

		$this->assertMetricsFit($font, $reader, 'hhea', 'hmtx', $glyphs);

		if ($font->hasTable('vhea')) {
			$this->assertMetricsFit($font, $reader, 'vhea', 'vmtx', $glyphs);
		}

		$reader->close();
	}

	/**
	 * Every glyph a font's GSUB can output is one the font has.
	 *
	 * @dataProvider packagedFontProvider
	 */
	public function testSubstitutionsOutputGlyphsTheFontHas($path)
	{
		$font = $this->openFont($path);
		$reader = new FileReader($path);
		$glyphs = $reader->uint16At($font->getTablePosition('maxp')[0] + 4);

		$lookups = new \ReflectionMethod(TTFontFile::class, 'gsubLookups');
		if (PHP_VERSION_ID < 80100) {
			$lookups->setAccessible(true);
		}

		$outputs = array_keys(GsubOutputs::glyphs($reader, $lookups->invoke($font)));
		$reader->close();

		$this->assertLessThan($glyphs, max(array_merge([0], $outputs)), 'GSUB outputs a glyph the font does not have');
	}

	/**
	 * Every font file in the font packages, keyed by its path below packages/.
	 *
	 * @return array
	 */
	public static function packagedFontProvider()
	{
		$packages = dirname(dirname(dirname(__DIR__))) . '/packages/';
		$fonts = [];

		foreach (glob($packages . '*/fonts/*.[ot]tf') as $path) {
			$fonts[substr($path, strlen($packages))] = [$path];
		}

		return $fonts;
	}

	/**
	 * Opens a font and reads its table directory.
	 *
	 * @param string $path The font file
	 *
	 * @return TTFontFile
	 */
	private function openFont($path)
	{
		$font = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$font->open($path)->skip(4);
		$font->readTableDirectory();

		return $font;
	}

	/**
	 * Checks a header's count of full metrics against the glyph count, and that the table holds them.
	 *
	 * @param TTFontFile $font The font, its table directory read
	 * @param FileReader $reader The font file
	 * @param string $header The tag of the header table, hhea or vhea
	 * @param string $metrics The tag of the metrics table, hmtx or vmtx
	 * @param int $glyphs The font's glyph count
	 */
	private function assertMetricsFit(TTFontFile $font, FileReader $reader, $header, $metrics, $glyphs)
	{
		$count = $reader->uint16At($font->getTablePosition($header)[0] + 34);

		$this->assertLessThanOrEqual($glyphs, $count, $header . ' claims more metrics than there are glyphs');
		$this->assertTrue($font->hasTable($metrics), $header . ' has no ' . $metrics);
		$this->assertGreaterThanOrEqual(
			$count * 4 + ($glyphs - $count) * 2,
			$font->getTablePosition($metrics)[1],
			$metrics . ' is too short for the glyphs'
		);
	}
}
