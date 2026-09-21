<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Cache;
use Mpdf\Fonts\BlobReader;
use Mpdf\Fonts\FontCache;
use Mpdf\TTFontFile;

/**
 * GsubOutputs over GSUB subtables built here, one of each lookup type. Each subtable is read from 4
 * bytes in, past bytes that are no glyph, so every offset in it has to be followed from where the
 * subtable starts rather than from the start of what is read.
 */
class GsubOutputsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Single substitution format 1 adds its delta to each glyph it covers, modulo 65536, so a delta
	 * can carry a glyph past 0xFFFF round to the start
	 */
	public function testASingleSubstitutionByDeltaOutputsEachCoveredGlyphMovedByIt()
	{
		// substFormat, coverageOffset, deltaGlyphID, then the Coverage table
		$subtable = pack('n3', 1, 6, 5) . pack('n4', 1, 2, 3, 0xFFFE);

		$this->assertSame([8, 3], $this->outputs(1, $subtable));
	}

	/**
	 * A negative delta moves each glyph down
	 */
	public function testASingleSubstitutionByANegativeDeltaMovesEachGlyphDown()
	{
		$subtable = pack('n3', 1, 6, 0x10000 - 2) . pack('n3', 1, 1, 7);

		$this->assertSame([5], $this->outputs(1, $subtable));
	}

	/**
	 * Single substitution format 2 lists its substitutes outright
	 */
	public function testASingleSubstitutionByListOutputsEachSubstitute()
	{
		// substFormat, coverageOffset, glyphCount, substituteGlyphIDs, then the Coverage table
		$subtable = pack('n5', 2, 10, 2, 40, 41) . pack('n4', 1, 2, 3, 4);

		$this->assertSame([40, 41], $this->outputs(1, $subtable));
	}

	/**
	 * A multiple substitution outputs every glyph of every Sequence, and an alternate substitution
	 * every glyph of every AlternateSet, the two being laid out alike
	 *
	 * @dataProvider setsOfGlyphs
	 *
	 * @param int $type 2, multiple, or 3, alternate
	 */
	public function testEachGlyphOfEachSequenceOrAlternateSetIsAnOutput($type)
	{
		// substFormat, coverageOffset, sequenceCount, sequenceOffsets; two Sequence tables, each a count
		// and its glyphs; then the Coverage table
		$subtable = pack('n5', 1, 20, 2, 10, 16) . pack('n3', 2, 50, 51) . pack('n2', 1, 52) . pack('n4', 1, 2, 3, 4);

		$this->assertSame([50, 51, 52], $this->outputs($type, $subtable));
	}

	/**
	 * @return array[] The two lookup types a list of glyphs per covered glyph
	 */
	public function setsOfGlyphs()
	{
		return [
			'multiple substitution' => [2],
			'alternate substitution' => [3],
		];
	}

	/**
	 * A ligature substitution outputs the ligature glyph of every Ligature in every LigatureSet, and
	 * none of the components it is formed from
	 */
	public function testALigatureSubstitutionOutputsEachLigatureButNotItsComponents()
	{
		// substFormat, coverageOffset, ligatureSetCount, ligatureSetOffsets; a LigatureSet of a count
		// and two offsets from the set; two Ligatures, each its glyph, componentCount and the
		// components after the first; then the Coverage table
		$subtable = pack('n4', 1, 28, 1, 8)
			. pack('n3', 2, 6, 12)
			. pack('n3', 70, 2, 9)
			. pack('n4', 71, 3, 9, 10)
			. pack('n3', 1, 1, 3);

		$this->assertSame([70, 71], $this->outputs(4, $subtable));
	}

	/**
	 * A reverse chaining substitution outputs its substitutes, and none of the glyphs its Coverage
	 * tables name
	 */
	public function testAReverseChainingSubstitutionOutputsItsSubstitutes()
	{
		// substFormat, coverageOffset, backtrackGlyphCount and one offset, lookaheadGlyphCount and two
		// offsets, glyphCount, substituteGlyphIDs; then one Coverage table all four offsets share
		$subtable = pack('n10', 1, 20, 1, 20, 2, 20, 20, 2, 80, 81) . pack('n4', 1, 2, 3, 4);

		$this->assertSame([80, 81], $this->outputs(8, $subtable));
	}

	/**
	 * A contextual or chained contextual lookup only names other lookups, which output glyphs of their
	 * own and are in the list too: it adds nothing of its own beside them
	 *
	 * @dataProvider contextualSubtables
	 *
	 * @param int    $type     5 or 6
	 * @param string $subtable The subtable
	 */
	public function testAContextualLookupOutputsNoGlyphOfItsOwn($type, $subtable)
	{
		$single = pack('n3', 1, 6, 1) . pack('n3', 1, 1, 30);
		$reader = new BlobReader(pack('n2', 0xFFFF, 0xFFFF) . $single . $subtable);

		$lookups = [
			['Type' => $type, 'Subtables' => [4 + strlen($single)]],
			['Type' => 1, 'Subtables' => [4]],
		];

		$this->assertSame([31], array_keys(GsubOutputs::glyphs($reader, $lookups)));
	}

	/**
	 * @return array[] A contextual subtable in each of formats 1 and 3, and a chained one in format 3,
	 *                 each applying lookup 1 at position 0 of a match
	 */
	public function contextualSubtables()
	{
		return [
			// substFormat, coverageOffset, seqRuleSetCount, seqRuleSetOffsets; a SequenceRuleSet of one
			// rule; the rule, glyphCount, seqLookupCount, the input after the first and its record; then
			// the Coverage table
			'contextual, format 1' => [5, pack('n4', 1, 22, 1, 8) . pack('n2', 1, 4) . pack('n5', 2, 1, 40, 0, 1) . pack('n3', 1, 1, 30)],
			// substFormat, glyphCount, seqLookupCount, one coverage offset, the record; then the Coverage
			// table
			'contextual, format 3' => [5, pack('n6', 3, 1, 1, 12, 0, 1) . pack('n3', 1, 1, 30)],
			// substFormat, no backtrack, one input coverage offset, no lookahead, seqLookupCount and the
			// record; then the Coverage table
			'chained contextual, format 3' => [6, pack('n8', 3, 0, 1, 16, 0, 1, 0, 1) . pack('n3', 1, 1, 30)],
		];
	}

	/**
	 * An extension lookup's subtables are followed by the parser to the subtables they stand for, and
	 * those are read as the type the extension names
	 */
	public function testAnExtensionIsReadAsTheLookupItStandsFor()
	{
		// A LookupList at 4, since one at 0 is read as absent, listing one Lookup 4 on from it, at 8:
		// Type 7, lookupFlag, subTableCount and one offset from the Lookup, to 16. The extension there:
		// format, the type it stands for and an Offset32 from the extension, to 24. A single
		// substitution by list there, and its Coverage table at 32.
		$gsub = pack('n2', 0xFFFF, 0xFFFF)
			. pack('n2', 1, 4)
			. pack('n4', 7, 0, 1, 8)
			. pack('n2', 1, 1) . pack('N', 8)
			. pack('n4', 2, 8, 1, 77)
			. pack('n3', 1, 1, 5);

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$reader = new \ReflectionProperty($ttf, 'reader');
		$readLookupList = new \ReflectionMethod($ttf, 'readLookupList');

		// A no-op from PHP 8.1 and deprecated from 8.5
		if (PHP_VERSION_ID < 80100) {
			$reader->setAccessible(true);
			$readLookupList->setAccessible(true);
		}

		$reader->setValue($ttf, $blob = new BlobReader($gsub));
		$lookups = $readLookupList->invoke($ttf, 4, 0, 7);

		$this->assertSame(1, $lookups[0]['Type']);
		$this->assertSame([77], array_keys(GsubOutputs::glyphs($blob, $lookups)));
	}

	/**
	 * Every lookup and every subtable of each is read, and a glyph more than one outputs is listed once
	 */
	public function testTheOutputsOfEveryLookupAreGatheredOnce()
	{
		$single = pack('n5', 2, 10, 2, 40, 41) . pack('n3', 1, 1, 3);
		$delta = pack('n3', 1, 6, 1) . pack('n4', 1, 2, 39, 40);
		$reader = new BlobReader($single . $delta);

		$lookups = [
			['Type' => 1, 'Subtables' => [0, strlen($single)]],
			['Type' => 1, 'Subtables' => []],
			['Type' => 1, 'Subtables' => [0]],
		];

		$this->assertSame([40 => true, 41 => true], GsubOutputs::glyphs($reader, $lookups));
	}

	/**
	 * @param int    $type     The lookup type
	 * @param string $subtable One subtable of it
	 *
	 * @return int[] The glyphs GsubOutputs finds the subtable outputs, read from 4 bytes in
	 */
	private function outputs($type, $subtable)
	{
		$reader = new BlobReader(pack('n2', 0xFFFF, 0xFFFF) . $subtable);

		return array_keys(GsubOutputs::glyphs($reader, [['Type' => $type, 'Subtables' => [4]]]));
	}
}
