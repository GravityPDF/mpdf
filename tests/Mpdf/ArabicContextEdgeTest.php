<?php

namespace Mpdf;

/**
 * An Arabic joining form whose chained rule has a backtrack or lookahead is matched by walking over
 * the glyphs the rule's lookup ignores, and a word that starts or ends in those glyphs takes the walk
 * to the edge of the run. It carried on past it, and from PHP 8 never returned (#204). Running out of
 * glyphs there means the rule does not match, as HarfBuzz has it.
 *
 * No font in the corpus writes a backtrack or lookahead into its rtlSUB table.
 * NotoSansArabic-ContextEdge-Synthetic is NotoSansArabic-Joining-Subset (Noto Sans Arabic 2.012, OFL
 * 1.1) with a FATHA added, and its GSUB replaced by 'init' and 'fina' Chaining Context Substitutions
 * (Type 6 Format 3) flagged IgnoreMarks: beh takes the dotless initial form after a low alef, and the
 * dotless final form before one. Its 'ccmp' is dropped, because the shaper would take beh apart into a
 * dotless beh and a dot before resolving joining, and a glyph that is not a character joins nothing
 * (#209).
 * `hb-shape` 14.3.1 draws the glyphs these tests expect.
 */
class ArabicContextEdgeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const BEH = 0x0628;

	const FATHA = 0x064E;

	const LOW_ALEF = 0x08AD;

	/** The forms have no codepoint, so they are mapped into the Private Use Area */
	const DOTLESS_BEH_FINAL = 0xE002;

	const DOTLESS_BEH_INITIAL = 0xE004;

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosansarabiccontextedgesynthetic' => [
				'R' => 'NotoSansArabic-ContextEdge-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansarabiccontextedgesynthetic',
		]);
		$mpdf->WriteHTML('<p dir="rtl">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	public function dataRuns()
	{
		return [
			'a word ending in a mark, where the lookahead runs out' => [
				[self::BEH, self::BEH, self::FATHA],
				[self::FATHA, self::BEH, self::BEH],
			],
			'a word starting with a mark, where the backtrack runs out' => [
				[self::FATHA, self::BEH, self::BEH],
				[self::BEH, self::BEH, self::FATHA],
			],
			'the lookahead met past the mark' => [
				[self::BEH, self::BEH, self::FATHA, self::LOW_ALEF],
				[self::LOW_ALEF, self::FATHA, self::DOTLESS_BEH_FINAL, self::BEH],
			],
			'the backtrack met past the mark' => [
				[self::LOW_ALEF, self::FATHA, self::BEH, self::BEH],
				[self::BEH, self::DOTLESS_BEH_INITIAL, self::FATHA, self::LOW_ALEF],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAFormsContextIsMatchedUpToTheEdgeOfTheRun($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

}
