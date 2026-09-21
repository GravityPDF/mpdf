<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * Which colour formats a font carries, as the parser finds them from its tables
 */
class ColorFormatsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string[] The fonts patched() wrote
	 */
	private $patched = [];

	/**
	 * Removes the fonts patched() wrote
	 */
	protected function tear_down()
	{
		foreach ($this->patched as $file) {
			unlink($file);
		}

		parent::tear_down();
	}

	/**
	 * The formats are listed in the order a renderer would prefer them, and a font with none lists
	 * none
	 *
	 * @param string   $file     The font
	 * @param string[] $expected Its colour formats
	 *
	 * @dataProvider fonts
	 */
	public function testTheColourFormatsAreReadFromTheTables($file, array $expected)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics($file, uniqid('', true), 0, false, false, 0xFF);

		$this->assertSame($expected, $ttf->colorFormats);
	}

	/**
	 * @return array[] Each font, and the colour formats it carries
	 */
	public function fonts()
	{
		$color = __DIR__ . '/../../data/ttf/color/';

		return [
			'COLR version 0' => [$color . 'TestEmoji-COLRv0.ttf', ['COLRv0']],
			'COLR version 1, with the version 0 records beside its paints' => [$color . 'TestEmoji-COLRv1.ttf', ['COLRv1', 'COLRv0']],
			'CBDT' => [$color . 'TestEmoji-CBDT.ttf', ['CBDT']],
			'sbix' => [$color . 'TestEmoji-sbix.ttf', ['sbix']],
			'a black and white emoji font' => [__DIR__ . '/../../../packages/Emoji/fonts/NotoEmoji-Regular.ttf', []],
		];
	}

	/**
	 * A COLR table of version 1 need not carry version 0 records at all, and one without them is
	 * version 1 alone
	 */
	public function testACOLRv1TableWithoutVersion0RecordsIsOnlyVersion1()
	{
		$file = $this->patched('TestEmoji-COLRv1.ttf', 'COLR', function ($font, $offset) {
			return substr_replace($font, "\x00\x00", $offset + 2, 2);
		});

		$this->assertSame(['COLRv1'], $this->colorFormats($file));
	}

	/**
	 * No fixture carries SVG, so the sbix table of one is renamed to stand in for it: the format is
	 * found by the table's tag alone
	 */
	public function testAnSvgTableIsFound()
	{
		$file = $this->patched('TestEmoji-sbix.ttf', 'sbix', null, 'SVG ');

		$this->assertSame(['SVG'], $this->colorFormats($file));
	}

	/**
	 * Each tag the font maps is given the Private Use code its glyph was read at, so the shaper can
	 * reach it
	 */
	public function testTagsAreGivenTheCodeTheirGlyphWasReadAt()
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics(__DIR__ . '/../../data/ttf/color/TestEmoji-COLRv0.ttf', uniqid('', true), 0, false, false, 0xFF);

		$this->assertSame([0xE0062, 0xE0065, 0xE0067, 0xE006E, 0xE007F], array_keys($ttf->tagChars));
		foreach ($ttf->tagChars as $tag => $char) {
			$this->assertContains([$char], $ttf->glyphToChar, sprintf('U+%X', $tag));
			$this->assertGreaterThanOrEqual(0xE000, $char);
		}
	}

	/**
	 * @param string $file The font
	 *
	 * @return string[] The colour formats the parser finds in it
	 */
	private function colorFormats($file)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics($file, uniqid('', true), 0, false, false, 0xFF);

		return $ttf->colorFormats;
	}

	/**
	 * Writes a copy of a fixture font with one table changed
	 *
	 * @param string        $source The fixture, in tests/data/ttf/color
	 * @param string        $tag    The table to change
	 * @param callable|null $patch  Given the font's bytes and the table's offset, returns the new bytes
	 * @param string|null   $rename A tag to give the table in the table directory
	 *
	 * @return string The copy
	 */
	private function patched($source, $tag, $patch = null, $rename = null)
	{
		$font = file_get_contents(__DIR__ . '/../../data/ttf/color/' . $source);

		$tables = unpack('n', substr($font, 4, 2));
		for ($i = 0; $i < $tables[1]; $i++) {
			$record = 12 + 16 * $i;
			if (substr($font, $record, 4) === $tag) {
				if ($patch !== null) {
					$offset = unpack('N', substr($font, $record + 8, 4));
					$font = call_user_func($patch, $font, $offset[1]);
				}
				if ($rename !== null) {
					$font = substr_replace($font, $rename, $record, 4);
				}
			}
		}

		$file = tempnam(sys_get_temp_dir(), 'mpdf-color-');
		file_put_contents($file, $font);
		$this->patched[] = $file;

		return $file;
	}

}
