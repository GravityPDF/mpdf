<?php

namespace Mpdf;

use Mpdf\Fonts\TableReadRecordingBlobReader;

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

	/** A codepoint in Plane 15, which no font here has a glyph for, for marking a set with */
	const UNASSIGNED = 0xF0000;

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
	 * The three above say each set is kept. None of them says that keeping it is what bounds the work:
	 * a cache written and then never read back leaves all three true while shaping goes on taking
	 * minutes over a word. Nor can a clock say it - the figures in GravityPDF/mpdf#83 were measured by
	 * hand, and a wall-clock assertion on a shared runner across six PHP versions and two operating
	 * systems measures the runner.
	 *
	 * So the reading is counted. The reader the shaper reads the layout table through is swapped for one
	 * that counts the parses made out of it, the cache is emptied so the run pays for every table it
	 * uses, and the same text is shaped again. Each table the run ends up holding has to have been
	 * parsed once for each way it is kept, and no more, however many rules were matched against it.
	 *
	 * Padauk Book is shaped because its Myanmar lookups read four hundred tables between them and offer
	 * thousands of rules at a glyph, so a parse per rule is orders of magnitude from a parse per table
	 * rather than a few counts from it. FreeSerif is shaped beside it because Padauk writes its rules by
	 * coverage and FreeSerif its Devanagari by class, so between them the two read every kind of table
	 * this counts.
	 */
	public function testATableIsParsedOnceHoweverManyRulesAreMatchedAgainstIt()
	{
		$html = '<p style="font-family:padaukbook">ဤသည်မှာ မြန်မာဘာသာဖြင့်</p>'
			. '<p style="font-family:freeserif">यह नया वाक्य है</p>';

		$mpdf = new Mpdf();
		$otl = $this->otl($mpdf);

		$mpdf->WriteHTML($html); // a reader is made when a font first needs one, so make it before swapping it

		$counting = $this->countTableReads($otl);
		$otl->LuDataCache = [];

		$mpdf->WriteHTML($html);

		$parsedOtherThanOnce = [];
		$tables = 0;

		foreach ($counting as $cacheKey => $reader) {
			foreach ($this->parsesExpected($otl->LuDataCache, $cacheKey) as $offset => $expected) {
				$tables++;
				$parsed = isset($reader->tableReads[$offset]) ? $reader->tableReads[$offset] : 0;

				if ($parsed !== $expected) {
					$parsedOtherThanOnce[$cacheKey . ' at ' . $offset] = $parsed . ' parses of an expected ' . $expected;
				}
			}
		}

		$this->assertGreaterThan(100, $tables, 'the run read too few tables for the count to say anything');
		$this->assertSame([], $parsedOtherThanOnce);
	}

	/**
	 * Two of the sets are worked out in memory rather than read out of the font - the class 0 exclusions
	 * from a ClassDef table the run has already parsed, and the skip set from the GDEF glyph classes -
	 * so no count of the reading sees them, and between them they are what the profile in
	 * GravityPDF/mpdf#83 put nearly all of the time in.
	 *
	 * They are held the other way round. Once the run has built them, each is replaced by a mark - a set
	 * holding one codepoint no font here has a glyph for - and the same text is shaped again: every
	 * lookup asks for its skip set again and every rule for its exclusions. Whatever answers those asks
	 * is what the shaping is spending its time on, so anything worked out a second time writes over the
	 * mark. Every mark is still there.
	 *
	 * Emptying what they are built from would say the same thing and say it the wrong way round: a run
	 * whose ClassDef tables are empty matches no class-based rule, so it never asks for the exclusions
	 * at all and the question goes unanswered.
	 */
	public function testTheSetsBuiltFromWhatWasReadAreNotBuiltASecondTime()
	{
		$html = '<p style="font-family:freeserif">यह एक परीक्षण वाक्य है</p>'
			. '<p style="font-family:lateef">السلام علیکم ورحمۃ اللہ وبرکاتہ</p>';

		$mpdf = new Mpdf();
		$otl = $this->otl($mpdf);

		$mpdf->WriteHTML($html);

		$marks = $this->markTheSetsBuiltInMemory($otl);

		$mpdf->WriteHTML($html);

		$this->assertGreaterThan(0, $marks, 'the run built none of the sets this marks');

		foreach ($otl->LuDataCache as $cacheKey => $sets) {
			foreach (['ignore', 'class0excl'] as $bucket) {
				if (!isset($sets[$bucket])) {
					continue;
				}

				foreach ($sets[$bucket] as $key => $set) {
					$this->assertSame([self::UNASSIGNED => 1], $set, $cacheKey . ' ' . $bucket . ' ' . $key);
				}
			}
		}
	}

	/**
	 * @return array Otl's memoised tables after the string has been shaped
	 */
	private function shape($font, $text)
	{
		$mpdf = new Mpdf();
		$mpdf->WriteHTML('<p style="font-family:' . $font . '">' . $text . '</p>');

		return $this->otl($mpdf)->LuDataCache;
	}

	/**
	 * Swap the reader over each layout table the run has loaded for one that counts the parses made out
	 * of it, over the same bytes Otl reads through the font cache.
	 *
	 * @return TableReadRecordingBlobReader[] keyed as LuDataCache is, "padaukbook/GSUB"
	 */
	private function countTableReads($otl)
	{
		$fontCache = $this->property($otl, 'fontCache');
		$readers = $this->property($otl, 'readers');
		$counting = [];

		foreach ($readers as $fontkey => $byTag) {
			foreach (array_keys($byTag) as $tag) {
				$reader = new TableReadRecordingBlobReader($fontCache->load($fontkey . '.' . $tag . '.dat'));
				$readers[$fontkey][$tag] = $reader;
				$counting[$fontkey . '/' . $tag] = $reader;
			}
		}

		$property = new \ReflectionProperty('Mpdf\Otl', 'readers');
		$property->setAccessible(true);
		$property->setValue($otl, $readers);

		return $counting;
	}

	/**
	 * How many times each table the run kept had to be parsed: once for each projection of it that is
	 * cached apart from the others. A Coverage table is kept three ways - as glyph IDs, as the hex the
	 * matchers used to search, and as a set keyed by codepoint - and a ClassDef two, and each of those
	 * is read from the font in its own right. class0excl and ignore are left out: they are built from
	 * what was parsed rather than parsed themselves.
	 *
	 * The glyph ID projection is counted if it turns up, but nothing here reaches it: only a single
	 * substitution that names its output as a delta on the input reads a Coverage table that way, and
	 * none of the fonts that ship here writes one.
	 *
	 * @return array offset in the layout table => parses it should have taken
	 */
	private function parsesExpected($cache, $cacheKey)
	{
		$expected = [];

		foreach (['coverage', 'coverageGID', 'coverageUni', 'classes', 'classDef'] as $bucket) {
			if (!isset($cache[$cacheKey][$bucket])) {
				continue;
			}

			foreach (array_keys($cache[$cacheKey][$bucket]) as $offset) {
				$expected[$offset] = isset($expected[$offset]) ? $expected[$offset] + 1 : 1;
			}
		}

		return $expected;
	}

	/**
	 * Put a mark in place of every set the run worked out in memory, naming a codepoint in a plane no
	 * font here reaches so that shaping carries on as it would have: a skip set that skips only that
	 * skips nothing the text holds, and a class 0 that excludes only that excludes nothing either.
	 *
	 * @return int how many sets were marked
	 */
	private function markTheSetsBuiltInMemory($otl)
	{
		$marks = 0;

		foreach ($otl->LuDataCache as $cacheKey => $sets) {
			foreach (['ignore', 'class0excl'] as $bucket) {
				if (!isset($sets[$bucket])) {
					continue;
				}

				foreach (array_keys($sets[$bucket]) as $key) {
					$otl->LuDataCache[$cacheKey][$bucket][$key] = [self::UNASSIGNED => 1];
					$marks++;
				}
			}
		}

		return $marks;
	}

	/**
	 * @return \Mpdf\Otl the shaper the document shaped through
	 */
	private function otl(Mpdf $mpdf)
	{
		return $this->property($mpdf, 'otl');
	}

	/**
	 * @return mixed one of an object's private properties - the shaper itself, and the readers and the
	 *               font cache it keeps
	 */
	private function property($object, $name)
	{
		$property = new \ReflectionProperty(get_class($object), $name);
		$property->setAccessible(true);

		return $property->getValue($object);
	}

}
