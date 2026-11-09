<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * The tables of every bundled font agree with its glyph count. The OpenType Sanitizer in Chromium and
 * Firefox rejects a whole font for metrics that don't, and stricter builds of it for substitutions that don't.
 */
class BundledFontMetricsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var TTFontFile The font under test
	 */
	private $font;

	/**
	 * Closes the font the test opened.
	 */
	protected function tear_down()
	{
		fclose($this->font->fh);

		parent::tear_down();
	}

	/**
	 * Each font's horizontal and vertical metrics fit its glyph count.
	 *
	 * @dataProvider bundledFontProvider
	 */
	public function testMetricsTablesFitTheGlyphCount($path)
	{
		$glyphs = $this->openFont($path);

		$this->assertMetricsFit('hhea', 'hmtx', $glyphs);

		if (isset($this->font->tables['vhea'])) {
			$this->assertMetricsFit('vhea', 'vmtx', $glyphs);
		}
	}

	/**
	 * Every glyph a font's single, multiple, alternate and ligature substitutions output is one the font has.
	 *
	 * @dataProvider bundledFontProvider
	 */
	public function testSubstitutionsOutputGlyphsTheFontHas($path)
	{
		$glyphs = $this->openFont($path);
		$outputs = isset($this->font->tables['GSUB']) ? $this->substitutionOutputs() : [];

		$this->assertLessThan($glyphs, max(array_merge([0], $outputs)), 'GSUB outputs a glyph the font does not have');
	}

	/**
	 * Every font file in ttfonts/, keyed by its name.
	 *
	 * @return array
	 */
	public static function bundledFontProvider()
	{
		$fonts = [];

		foreach (glob(CharacterRanges::SHIPPED_FONT_DIRECTORY . '/*.[ot]tf') as $path) {
			$fonts[basename($path)] = [$path];
		}

		return $fonts;
	}

	/**
	 * Opens a font and reads its table directory.
	 *
	 * @param string $path The font file
	 *
	 * @return int The font's glyph count
	 */
	private function openFont($path)
	{
		$this->font = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$this->font->fh = fopen($path, 'rb');
		$this->font->seek(4);
		$this->font->readTableDirectory();

		return $this->font->get_ushort($this->font->tables['maxp']['offset'] + 4);
	}

	/**
	 * Checks a header's count of full metrics against the glyph count, and that the table holds them.
	 *
	 * @param string $header The tag of the header table, hhea or vhea
	 * @param string $metrics The tag of the metrics table, hmtx or vmtx
	 * @param int $glyphs The font's glyph count
	 */
	private function assertMetricsFit($header, $metrics, $glyphs)
	{
		$tables = $this->font->tables;
		$count = $this->font->get_ushort($tables[$header]['offset'] + 34);

		$this->assertLessThanOrEqual($glyphs, $count, $header . ' claims more metrics than there are glyphs');
		$this->assertArrayHasKey($metrics, $tables, $header . ' has no ' . $metrics);
		$this->assertGreaterThanOrEqual(
			$count * 4 + ($glyphs - $count) * 2,
			$tables[$metrics]['length'],
			$metrics . ' is too short for the glyphs'
		);
	}

	/**
	 * Collects the glyphs the font's GSUB can output.
	 *
	 * @return int[]
	 */
	private function substitutionOutputs()
	{
		$gsub = $this->font->tables['GSUB']['offset'];
		$list = $gsub + $this->font->get_ushort($gsub + 8);
		$outputs = [];

		foreach ($this->readUint16Array($list, $list) as $lookup) {
			foreach ($this->readUint16Array($lookup + 4, $lookup) as $subtable) {
				$type = $this->font->get_ushort($lookup);
				$outputs = array_merge($outputs, $this->subtableOutputs($type, $subtable));
			}
		}

		return $outputs;
	}

	/**
	 * Collects the glyphs one GSUB subtable outputs.
	 *
	 * @param int $type The lookup type
	 * @param int $subtable Where the subtable is in the file
	 *
	 * @return int[]
	 */
	private function subtableOutputs($type, $subtable)
	{
		$font = $this->font;

		if (7 === $type) {
			return $this->subtableOutputs($font->get_ushort($subtable + 2), $subtable + $font->get_ulong($subtable + 4));
		}

		if (1 === $type) {
			return $this->singleSubstitutionOutputs($subtable);
		}

		if ($type < 2 || $type > 4) {
			return [];
		}

		$outputs = [];

		foreach ($this->readUint16Array($subtable + 4, $subtable) as $set) {
			if (4 !== $type) {
				$outputs = array_merge($outputs, $this->readUint16Array($set));
				continue;
			}

			foreach ($this->readUint16Array($set, $set) as $ligature) {
				$outputs[] = $font->get_ushort($ligature);
			}
		}

		return $outputs;
	}

	/**
	 * Collects the glyphs a single substitution subtable outputs.
	 *
	 * @param int $subtable Where the subtable is in the file
	 *
	 * @return int[]
	 */
	private function singleSubstitutionOutputs($subtable)
	{
		if (2 === $this->font->get_ushort($subtable)) {
			return $this->readUint16Array($subtable + 4);
		}

		$delta = $this->font->get_ushort($subtable + 4);
		$outputs = [];

		foreach ($this->coverageGlyphs($subtable + $this->font->get_ushort($subtable + 2)) as $glyph) {
			$outputs[] = ($glyph + $delta) & 0xFFFF;
		}

		return $outputs;
	}

	/**
	 * Reads the glyphs of a Coverage table.
	 *
	 * @param int $coverage Where the table is in the file
	 *
	 * @return int[]
	 */
	private function coverageGlyphs($coverage)
	{
		if (1 === $this->font->get_ushort($coverage)) {
			return $this->readUint16Array($coverage + 2);
		}

		$glyphs = [];
		$ranges = $this->font->get_ushort($coverage + 2);

		for ($i = 0; $i < $ranges; $i++) {
			$record = $coverage + 4 + $i * 6;
			$glyphs = array_merge($glyphs, range($this->font->get_ushort($record), $this->font->get_ushort($record + 2)));
		}

		return $glyphs;
	}

	/**
	 * Reads a counted array of 16-bit values, each added to a base.
	 *
	 * @param int $position Where the count is in the file
	 * @param int $base What each value is added to, for an array of relative offsets
	 *
	 * @return int[]
	 */
	private function readUint16Array($position, $base = 0)
	{
		$values = [];
		$count = $this->font->get_ushort($position);

		for ($i = 0; $i < $count; $i++) {
			$values[] = $base + $this->font->get_ushort($position + 2 + $i * 2);
		}

		return $values;
	}
}
