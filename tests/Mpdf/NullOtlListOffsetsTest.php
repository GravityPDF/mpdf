<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

/**
 * What the parser makes of a GSUB or GPOS table that states no ScriptList, FeatureList or LookupList.
 *
 * A NULL offset in the table header means the list is absent, and adding the table offset to it points
 * at the header itself, where the majorVersion of 0x0001 reads as a count of one. The table then
 * appears to carry one script whose tag is four NUL bytes and one lookup with no subtables. An isset()
 * at the two reads that warn would have left the fabricated tag standing, which is the half of it
 * nothing complains about (#253).
 *
 * Both fonts are three-glyph subsets of the Noto Sans in tests/data/ttf with a GSUB written by hand.
 * Its OFL notice reserves no name - "Copyright 2015-2021 Google LLC", with none of the Reserved Font
 * Names that OFL 1.1 clause 3 would bar a modified version from using - so each is free to be named
 * for what it carries, as the NotoSansTC synthetics beside them are. Each carries a GDEF.
 *
 * - NotoSans-NullOtlLists-Synthetic has both a GSUB and a GPOS that are nothing but a header:
 *   `00 01 00 00 00 00 00 00 00 00`, which is byte-for-byte what the five google/fonts families in the
 *   issue ship. Both tables are read by the same three lines, so the font reaches both.
 * - NotoSans-EmptyLookup-Synthetic states a real latn ScriptList, a real liga FeatureList and a
 *   LookupList whose one Lookup states no subtables, which the format permits and `hb-shape` shapes
 *   without complaint. It is the same absent Subtables key reached from a conforming font rather than
 *   from the phantom lookup.
 */
class NullOtlListOffsetsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const NULL_LISTS = 'NotoSans-NullOtlLists-Synthetic';

	const EMPTY_LOOKUP = 'NotoSans-EmptyLookup-Synthetic';

	/**
	 * @var string[] What PHP raised while the recording handler was installed
	 */
	private $raised = [];

	public function testNoScriptIsFabricatedFromTheTableHeader()
	{
		$ttf = $this->parse(self::NULL_LISTS);

		$this->assertSame([], $ttf->GSUBScriptLang, 'GSUB script tags: ' . $this->tags($ttf->GSUBScriptLang));
		$this->assertSame([], $ttf->GPOSScriptLang, 'GPOS script tags: ' . $this->tags($ttf->GPOSScriptLang));
	}

	public function testATableStatingNoListsCarriesNoFeatureAndNoLookup()
	{
		$ttf = $this->parse(self::NULL_LISTS);

		$this->assertSame([], $ttf->GSUBFeatures);
		$this->assertSame([], $ttf->GPOSFeatures);
		$this->assertSame([], $ttf->GSUBLookups);
		$this->assertSame([], $ttf->GPOSLookups);
	}

	/**
	 * The page these fonts draw is already right - a walk over an absent lookup list contributes nothing,
	 * which is what an absent lookup list should contribute - so on a default error handler this is the
	 * whole cost, and a caller that promotes warnings to exceptions cannot parse the font at all.
	 *
	 * @dataProvider dataFonts
	 */
	public function testParsingRaisesNoDiagnostics($font)
	{
		$this->parse($font);

		$this->assertSame([], $this->raised);
	}

	public function dataFonts()
	{
		return [
			'both lists absent' => [self::NULL_LISTS],
			'a lookup with no subtables' => [self::EMPTY_LOOKUP],
		];
	}

	/**
	 * A lookup the font really does state, which really does state no subtables. What it offers is an
	 * empty list of them rather than nothing at all.
	 */
	public function testALookupStatingNoSubtablesOffersAnEmptyListOfThem()
	{
		$ttf = $this->parse(self::EMPTY_LOOKUP);

		$this->assertSame(['latn' => 'DFLT '], $ttf->GSUBScriptLang);
		$this->assertSame(['latn' => ['DFLT' => ['liga' => [0]]]], $ttf->GSUBFeatures);
		$this->assertSame([[
			'Type' => 1,
			'Flag' => 0,
			'SubtableCount' => 0,
			'Subtables' => [],
			'MarkFilteringSet' => '',
		]], $ttf->GSUBLookups);
	}

	/**
	 * The shaper reads the lookup list back out of the cache and walks the subtables of every lookup a
	 * feature names, so a lookup that states none is read there too, twice a character.
	 */
	public function testShapingAgainstALookupStatingNoSubtablesDrawsSilently()
	{
		$this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<p>AB</p>');
		}, [
			'mode' => 'utf-8',
			// The font cache keys on the family name, so a directory of its own is what makes the lookup
			// list read back here the one this run parsed
			'tempDir' => sys_get_temp_dir() . '/mpdf-null-otl-lists-' . uniqid('', true),
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [strtolower(self::EMPTY_LOOKUP) => [
				'R' => self::EMPTY_LOOKUP . '.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => strtolower(self::EMPTY_LOOKUP),
		]);
	}

	/**
	 * Parses under a handler of its own, so that a warning is recorded rather than thrown by PHPUnit's
	 * and the tables it produced can still be read. Everything recorded is asserted empty by
	 * testParsingRaisesNoDiagnostics.
	 *
	 * @param string $font A file name in tests/data/ttf, without its extension
	 *
	 * @return TTFontFile The parser, having read that font with every script enabled
	 */
	private function parse($font)
	{
		$this->raised = [];

		set_error_handler(function ($number, $message, $path, $line) {
			$this->raised[] = sprintf('%s in %s:%d', $message, basename($path), $line);

			return true;
		});

		// A fontkey of its own each time: the cache is keyed on it, so a shared one would have a later
		// parse read what an earlier one wrote
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')), 'win');

		try {
			$ttf->getMetrics(__DIR__ . '/../data/ttf/' . $font . '.ttf', uniqid('', true), 0, false, false, 0xFF);
		} finally {
			restore_error_handler();
		}

		return $ttf;
	}

	/**
	 * @param array $scriptLang The parser's GSUBScriptLang or GPOSScriptLang
	 *
	 * @return string Its script tags as hex, so that a fabricated one reads as 00000000 in the failure
	 *                rather than as nothing at all
	 */
	private function tags(array $scriptLang)
	{
		return implode(', ', array_map('bin2hex', array_keys($scriptLang)));
	}

}
