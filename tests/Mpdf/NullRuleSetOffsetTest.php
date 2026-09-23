<?php

namespace Mpdf;

use Mpdf\Fonts\OtlDumpGoldenMaster;

/**
 * A glyph whose rule set offset is NULL in a chained context Format 1 subtable (GSUB Type 6) begins
 * no context.
 *
 * NotoSans-NullRuleSet-Synthetic is the glyph set, cmap and GDEF of NotoSans-NullClassDef-Synthetic
 * beside it - Noto Sans cut down to space, A, B and C with a few unmapped glyphs - given a GSUB of
 * three lookups:
 *
 *   #0  a chained context, Format 1, covering A and B. A's rule set holds one rule: a space, then A
 *       and C, running lookup #1 on the C. B's rule set offset is 0.
 *   #1  single substitution, C to c.sc
 *   #2  single substitution, B to b.sc
 *
 * `calt` is the only feature, on DFLT and latn, and runs lookup #0 alone.
 *
 * Following B's null offset reads the subtable header as a rule set of one rule, at the Coverage
 * table. With the bytes after it that rule is: backtrack A, input B B, lookahead C, running lookup
 * #2 on the second B - so ABBC was drawn A, B, b.sc, C. `hb-shape` leaves ABBC as it is.
 */
class NullRuleSetOffsetTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'NotoSans-NullRuleSet-Synthetic';

	/**
	 * c.sc, which A's rule substitutes. It has no codepoint of its own, so it is mapped into the
	 * Private Use Area after the three unmapped glyphs before it.
	 */
	const C_SMALL_CAP = 0xE003;

	/**
	 * The shaper matches no rule for a glyph with a null rule set, and still matches the rules of a
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
			'the rule read from the subtable header' => ['<p>ABBC</p>', [0x41, 0x42, 0x42, 0x43]],
			'the rule A\'s rule set holds' => ['<p>B AC</p>', [0x42, 0x20, 0x41, self::C_SMALL_CAP]],
		];
	}

	/**
	 * The parser, which the OTL dump reports from, reads the one rule the font holds and none for B.
	 */
	public function testTheParserReadsNoRulesForAGlyphWithANullRuleSet()
	{
		$report = (new OtlDumpGoldenMaster())->capture(self::FONT);

		$this->assertSame(1, substr_count($report, '<div class="rule">'));
		$this->assertStringContainsString('<div>Backtrack #0: <span class="unicode">U+0020</span></div>', $report);
	}

}
