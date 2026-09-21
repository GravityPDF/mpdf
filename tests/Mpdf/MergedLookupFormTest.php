<?php

namespace Mpdf;

/**
 * A Lookup two joining features of one language system name states both of their forms (#271).
 *
 * TTFontFile::useGSUBlookups() keyed its Lookup list by Lookup index with the feature tag as the
 * value, so a Lookup two features named kept one tag, and _getGSUBarray() stamped that tag on every
 * rule it read out of it. The tag is what rtlSUB is built from - isol goes in slot 0, fina in slot 1,
 * init in 2, medi in 3 - so the form under the tag that was dropped was never recorded, and
 * Shaper\Arabic::shape() drew whatever its fallbacks reach for a slot the font never filled.
 *
 * NotoSansArabic-SharedForm is the glyph set of NotoSansArabic-Joining-Subset - Noto Sans Arabic
 * Regular 2.012, SIL OFL 1.1 - less its medial form, carrying a GSUB built for this test:
 *
 *   lookup 0  ccmp                 uni0628 -> uni066E
 *   lookup 1  init, then medi      uni066E -> uni066E.init
 *   lookup 2  fina                 uni0627 -> uni0627.fina, uni066E -> uni066E.fina
 *
 * Lookup 1 is the shape of the defect: one form serving both the initial and the medial position,
 * which is how an Arabic letter whose two forms are drawn alike is usually stated. Lookup 2, which
 * one feature names alone, is the control. Noto's OFL notice reserves no font name, so clause 3
 * leaves a modified version free to be called anything; it carries a name of its own because a font
 * called Noto Sans Arabic that is not Noto Sans Arabic would mislead, and keeps the donor's copyright
 * and licence strings because clauses 1 and 2 require them to travel.
 *
 * One three-letter word tells the three answers apart, because each wrong one leaves a different
 * position with no form of its own and the fallbacks in glyphs() differ: a medial position falls back
 * to the final form, an initial position to the isolated form, which this font does not state either,
 * so it is left with the letter itself.
 *
 *   taking the tag listed last   [fina, init, letter]   the initial position has no form
 *   taking the tag listed first  [fina, fina, init]     the medial position falls back to final
 *   taking both                  [fina, init, init]
 *
 * `hb-shape` 14.3.1 draws the third:
 *
 *   $ hb-shape --font-file=NotoSansArabic-SharedForm-Synthetic.ttf --unicodes=628,628,628 --no-positions
 *   [uni066E.fina=2|uni066E.init=1|uni066E.init=0]
 *
 * @see MergedLookupClassTest, the same defect where the tag states an Indic consonant's class
 */
class MergedLookupFormTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The forms have no code point of their own, so the parser gives each one a Private Use Area
	 * code point, in glyph order: uni0627.fina, uni066E, uni066E.fina, uni066E.init.
	 */
	const ALEF_FINAL = 0xE000;
	const LETTER = 0xE001;
	const LETTER_FINAL = 0xE002;
	const LETTER_INITIAL = 0xE003;

	public function dataRuns()
	{
		return [
			'a letter in all three positions, the shared Lookup drawing two of them' => [
				[0x0628, 0x0628, 0x0628],
				[self::LETTER_FINAL, self::LETTER_INITIAL, self::LETTER_INITIAL],
			],
			'the initial position, which the surviving tag left without a form' => [
				[0x0628, 0x0628],
				[self::LETTER_FINAL, self::LETTER_INITIAL],
			],
			'an isolated letter, a form the font states under no feature at all' => [
				[0x0628],
				[self::LETTER],
			],
			'the Lookup fina names alone, on a letter that joins only to its right' => [
				[0x0628, 0x0627],
				[self::ALEF_FINAL, self::LETTER_INITIAL],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAMergedLookupStatesTheFormOfEveryFeatureThatNamedIt($codepoints, $expected)
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
			'fontdata' => ['arabicsharedform' => [
				'R' => 'NotoSansArabic-SharedForm-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'arabicsharedform',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
