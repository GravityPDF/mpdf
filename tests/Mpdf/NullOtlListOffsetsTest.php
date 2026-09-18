<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

/**
 * What the parser makes of a GSUB or GPOS table that states no ScriptList, FeatureList or LookupList.
 *
 * A NULL offset in the table header means the list is absent, and adding the table offset to it points
 * at the header itself, where the majorVersion of 0x0001 reads as a count of one. The table then
 * appears to carry one script whose tag is four NUL bytes and one lookup with no subtables, which cost
 * four warnings a render and a fabricated tag no caller asked for (#253).
 *
 * Both fonts here are three-glyph subsets of the Noto Sans in tests/data/ttf with a GSUB written by
 * hand. Its OFL notice reserves no name - "Copyright 2015-2021 Google LLC", with none of the Reserved
 * Font Names that OFL 1.1 clause 3 would bar a modified version from using - so each is free to be
 * named for what it carries, as the NotoSansTC synthetics beside them are. Each carries a GDEF, without
 * which the parser refuses the font before it ever reads GSUB.
 *
 * - NotoSans-NullOtlLists-Synthetic has both a GSUB and a GPOS that are nothing but a header:
 *   `00 01 00 00 00 00 00 00 00 00`, which is byte-for-byte what the five google/fonts families in the
 *   issue ship. Both tables are read by the same three lines, so the font reaches both.
 * - NotoSans-EmptyLookup-Synthetic states a real latn ScriptList, a real liga FeatureList and a
 *   LookupList whose one Lookup states no subtables, which the format permits and `hb-shape` shapes
 *   without complaint. It is the same absent Subtables key arrived at from a conforming font rather
 *   than from the phantom lookup, and it is why the key is stated rather than left to the loop that
 *   fills it: the shaper reads it unguarded as well, four times.
 */
class NullOtlListOffsetsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const NULL_LISTS = 'NotoSans-NullOtlLists-Synthetic';

	const EMPTY_LOOKUP = 'NotoSans-EmptyLookup-Synthetic';

	/**
	 * @var string
	 */
	private $tempDir;

	/**
	 * @var string[] What PHP raised while the recording handler was installed
	 */
	private $raised = [];

	public function set_up()
	{
		parent::set_up();

		// A fresh directory each time: the font cache is keyed by the family name and rewritten only
		// when the file's size changes, so a cache one run left behind is read by the next
		$this->tempDir = sys_get_temp_dir() . '/mpdf-null-otl-lists-' . uniqid('', true);
	}

	protected function tear_down()
	{
		$this->remove($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The silent half of the defect, and the reason an isset() at the warning sites would not have been
	 * a fix: nothing in the table says "latn" or anything else, and reading the header as a ScriptList
	 * invents a script from the version number and the offset that follows it.
	 */
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
	 * The page these fonts draw is already right - a walk over an absent lookup list contributes
	 * nothing, which is what an absent lookup list should contribute - so what the defect costs a
	 * default error handler is this, and a caller that promotes warnings to exceptions cannot parse the
	 * font at all.
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
	 * The shaper reads the lookup list back out of the cache and walks its subtables for every character
	 * of every run, so a lookup that states none costs two warnings a character there as well.
	 */
	public function testShapingAgainstALookupStatingNoSubtablesRaisesNoDiagnostics()
	{
		$this->record();

		try {
			$mpdf = new Mpdf([
				'mode' => 'utf-8',
				'tempDir' => $this->tempDir,
				'fontDir' => [__DIR__ . '/../data/ttf'],
				'fontdata' => [strtolower(self::EMPTY_LOOKUP) => [
					'R' => self::EMPTY_LOOKUP . '.ttf',
					'useOTL' => 0xFF,
				]],
				'default_font' => strtolower(self::EMPTY_LOOKUP),
			]);
			$mpdf->WriteHTML('<p>AB</p>');
			$mpdf->OutputBinaryData();
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $this->raised);
	}

	/**
	 * Parses under a handler of its own, recording what PHP raises into $raised rather than letting
	 * PHPUnit's handler turn the first warning into an exception. Everything it records is asserted
	 * empty by testParsingRaisesNoDiagnostics, so nothing is swallowed: what it buys is that the other
	 * tests read the tables the parser produced rather than stopping at the first warning it raised on
	 * the way.
	 *
	 * @param string $font A file name in tests/data/ttf, without its extension
	 *
	 * @return TTFontFile The parser, having read that font with every script enabled
	 */
	private function parse($font)
	{
		$ttf = new TTFontFile(new FontCache(new Cache($this->tempDir . '/ttfontdata')), 'win');

		$this->record();

		try {
			$ttf->getMetrics(__DIR__ . '/../data/ttf/' . $font . '.ttf', strtolower($font), 0, false, false, 0xFF);
		} finally {
			restore_error_handler();
		}

		return $ttf;
	}

	/**
	 * Collects every diagnostic PHP raises until the handler is restored. A warning is not an exception,
	 * so a handler is what sees it.
	 */
	private function record()
	{
		$this->raised = [];

		set_error_handler(function ($number, $message, $path, $line) {
			$this->raised[] = sprintf('%s in %s:%d', $message, basename($path), $line);

			return true;
		});
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

	/**
	 * @param string $path A directory, deleted with everything under it
	 */
	private function remove($path)
	{
		foreach (glob($path . '/*') as $child) {
			if (is_dir($child)) {
				$this->remove($child);
			} else {
				unlink($child);
			}
		}

		if (is_dir($path)) {
			rmdir($path);
		}
	}

}
