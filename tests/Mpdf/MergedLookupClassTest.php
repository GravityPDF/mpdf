<?php

namespace Mpdf;

/**
 * A Lookup two Indic features of one language system name states a consonant of both classes (#271).
 *
 * The other half of what the tag on a rule decides, alongside MergedLookupFormTest. A rule read under
 * rphf, half, pref, blwf or pstf gives its consonant that class, and Shaper\Indic reads the classes
 * back to decide which position of the syllable each feature is allowed to change. A Lookup two of
 * them name kept one tag, so the consonants it states were of one class and the other class was
 * empty - and an empty class is a position the reordering never marks, so the feature reaches
 * nothing there even though the font stated it.
 *
 * NotoSansBengali-SharedClass is the glyph set of NotoSansBengali-DevaScript-Synthetic - Noto Sans
 * Bengali Regular, SIL OFL 1.1 - with a conjunct form added for each of the two consonants, drawn
 * with the outlines of letters the donor already has, and a GSUB built for this test:
 *
 *   lookup 0  blwf, then half      uni0995 uni09CD -> uni0995.half
 *                                  uni0997 uni09CD -> uni0997.blwf
 *
 * The script is beng, where a class is stated as Consonant + Halant whichever of the five features
 * states it, so one Lookup can hold both rules and both features can read a class out of both. The
 * names of the two forms say which class each rule is there for: half forms sit before the base
 * consonant of the syllable and below-base forms after it, so one syllable holds one of each and the
 * two classes can be told apart in what is drawn. Noto's OFL notice reserves no font name, so clause
 * 3 leaves a modified version free to be called anything; it carries a name of its own because a font
 * called Noto Sans Bengali that is not Noto Sans Bengali would mislead, and keeps the donor's
 * copyright and licence strings because clauses 1 and 2 require them to travel.
 *
 * Reading the Lookup under the tag the language system listed last leaves blwf empty, so the
 * consonant after the base keeps its halant and its own shape. Reading it under the first leaves half
 * empty, which this font cannot show: with blwf stating both consonants the old-spec reordering
 * reaches the pre-base one through the blwf class as well, and draws the same line as the fix does.
 * tests/data/fontcache/NotoSansBengali-SharedClass-Synthetic.json is where that answer shows, holding
 * the two class tables the rules landed in.
 *
 * `hb-shape` 14.3.1 draws the line the way the fix does:
 *
 *   $ hb-shape --font-file=NotoSansBengali-SharedClass-Synthetic.ttf --unicodes=995,9CD,9B7,9CD,997 --no-positions
 *   [uni0995.half=0|uni09B7=2|uni0997.blwf=2]
 */
class MergedLookupClassTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The two conjunct forms have no code point of their own, so the parser gives each one a Private
	 * Use Area code point, in glyph order: uni0995.half, uni0997.blwf.
	 */
	const KA_HALF = 0xE000;
	const GA_BELOW_BASE = 0xE001;

	const KA = 0x0995;
	const SSA = 0x09B7;
	const GA = 0x0997;
	const HALANT = 0x09CD;

	public function dataRuns()
	{
		return [
			'a syllable holding a consonant of each class, from the one Lookup' => [
				[self::KA, self::HALANT, self::SSA, self::HALANT, self::GA],
				[self::KA_HALF, self::SSA, self::GA_BELOW_BASE],
			],
			'the half form alone, which is the class the surviving tag stated' => [
				[self::KA, self::HALANT, self::SSA],
				[self::KA_HALF, self::SSA],
			],
			'the below-base form alone, which is the class that was lost' => [
				[self::SSA, self::HALANT, self::GA],
				[self::SSA, self::GA_BELOW_BASE],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAMergedLookupStatesTheClassOfEveryFeatureThatNamedIt($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the code points of the line as it is handed to the drawing code, in visual order
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
			'fontdata' => ['bengalisharedclass' => [
				'R' => 'NotoSansBengali-SharedClass-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'bengalisharedclass',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
