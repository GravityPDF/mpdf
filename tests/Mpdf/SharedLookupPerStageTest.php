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
 * HarfBuzz merges them once per stage of the plan it builds, not once per run, and each of these calls
 * is one stage. Where two features have stages of their own the Lookup is legitimately taken for each,
 * over what the earlier one produced, so each font below states a Lookup of both kinds and every case
 * is what `hb-shape` 14.3.1 draws:
 *
 *   $ hb-shape --font-file=NotoSansTaiTham-ForwardShared-Synthetic.ttf --unicodes=1A21 --no-positions
 *   [uni1A21.blwf=0]
 *   $ hb-shape --font-file=NotoSansTaiTham-ForwardShared-Synthetic.ttf --unicodes=1A20 --no-positions
 *   [uni1A20.blwf2=0]
 *
 * Nothing in tests/data/ttf has a Lookup under two of the tags either caller names, so each is reached
 * with a font of its own. Each states `locl [0, 1]` or `liga [0, 1]` against `ccmp [1]` or `clig [1]`,
 * two features of one stage, and `pref [2, 3]` or `rlig [2, 3]` against `blwf [3]` or `calt [3]`, two
 * features of two. The shared Lookup subscripts a consonant and then, applied to what it made, gives a
 * second form of the subscript, so a second application shows in the glyph drawn. No feature names a
 * Lookup another names first, because readScriptsAndFeatures() keys a language system's features by
 * the first Lookup each names and drops the collisions (#245).
 *
 * - NotoSansTaiTham-ForwardShared, from NotoSansTaiTham-ReverseShared (#234), a subset of Noto Sans
 *   Tai Tham 2.002 retagged lana. Reaches _applyGSUBrulesSingly() through the South East Asian shaper,
 *   whose step (b) asks for 'locl ccmp' and step (d) for 'pref abvf blwf pstf'.
 * - Myanmar-ForwardShared, from Myanmar-Mym2Script (#239), a subset of the Padauk Book in
 *   packages/Myanmar-Bundle retagged mym2. Reaches applyGSUBfeaturesInTurn() through
 *   _applyGSUBrulesMyanmar(). It inherits the donor's malformed name ID 6, which TTFontFile answers
 *   with name ID 4 - see MyanmarStackedConsonantTest for why that name is the shape it is.
 * - NotoSansArabic-ForwardShared, from NotoSansArabic-AlternateCoverage, a subset of Noto Sans Arabic
 *   2.012. Reaches applyGSUBfeaturesInTurn() and featureStages() through the Arabic presentation pass,
 *   the one stage list a document can add to or subtract from. It states no joining features, so the
 *   passes before the presentation forms leave its glyphs alone.
 *
 * Noto's OFL 1.1 notice reserves no name, so a modified version may be named freely. Padauk's reserves
 * "Padauk", which clause 3 bars a modified version from using, so the Myanmar font carries a name of
 * its own and keeps the SIL copyright the licence requires.
 */
class SharedLookupPerStageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataRuns()
	{
		return [
			// The forms no code point names are given one in the Private Use Area, in glyph order:
			// uni1A20.blwf, uni1A21.blwf, uni1A63.locl, uni1A21.blwf2, uni1A22.blwf, uni1A20.blwf2
			'a Lookup locl and ccmp share, which the shaper applies in one call' => [
				'NotoSansTaiTham-ForwardShared-Synthetic',
				[0x1A21],
				[0xE001],
			],
			'the vowel the locl pass substitutes on its own' => [
				'NotoSansTaiTham-ForwardShared-Synthetic',
				[0x1A63],
				[0xE002],
			],
			'a Lookup pref names alone' => [
				'NotoSansTaiTham-ForwardShared-Synthetic',
				[0x1A22],
				[0xE004],
			],
			'a Lookup pref and blwf share, which the Universal Shaping Engine stages apart' => [
				'NotoSansTaiTham-ForwardShared-Synthetic',
				[0x1A20],
				[0xE005],
			],
			'all four of them in one run' => [
				'NotoSansTaiTham-ForwardShared-Synthetic',
				[0x1A20, 0x1A21, 0x1A22, 0x1A63],
				[0xE005, 0xE001, 0xE004, 0xE002],
			],
			// u1000.med, u1050.med and u1051.med carry a Private Use Area code point of their own in the
			// donor's cmap, so u1001.med, u1002.med and u1002.med2 are given one after them
			'Myanmar: a Lookup locl and ccmp share' => [
				'Myanmar-ForwardShared-Synthetic',
				[0x1000],
				[0xE000],
			],
			'Myanmar: a Lookup locl names alone' => [
				'Myanmar-ForwardShared-Synthetic',
				[0x1050],
				[0xE001],
			],
			'Myanmar: a Lookup pref names alone' => [
				'Myanmar-ForwardShared-Synthetic',
				[0x1001],
				[0xE003],
			],
			'Myanmar: a Lookup pref and blwf share, which the Myanmar plan stages apart' => [
				'Myanmar-ForwardShared-Synthetic',
				[0x1002],
				[0xE005],
			],
			'Myanmar: all four of them in one run' => [
				'Myanmar-ForwardShared-Synthetic',
				[0x1000, 0x1001, 0x1002, 0x1050],
				[0xE000, 0xE003, 0xE005, 0xE001],
			],
			// As Myanmar: the donor's cmap already names uni066E.init, uni066E.medi and uni066E.fina,
			// so uni0627.fina, dotbelowar, uni0628.a, uni0628.b, uni066E.a and uni066E.b follow them
			'Arabic: a Lookup liga and clig share' => [
				'NotoSansArabic-ForwardShared-Synthetic',
				[0x0628],
				[0xE005],
			],
			'Arabic: a Lookup liga names alone' => [
				'NotoSansArabic-ForwardShared-Synthetic',
				[0x0627],
				[0xE003],
			],
			'Arabic: a Lookup rlig names alone' => [
				'NotoSansArabic-ForwardShared-Synthetic',
				[0x08AD],
				[0xE004],
			],
			'Arabic: a Lookup rlig and calt share, which the Arabic plan stages apart' => [
				'NotoSansArabic-ForwardShared-Synthetic',
				[0x066E],
				[0xE008],
			],
			'Arabic: all four of them in one run, right to left' => [
				'NotoSansArabic-ForwardShared-Synthetic',
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
	 * The Arabic stages hold what a document turned off, which is the one case featureStages() has to
	 * split a list _applyTagSettings() has already been through: 'no-common-ligatures' takes liga and
	 * clig out of the last stage, where rlig and calt in the first two are left to run.
	 */
	public function testTheStagesOfTheArabicPassHoldWhatTheDocumentTurnedOff()
	{
		$this->assertSame(
			[0xE004, 0xE008, 0x0628, 0x0627],
			$this->drawn(
				'NotoSansArabic-ForwardShared-Synthetic',
				[0x0627, 0x0628, 0x066E, 0x08AD],
				'font-variant-ligatures: no-common-ligatures;'
			)
		);
	}

	/**
	 * @param string $font       The file name in tests/data/ttf, without its extension
	 * @param int[]  $codepoints
	 * @param string $style      What the paragraph asks for, as CSS
	 *
	 * @return int[] the code points of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($font, $codepoints, $style = '')
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$key = strtolower(str_replace('-', '', $font));
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$key => [
				'R' => $font . '.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => $key,
		]);
		$mpdf->WriteHTML('<p style="' . $style . '">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
