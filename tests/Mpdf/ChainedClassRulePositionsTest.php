<?php

namespace Mpdf;

/**
 * Each rule of a class-based chained context (GSUB Type 6 Format 2) matches its own backtrack and
 * lookahead positions, and none the rule before it named (#170).
 *
 * No font in the corpus has a rule naming fewer positions than the one before it in a joining form's
 * lookup, which is where the shaper sees the difference. NotoSansArabic-GSUB62Positions-Synthetic is
 * NotoSansArabic-Joining-Subset (Noto Sans Arabic 2.012, OFL 1.1) with its GSUB replaced by one
 * 'fina' lookup of that type. Alef's rule gives alef its final form after beh then alef and before
 * two low alefs; beh's rule, read after it, gives beh the dotless final form after one beh and before
 * one low alef. `hb-shape` draws the glyphs these tests expect.
 */
class ChainedClassRulePositionsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ALEF = 0x0627;

	const BEH = 0x0628;

	const LOW_ALEF = 0x08AD;

	/** The final forms have no codepoint, so they are mapped into the Private Use Area */
	const ALEF_FINAL = 0xE000;

	const DOTLESS_BEH_FINAL = 0xE002;

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
			'fontdata' => ['notosansarabicgsub62positionssynthetic' => [
				'R' => 'NotoSansArabic-GSUB62Positions-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansarabicgsub62positionssynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	public function dataRuns()
	{
		return [
			'the first rule, with two positions either side' => [
				[self::ALEF, self::BEH, self::ALEF, self::LOW_ALEF, self::LOW_ALEF],
				[self::LOW_ALEF, self::LOW_ALEF, self::ALEF_FINAL, self::BEH, self::ALEF],
			],
			'the second rule, where the first rule\'s second backtrack position does not hold' => [
				[self::BEH, self::BEH, self::LOW_ALEF, self::LOW_ALEF],
				[self::LOW_ALEF, self::LOW_ALEF, self::DOTLESS_BEH_FINAL, self::BEH],
			],
			'the second rule, where the first rule\'s second lookahead position does not hold' => [
				[self::ALEF, self::BEH, self::BEH, self::LOW_ALEF],
				[self::LOW_ALEF, self::DOTLESS_BEH_FINAL, self::BEH, self::ALEF],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testEachRuleMatchesItsOwnPositions($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

}
