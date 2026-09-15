<?php

namespace Mpdf;

/**
 * A cursive letter's positional form need not be one glyph. A font may state it as a dotless base and
 * the dots to draw on it, and the Nastaliq and Naskh faces - Katibeh, Mirza, Aref Ruqaa, Noto Nastaliq
 * Urdu - write most of their initial and medial forms that way. rtlSUB holds such a form as one
 * space-separated string, and Shaper\Arabic read the whole of it through hexdec(), so '0E01D 0FBB3'
 * became code point 60,160,015,283 and the letter was drawn as that.
 *
 * It cost more than the letter. Every character the shaping ends with goes into the font subset, and
 * FontWriter walks the /W array from the first character to the highest one the subset covers - so a
 * hundred characters of Katibeh spent two and a half minutes in Output() counting to sixty billion.
 * That is GravityPDF/mpdf#115, which the second test below pins.
 */
class MultipleFormTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+06CC ARABIC LETTER FARSI YEH, dual-joining, and the letter the font states in two glyphs */
	const FARSI_YEH = 0x06CC;

	/** The dotless base of its initial form */
	const YEH_INIT = 0xE003;

	/** The two dots drawn under that base, which the same substitution names */
	const YEH_INIT_DOTS = 0xE007;

	/** Its final form, which the font states as one glyph */
	const YEH_FINA = 0xE005;

	/**
	 * Two dual-joining letters: the first takes an initial form, the last a final one. The initial
	 * form is two glyphs and the final one is one, so two characters draw three glyphs.
	 *
	 * Text is drawn in visual order, which for Arabic is the reverse of the order it is written in.
	 */
	public function testAFormOfSeveralGlyphsDrawsAllOfThem()
	{
		$mpdf = $this->render([self::FARSI_YEH, self::FARSI_YEH]);
		$drawn = unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8'));

		$this->assertSame(
			[self::YEH_INIT, self::YEH_INIT_DOTS, self::YEH_FINA],
			array_reverse($drawn)
		);
	}

	/**
	 * Nothing in the subset is above the Private Use Area the parser maps the unmapped glyphs into,
	 * which is as far as the /W array is walked.
	 */
	public function testTheSubsetHoldsNoCharacterThePdfCannotState()
	{
		$subset = $this->render([self::FARSI_YEH, self::FARSI_YEH])->fonts['multipleform']['subset'];

		$this->assertLessThanOrEqual(0xF8FF, max($subset));
		$this->assertContains(self::YEH_INIT_DOTS, $subset);
	}

	/**
	 * Draw the characters in Noto Sans Arabic cut down to the one letter, whose initial, medial and
	 * final forms are written as Multiple Substitutions: the first two name two glyphs and the last
	 * names one, which is the shape the Nastaliq faces have and the corpus otherwise has nowhere.
	 *
	 * @param int[] $codepoints
	 *
	 * @return TextRecordingMpdf the document, having drawn them
	 */
	private function render($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['multipleform' => [
				'R' => 'NotoSansArabic-MultipleForm-Subset.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'multipleform',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return $mpdf;
	}

}
