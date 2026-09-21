<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Fonts\Color\ColorFormats;
use Mpdf\Mpdf;
use Mpdf\TTFontFile;

/**
 * Which colour formats a font carries, as the parser finds them from its tables, and what reading one
 * to draw in colour changes about it
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
	 * @param string $name A font in tests/data/ttf/color
	 *
	 * @return TTFontFile The font read
	 */
	private function read($name)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics(__DIR__ . '/../../data/ttf/color/' . $name, uniqid('', true), 0, false, false, 0xFF);

		return $ttf;
	}

	/**
	 * A font is drawn in the first format mPDF draws that it carries, and in none where the document may
	 * not use colour
	 */
	public function testAFontIsDrawnInTheFirstFormatItCarriesThatMpdfDraws()
	{
		$this->assertSame('CBDT', ColorFormats::choose(['COLRv1', 'CBDT', 'sbix'], true));
		$this->assertSame('', ColorFormats::choose(['SVG'], true), 'a format mPDF does not draw yet is not chosen');
		$this->assertSame('COLRv0', ColorFormats::choose(['COLRv1', 'COLRv0', 'CBDT'], true), 'a COLR version 1 font is drawn from its version 0 records, ahead of its bitmaps');
		$this->assertSame('', ColorFormats::choose(['CBDT'], false), 'nor anything, where colour is off');
	}

	/**
	 * A font in a format mPDF draws is written as Type3 fonts, where the fixture's layer glyphs, from 27
	 * on, are drawn only inside the emoji that use them. A font mPDF draws as TrueType hands every glyph
	 * a code, as it always has: Noto Emoji's family ligature, glyph 1483, is one no character maps to.
	 */
	public function testAFontWrittenAsType3GivesCodesOnlyToTheGlyphsTextCanReach()
	{
		$type3 = $this->read('TestEmoji-COLRv0.ttf');

		$this->assertTrue(isset($type3->glyphToChar[26]), 'a ligature is reached through GSUB');
		$this->assertTrue(isset($type3->glyphToChar[9]), 'a tag is reached through the tags');
		$this->assertFalse(isset($type3->glyphToChar[27]), 'a layer is reached by neither');

		$trueType = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$trueType->getMetrics(__DIR__ . '/../../../packages/Emoji/fonts/NotoEmoji-Regular.ttf', uniqid('', true), 0, false, false, 0xFF);
		$this->assertTrue(isset($trueType->glyphToChar[1483]));
	}

	/**
	 * A font of bitmaps can still have a glyf table of empty glyphs, as the sbix fixture does
	 */
	public function testAFontHasOutlinesOnlyWhereAGlyphHasOne()
	{
		$this->assertTrue($this->read('TestEmoji-COLRv0.ttf')->hasOutlines);
		$this->assertFalse($this->read('TestEmoji-sbix.ttf')->hasOutlines);
		$this->assertFalse($this->read('TestEmoji-CBDT.ttf')->hasOutlines);
	}

	/**
	 * A glyph is drawn by the font's colour format, then by its outline, and by its outline alone
	 * where colour is off
	 */
	public function testAFontIsDrawnByItsColourFormatThenItsOutlines()
	{
		$mpdf = new Mpdf(['mode' => 'utf-8']);
		$colr = ['colorFormats' => ['COLRv1', 'COLRv0'], 'hasOutlines' => true];

		$this->assertSame(['Mpdf\Fonts\Color\ColrV0Source', 'Mpdf\Fonts\Color\OutlineSource'], ColorFormats::sources($colr, $mpdf));
		$this->assertSame(['Mpdf\Fonts\Color\CbdtSource'], ColorFormats::sources(['colorFormats' => ['CBDT'], 'hasOutlines' => false], $mpdf));

		$mpdf->PDFA = true;
		$this->assertSame(['Mpdf\Fonts\Color\OutlineSource'], ColorFormats::sources($colr, $mpdf));
	}

	/**
	 * A font draws nothing where colour is off only if it has no outlines to draw from instead
	 */
	public function testOnlyAColourFontWithoutOutlinesIsBlankWhereColourIsOff()
	{
		$mpdf = new Mpdf(['mode' => 'utf-8', 'restrictColorSpace' => 1]);

		$this->assertTrue(ColorFormats::blank(['colorFormats' => ['CBDT'], 'hasOutlines' => false], $mpdf));
		$this->assertFalse(ColorFormats::blank(['colorFormats' => ['COLRv0'], 'hasOutlines' => true], $mpdf));
		$this->assertFalse(ColorFormats::blank(['colorFormats' => ['SVG'], 'hasOutlines' => false], $mpdf), 'a format mPDF does not draw is not written as Type3');

		$mpdf->restrictColorSpace = 0;
		$this->assertFalse(ColorFormats::blank(['colorFormats' => ['CBDT'], 'hasOutlines' => false], $mpdf), 'nor with colour on');
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
