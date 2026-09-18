<?php

namespace Mpdf;

/**
 * A GSUB Lookup two selected features name is taken once where HarfBuzz merges it (#243).
 *
 * Otl::_applyGSUBrulesSingly() and Otl::applyGSUBfeaturesInTurn() take one pass per selected feature.
 * lookupsForFeature() de-duplicates the Lookups within one feature and #234 de-duplicated the reverse
 * Lookups the first of the two hoists, but nothing de-duplicated across the passes, so a Lookup two of
 * the features named was taken once for each of them and the second pass read the glyphs the first had
 * made.
 *
 * HarfBuzz takes it once - but only once per stage of the plan it builds, not once per run, and each of
 * these calls is one stage. Where two features are in stages of their own the Lookup is legitimately
 * taken for each, over what the earlier one produced, which is why both fonts below carry a shared
 * Lookup of each kind and this test pins both answers. `hb-shape` says which is which:
 *
 *   $ hb-shape --font-file=NotoSansTaiTham-ForwardShared-Synthetic.ttf --unicodes=1A21 --no-positions
 *   [uni1A21.blwf=0]
 *   $ hb-shape --font-file=NotoSansTaiTham-ForwardShared-Synthetic.ttf --unicodes=1A20 --no-positions
 *   [uni1A20.blwf2=0]
 *
 * Nothing in tests/data/ttf has a Lookup under two of the tags either caller names, so each is reached
 * with a font of its own.
 *
 * NotoSansTaiTham-ForwardShared-Synthetic is built from NotoSansTaiTham-ReverseShared-Synthetic, a
 * subset of Noto Sans Tai Tham 2.002 retagged lana, whose OFL 1.1 notice reserves no name. Its GSUB
 * states four Lookups: the vowel substitution under locl alone, a Lookup locl and ccmp share, a Lookup
 * pref alone names, and a Lookup pref and blwf share. The shared ones subscript a consonant and then,
 * applied to what they made, give a second form of the subscript, so a second application shows in the
 * glyph drawn. The South East Asian shaper asks for 'locl ccmp' and then 'pref abvf blwf pstf', and the
 * Universal Shaping Engine puts locl and ccmp in one stage while pref has one of its own.
 *
 * Myanmar-ForwardShared-Synthetic is the same shape over the Myanmar tag list, built from
 * Myanmar-Mym2Script-Synthetic - a subset of the Padauk Book in packages/Myanmar-Bundle, already
 * retagged mym2 and already carrying a name of its own because OFL 1.1 clause 3 bars a Reserved Font
 * Name and "Padauk" is reserved. It keeps the SIL copyright the licence requires and the donor's
 * malformed name ID 6, which TTFontFile reads as invalid and answers with name ID 4; see
 * MyanmarStackedConsonantTest for why that name is the shape it is. HarfBuzz's Myanmar plan puts locl
 * and ccmp in one stage and each basic feature in one of its own.
 *
 * NotoSansArabic-ForwardShared-Synthetic is the same shape over the Arabic presentation tag list,
 * built from NotoSansArabic-AlternateCoverage-Synthetic, a subset of Noto Sans Arabic 2.012, whose OFL
 * 1.1 notice reserves no name. It is the one caller whose list can also carry what the document asked
 * for, so it is the one that goes through featureStages(); HarfBuzz's Arabic plan puts rlig in a stage
 * of its own, then rclt and calt in the next, and the ligature features with mset in the last. The
 * font states no joining features, so the passes before the presentation forms leave its glyphs alone.
 *
 * Each feature names a Lookup no other feature names first, because readScriptsAndFeatures() keys a
 * language system's features by the first Lookup each names and drops the collisions (#245).
 */
class SharedLookupPerStageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataRuns()
	{
		return [
			// The forms no codepoint names are mapped into the Private Use Area, in glyph order:
			// uni1A20.blwf, uni1A21.blwf, uni1A63.locl, uni1A21.blwf2, uni1A22.blwf, uni1A20.blwf2
			'a Lookup locl and ccmp share, which the shaper applies in one call' => [
				'NotoSansTaiTham-ForwardShared-Synthetic.ttf',
				[0x1A21],
				[0xE001],
			],
			'the vowel the locl pass substitutes on its own' => [
				'NotoSansTaiTham-ForwardShared-Synthetic.ttf',
				[0x1A63],
				[0xE002],
			],
			'a Lookup pref names alone' => [
				'NotoSansTaiTham-ForwardShared-Synthetic.ttf',
				[0x1A22],
				[0xE004],
			],
			'a Lookup pref and blwf share, which the Universal Shaping Engine stages apart' => [
				'NotoSansTaiTham-ForwardShared-Synthetic.ttf',
				[0x1A20],
				[0xE005],
			],
			'all four of them in one run' => [
				'NotoSansTaiTham-ForwardShared-Synthetic.ttf',
				[0x1A20, 0x1A21, 0x1A22, 0x1A63],
				[0xE005, 0xE001, 0xE004, 0xE002],
			],
			// u1000.med, u1050.med and u1051.med carry a Private Use Area code point of their own in
			// the donor's cmap; u1001.med, u1002.med and u1002.med2 are given one, in glyph order
			'Myanmar: a Lookup locl and ccmp share' => [
				'Myanmar-ForwardShared-Synthetic.ttf',
				[0x1000],
				[0xE000],
			],
			'Myanmar: a Lookup locl names alone' => [
				'Myanmar-ForwardShared-Synthetic.ttf',
				[0x1050],
				[0xE001],
			],
			'Myanmar: a Lookup pref names alone' => [
				'Myanmar-ForwardShared-Synthetic.ttf',
				[0x1001],
				[0xE003],
			],
			'Myanmar: a Lookup pref and blwf share, which the Myanmar plan stages apart' => [
				'Myanmar-ForwardShared-Synthetic.ttf',
				[0x1002],
				[0xE005],
			],
			'Myanmar: all four of them in one run' => [
				'Myanmar-ForwardShared-Synthetic.ttf',
				[0x1000, 0x1001, 0x1002, 0x1050],
				[0xE000, 0xE003, 0xE005, 0xE001],
			],
			// The donor's cmap already names uni066E.init, uni066E.medi and uni066E.fina in the Private
			// Use Area, so what is given a code point here starts after them: uni0627.fina, dotbelowar,
			// uni0628.a, uni0628.b, uni066E.a, uni066E.b, in glyph order
			'Arabic: a Lookup liga and clig share' => [
				'NotoSansArabic-ForwardShared-Synthetic.ttf',
				[0x0628],
				[0xE005],
			],
			'Arabic: a Lookup liga names alone' => [
				'NotoSansArabic-ForwardShared-Synthetic.ttf',
				[0x0627],
				[0xE003],
			],
			'Arabic: a Lookup rlig names alone' => [
				'NotoSansArabic-ForwardShared-Synthetic.ttf',
				[0x08AD],
				[0xE004],
			],
			'Arabic: a Lookup rlig and calt share, which the Arabic plan stages apart' => [
				'NotoSansArabic-ForwardShared-Synthetic.ttf',
				[0x066E],
				[0xE008],
			],
			'Arabic: all four of them in one run, right to left' => [
				'NotoSansArabic-ForwardShared-Synthetic.ttf',
				[0x0627, 0x0628, 0x066E, 0x08AD],
				[0xE004, 0xE008, 0xE005, 0xE003],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testASharedLookupIsTakenOncePerStageOfThePlan($font, $codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($font, $codepoints));
	}

	/**
	 * @param string $font        The file name in tests/data/ttf
	 * @param int[]  $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($font, $codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$key = strtolower(str_replace(['-', '.ttf'], '', $font));
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$key => [
				'R' => $font,
				'useOTL' => 0xFF,
			]],
			'default_font' => $key,
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
