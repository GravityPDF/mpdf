<?php

namespace Mpdf;

/**
 * Which of the three forms Syriac states for the Alaph it takes is read off the characters as written,
 * and not off the glyphs a substitution has left in their place (#228).
 *
 * NotoSansSyriac-AlaphCcmp-Synthetic takes dalath and gamal garshuni apart in 'ccmp', into the rasm
 * each shares with its dotless letter and the dot that distinguishes it, the way Noto Sans Arabic
 * takes beh apart. A rasm is unencoded, so mPDF maps it into the Private Use Area, and a Private Use
 * codepoint is in neither joining table and is none of the three letters fin3 turns on: the Alaph
 * after a letter taken apart was left with no form at all.
 *
 * `hb-shape` 14.3.1 draws the glyphs these tests expect, resolving the Alaph's action in
 * arabic_joining() before it applies GSUB.
 */
class DecomposedSyriacAlaphTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ALAPH = 0x0710;

	const BETH = 0x0712;

	/** Dual-joining, and taken apart in 'ccmp' into the gamal rasm and the dot above it */
	const GAMAL_GARSHUNI = 0x0714;

	/** Right-joining, and taken apart into the dotless dalath rish rasm and the dot below it */
	const DALATH = 0x0715;

	/** Right-joining like dalath, and left whole, so the same Alaph is reached without a 'ccmp' */
	const RISH = 0x072A;

	const QUSHSHAYA = 0x0741;

	const RUKKAKHA = 0x0742;

	/** The forms and the rasms have no codepoint of their own, so they are mapped into the Private Use Area */
	const ALAPH_FIN3 = 0xE000;

	const ALAPH_FIN2 = 0xE001;

	const ALAPH_MED2 = 0xE003;

	const BETH_INITIAL = 0xE006;

	const GAMAL_RASM = 0xE007;

	const GAMAL_RASM_INITIAL = 0xE00A;

	const DALATH_RASM = 0xE00B;

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
			'fontdata' => ['notosanssyriacalaphccmpsynthetic' => [
				'R' => 'NotoSansSyriac-AlaphCcmp-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosanssyriacalaphccmpsynthetic',
		]);
		$mpdf->WriteHTML('<p dir="rtl">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	public function dataWords()
	{
		return [
			'fin3 after a letter taken apart' => [
				[self::DALATH, self::ALAPH],
				[self::ALAPH_FIN3, self::RUKKAKHA, self::DALATH_RASM],
			],
			'and after the same letter left whole' => [
				[self::RISH, self::ALAPH],
				[self::ALAPH_FIN3, self::RISH],
			],
			'med2 between two letters taken apart' => [
				[self::GAMAL_GARSHUNI, self::ALAPH, self::GAMAL_GARSHUNI],
				[
					self::QUSHSHAYA, self::GAMAL_RASM,
					self::ALAPH_MED2,
					self::QUSHSHAYA, self::GAMAL_RASM_INITIAL,
				],
			],
			'and between two left whole' => [
				[self::BETH, self::ALAPH, self::BETH],
				[self::BETH, self::ALAPH_MED2, self::BETH_INITIAL],
			],
			// mPDF draws fin2 wherever an Alaph ends the word after a letter that joins forwards, which
			// for this position HarfBuzz resolves as the plain final form. That is a separate defect,
			// GravityPDF/mpdf#244; what these two cases hold is that the Alaph reaches the same form
			// whether or not 'ccmp' has taken the letter before it apart.
			'fin2 after a letter taken apart' => [
				[self::GAMAL_GARSHUNI, self::ALAPH],
				[self::ALAPH_FIN2, self::QUSHSHAYA, self::GAMAL_RASM_INITIAL],
			],
			'and after a letter left whole' => [
				[self::BETH, self::ALAPH],
				[self::ALAPH_FIN2, self::BETH_INITIAL],
			],
			// Syriac states no form for an Alaph inside a word after one of the three letters fin3 turns
			// on, and no font states an isolated one, so the Alaph stands as it was written
			'an Alaph inside a word after a letter taken apart keeps no form' => [
				[self::DALATH, self::ALAPH, self::BETH],
				[self::BETH, self::ALAPH, self::RUKKAKHA, self::DALATH_RASM],
			],
		];
	}

	/**
	 * @dataProvider dataWords
	 */
	public function testAnAlaphAfterALetterTakenApartInCcmpTakesTheFormTheLetterCalledFor($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

}
