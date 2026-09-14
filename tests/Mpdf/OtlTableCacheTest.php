<?php

namespace Mpdf;

/**
 * Shaping a word means offering every glyph of it to every subtable of every Lookup the script asks
 * for, and each subtable that takes the offer reads its Coverage and ClassDef tables and tests the
 * glyphs around the one offered against them. The tables themselves are the same every time, so what
 * they are reduced to is memoised per table for the life of the document - building it per rule is
 * what made a font whose rules run into the thousands take minutes over a word.
 *
 * The cache is keyed by font and table ("lateef/GSUB"), then by what was read from it, then by the
 * offset in the font the entry was read at - except the skip sets, which are keyed by the Lookup flag
 * that asks for them.
 */
class OtlTableCacheTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The class-based contextual formats test a position against "everything not in another class",
	 * which is every glyph of every class above 0 - so the reduction is of the whole table, and is kept
	 * beside the classes it was built from.
	 */
	public function testAClassDefinitionTableKeepsTheClassZeroExclusionsBuiltFromIt()
	{
		$cache = $this->shape('freeserif', 'यह एक परीक्षण वाक्य है');

		$classes = $cache['freeserif/GSUB']['classes'];
		$excluded = $cache['freeserif/GSUB']['class0excl'];

		$this->assertNotSame([], $excluded);
		$this->assertSame([], array_diff(array_keys($excluded), array_keys($classes)));

		foreach ($excluded as $offset => $glyphs) {
			$this->assertSame(array_fill_keys(array_keys($glyphs), 1), $glyphs, 'offset ' . $offset);
		}
	}

	/**
	 * The coverage-based formats test a position against a whole Coverage table, so the table is kept
	 * as a set the test can look a codepoint up in.
	 */
	public function testACoverageTableIsKeptAsASetKeyedByCodepoint()
	{
		$cache = $this->shape('padaukbook', 'ဤသည်မှာ မြန်မာဘာသာဖြင့်');

		$covered = $cache['padaukbook/GSUB']['coverageUni'];

		$this->assertNotSame([], $covered);

		foreach ($covered as $offset => $glyphs) {
			$this->assertSame(array_fill_keys(array_keys($glyphs), 1), $glyphs, 'offset ' . $offset);
			$this->assertSame(array_keys($glyphs), array_filter(array_keys($glyphs), 'is_int'), 'offset ' . $offset);
		}
	}

	/**
	 * Every position a rule tests is first walked past the characters the Lookup's flag says to skip,
	 * so that set is read more often than anything else. It is a property of the flag and of GDEF, and
	 * is kept per flag rather than per subtable applied.
	 */
	public function testTheCharactersALookupFlagSkipsAreKeptPerFlag()
	{
		$cache = $this->shape('lateef', 'السلام علیکم ورحمۃ اللہ وبرکاتہ');

		$skipped = $cache['lateef/GSUB']['ignore'];

		$this->assertNotSame([], $skipped);

		foreach ($skipped as $flag => $glyphs) {
			$this->assertMatchesRegularExpression('/^\d+:\S*$/', (string) $flag);
			$this->assertSame(array_fill_keys(array_keys($glyphs), 1), $glyphs, 'flag ' . $flag);
		}
	}

	/**
	 * @return array Otl's memoised tables after the string has been shaped
	 */
	private function shape($font, $text)
	{
		$mpdf = new Mpdf();
		$mpdf->WriteHTML('<p style="font-family:' . $font . '">' . $text . '</p>');

		$otl = new \ReflectionProperty('Mpdf\Mpdf', 'otl');
		$otl->setAccessible(true);

		return $otl->getValue($mpdf)->LuDataCache;
	}

}
