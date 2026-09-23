<?php

namespace Mpdf;

/**
 * An Indic font's above-base forms are applied, at the stage HarfBuzz applies them (#269).
 *
 * shapeIndic() names the stages of HarfBuzz's plan a feature at a time, and the Indic list was
 * `indic_features` in HarfBuzz's order with abvf missing from between blwf and half. The
 * presentation pass did not stand in for it - abvf is in Otl::PRESENTATION_OMIT_TAGS - so under the
 * Indic and Sinhala shapers the feature was never applied at all, although the reordering marks
 * characters for it there exactly as it does for Khmer.
 *
 * Nothing in the corpus states abvf under an Indic tag, so NotoSansBengali-AbvfStage-Synthetic is an
 * eight-glyph subset of Noto Sans Bengali 3.011 (SIL OFL 1.1 with no Reserved Font Name, fsType 0)
 * offering bng2 alone, with a GSUB built for this test:
 *
 *   lookup 0  abvf   GA (uni0997) -> KHA (uni0996)
 *   lookup 1  pstf   VIRAMA GA -> uni0997.pst, the donor's own uni09B7 under a name of its own
 *
 * The pstf lookup is there to move the base. An Indic base is the last consonant of the syllable
 * unless the font gives it a below-base or post-base form - update_consonant_positions() reads those
 * two features to tell - and only what follows the base is marked, so without it every GA below
 * would be a base and neither row could differ from the other. pstf rather than blwf because blwf is
 * applied before abvf and would have taken the GA first: a GA after the base coming out as KHA and
 * not as uni0997.pst is the stage order, not merely the feature.
 *
 * `hb-shape` 14.3.1 draws both rows the same way. Its second run is that syllable with abvf switched
 * off, which leaves the GA to the post-base lookup, and is what mPDF drew here until now:
 *
 *   $ hb-shape --font-file=NotoSansBengali-AbvfStage-Synthetic.ttf --unicodes=0997,09CD,0997 --no-positions
 *   [uni0997=0|uni09CD=0|uni0996=2]
 *   $ hb-shape --font-file=NotoSansBengali-AbvfStage-Synthetic.ttf --unicodes=0997,09CD,0997 --features=-abvf --no-positions
 *   [uni0997=0|uni0997.pst=0]
 */
class IndicAbvfStageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const KHA = 0x0996;

	const GA = 0x0997;

	const VIRAMA = 0x09CD;

	public function dataRuns()
	{
		return [
			'a base consonant, the whole of its syllable' => [
				[self::GA],
				[self::GA],
			],
			'the same consonant both as the base and after it' => [
				[self::GA, self::VIRAMA, self::GA],
				[self::GA, self::VIRAMA, self::KHA],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testTheAboveBaseFormsAreAppliedAtTheirOwnStage($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

	/**
	 * A document turning abvf on has it at its own stage, over every glyph rather than only those the
	 * reordering marked, which is what `hb-shape --features=abvf` draws. It is still not handed to
	 * the presentation pass: Otl::PRESENTATION_OMIT_TAGS keeps it out of there.
	 */
	public function testADocumentTurningAbvfOnHasItAtItsOwnStage()
	{
		$this->assertSame(
			[self::KHA, self::VIRAMA, self::KHA],
			$this->drawn([self::GA, self::VIRAMA, self::GA], "font-feature-settings:'abvf'")
		);
	}

	/**
	 * @param int[]  $codepoints
	 * @param string $style      The paragraph's own style, where the case turns on what the document
	 *                           asked for
	 *
	 * @return int[] the code points of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints, $style = '')
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosansbengaliabvfstagesynthetic' => [
				'R' => 'NotoSansBengali-AbvfStage-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansbengaliabvfstagesynthetic',
		]);
		$mpdf->WriteHTML('<p style="' . $style . '">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
