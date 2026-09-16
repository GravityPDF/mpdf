<?php

namespace Mpdf;

/**
 * A plain context rule (GSUB Type 5) of an Arabic joining form has no backtrack or lookahead, so the
 * shaper gives the form wherever the joining calls for it, whatever chained rule came before (#189).
 *
 * No font in the corpus has such a rule, so NotoSansArabic-GSUB5Form-Synthetic is
 * NotoSansArabic-Joining-Subset (Noto Sans Arabic 2.012, OFL 1.1) with its GSUB replaced by three
 * lookups for beh, in this order: a 'fina' Type 6 Format 3 giving the dotless final form after beh and
 * before low alef, an 'init' Type 5 Format 1 giving the dotless initial form, and a 'medi' Type 5
 * Format 2 giving the dotless medial form. `hb-shape` draws the glyphs these tests expect.
 */
class ContextRuleArabicFormTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const BEH = 0x0628;

	const LOW_ALEF = 0x08AD;

	/** The dotless forms have no codepoint, so they are mapped into the Private Use Area */
	const DOTLESS_BEH_FINAL = 0xE002;

	const DOTLESS_BEH_MEDIAL = 0xE003;

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
			'fontdata' => ['notosansarabicgsub5formsynthetic' => [
				'R' => 'NotoSansArabic-GSUB5Form-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansarabicgsub5formsynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	public function dataRuns()
	{
		return [
			'an initial form from Format 1, with nothing before it' => [
				[self::BEH, self::BEH],
				[self::BEH, self::DOTLESS_BEH_INITIAL],
			],
			'an initial form from Format 1, before the chained rule\'s final form' => [
				[self::BEH, self::BEH, self::LOW_ALEF],
				[self::LOW_ALEF, self::DOTLESS_BEH_FINAL, self::DOTLESS_BEH_INITIAL],
			],
			'a medial form from Format 2, with no low alef after it' => [
				[self::BEH, self::BEH, self::BEH, self::LOW_ALEF],
				[self::LOW_ALEF, self::DOTLESS_BEH_FINAL, self::DOTLESS_BEH_MEDIAL, self::DOTLESS_BEH_INITIAL],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testTheFormNeedsNoContext($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

}
