<?php

namespace Mpdf;

/**
 * Which form an Arabic letter takes is read off the characters as written, and not off the glyphs a
 * substitution has left in their place (#209).
 *
 * NotoSansArabic-Joining-Subset takes beh apart in 'ccmp', into the rasm it shares with the dotless
 * beh and the dot below it, and states the initial, medial and final forms of the rasm. The rasm is
 * unencoded, so mPDF maps it into the Private Use Area, and a Private Use codepoint is in none of the
 * joining tables: resolving joining after 'ccmp' left every beh of a word isolated, and the letter
 * after one isolated too, because a rasm joins nothing to read.
 *
 * `hb-shape` 14.3.1 draws the glyphs these tests expect, joining as HarfBuzz does in setup_masks()
 * before it applies GSUB.
 */
class DecomposedArabicJoiningTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const BEH = 0x0628;

	const ALEF = 0x0627;

	/** The forms have no codepoint of their own, so they are mapped into the Private Use Area */
	const ALEF_FINAL = 0xE000;

	const DOTLESS_BEH = 0xE001;

	const DOTLESS_BEH_FINAL = 0xE002;

	const DOTLESS_BEH_MEDIAL = 0xE003;

	const DOTLESS_BEH_INITIAL = 0xE004;

	const DOT_BELOW = 0xE005;

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
			'fontdata' => ['notosansarabicjoiningsubset' => [
				'R' => 'NotoSansArabic-Joining-Subset.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansarabicjoiningsubset',
		]);
		$mpdf->WriteHTML('<p dir="rtl">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	public function dataWords()
	{
		return [
			'a letter taken apart joins on both sides' => [
				[self::BEH, self::BEH, self::BEH],
				[
					self::DOT_BELOW, self::DOTLESS_BEH_FINAL,
					self::DOT_BELOW, self::DOTLESS_BEH_MEDIAL,
					self::DOT_BELOW, self::DOTLESS_BEH_INITIAL,
				],
			],
			'and on one side where the word is two letters' => [
				[self::BEH, self::BEH],
				[self::DOT_BELOW, self::DOTLESS_BEH_FINAL, self::DOT_BELOW, self::DOTLESS_BEH_INITIAL],
			],
			'the letter after one joins to it' => [
				[self::BEH, self::ALEF],
				[self::ALEF_FINAL, self::DOT_BELOW, self::DOTLESS_BEH_INITIAL],
			],
			'a letter after one that joins on neither side stands alone' => [
				[self::ALEF, self::BEH],
				[self::DOT_BELOW, self::DOTLESS_BEH, self::ALEF],
			],
			'a word of one letter stands alone' => [
				[self::BEH],
				[self::DOT_BELOW, self::DOTLESS_BEH],
			],
		];
	}

	/**
	 * @dataProvider dataWords
	 */
	public function testALetterTakenApartInCcmpJoinsAsTheCharacterItWasWrittenAs($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

}
