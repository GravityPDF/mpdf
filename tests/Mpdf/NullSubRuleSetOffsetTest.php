<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\GsubLookupRecordingTTFontFile;
use Mpdf\Fonts\OtlDumpGoldenMaster;

/**
 * A glyph whose rule set offset is NULL in a context Format 1 subtable (GSUB Type 5) has no rules.
 *
 * NotoSans-NullSubRuleSet-Synthetic is NotoSans-NullRuleSet-Synthetic with its GSUB replaced by one of
 * three lookups:
 *
 *   #0  a context, Format 1, covering A and B. A's rule set holds one rule: A then C, running lookup
 *       #1 on the C. B's rule set offset is 0.
 *   #1  single substitution, C to c.sc
 *   #2  single substitution, B to b.sc
 *
 * `calt` is the only feature, on DFLT and latn, and runs lookup #0 alone.
 *
 * Following B's null offset reads the subtable header as a rule set of one rule, at the Coverage
 * table: input B alone, running lookups #3 and #4, which the font does not have.
 */
class NullSubRuleSetOffsetTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'NotoSans-NullSubRuleSet-Synthetic';

	/**
	 * c.sc, which A's rule substitutes. It has no codepoint of its own, so it is mapped into the
	 * Private Use Area after the three unmapped glyphs before it.
	 */
	const C_SMALL_CAP = 0xE003;

	/**
	 * The parser keeps B's null offset as 0 and reads no rules for it, and reads A's one rule.
	 */
	public function testTheParserReadsNoRulesForAGlyphWithANullRuleSet()
	{
		$ttf = new GsubLookupRecordingTTFontFile(new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics(__DIR__ . '/../data/ttf/' . self::FONT . '.ttf', self::FONT, 0, false, false, 0xFF);

		$ruleSets = $ttf->gsubLookups[0]['Subtable'][0]['SubRuleSet'];

		$this->assertSame(1, $ruleSets[0]['SubRuleCount']);
		$this->assertSame(['Offset' => 0, 'SubRuleCount' => 0, 'FirstGlyph' => '00042'], $ruleSets[1]);
		$this->assertCount(1, $ttf->_getGSUBarray($ttf->gsubLookups, [0 => ['calt']], 'latn'));
	}

	/**
	 * The OTL dump reports A's rule and nothing for B.
	 */
	public function testTheDumpReportsNoRulesForAGlyphWithANullRuleSet()
	{
		$report = (new OtlDumpGoldenMaster())->capture(self::FONT);

		$this->assertSame(1, substr_count($report, '<div class="rule">'));
		$this->assertStringContainsString('<div>Input #1: <span class="unchanged">&nbsp;&#x0043;&nbsp;</span></div>', $report);
		$this->assertStringNotContainsString('diagnostics raised', $report);
	}

	/**
	 * The shaper matches no rule for a glyph with a null rule set, and still matches the rule of a
	 * glyph with one.
	 *
	 * @dataProvider dataRuns
	 *
	 * @param string $html       What the document is written from
	 * @param int[]  $codepoints The codepoints of the line the drawing code is handed
	 */
	public function testAGlyphWithANullRuleSetBeginsNoContext($html, array $codepoints)
	{
		$mpdf = new TextRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [strtolower(self::FONT) => [
				'R' => self::FONT . '.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => strtolower(self::FONT),
		]);
		$mpdf->WriteHTML($html);

		$this->assertSame($codepoints, $mpdf->drawnCodepoints(0));
	}

	/**
	 * Runs, and the codepoints `hb-shape` draws for each.
	 *
	 * @return array
	 */
	public function dataRuns()
	{
		return [
			'B begins no context' => ['<p>ABBC</p>', [0x41, 0x42, 0x42, 0x43]],
			'A\'s rule after B' => ['<p>BAC</p>', [0x42, 0x41, self::C_SMALL_CAP]],
		];
	}

}
