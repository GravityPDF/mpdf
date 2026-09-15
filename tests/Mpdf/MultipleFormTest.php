<?php

namespace Mpdf;

/**
 * A cursive letter's positional form need not be one glyph. A font is free to state it as a dotless
 * base and the dots to draw under it, and the Nastaliq and Naskh faces - Katibeh, Mirza, Aref Ruqaa,
 * Estedad, Noto Nastaliq Urdu - write most of their initial and medial forms that way.
 *
 * Arabic and Syriac reach their forms through Shaper\Arabic rather than through GSUB, and it wrote
 * whatever the font named into the one character it stood at:
 *
 *     $info[$i]['uni'] = hexdec($ra[$i][0]);
 *
 * where $ra[$i][0] is '0E01D 0FBB3' for a form of two glyphs. hexdec() stops at nothing and ignores
 * the space, so the character came out as code point 60,160,015,283 - which is no glyph the font
 * has, and which the letter was then drawn as.
 *
 * It cost more than the letter. Every character the shaping ends with goes into the font subset, so
 * a code point of sixty billion became the highest the subset covered, and FontWriter walks the /W
 * array from the first character to that one: a hundred characters of Katibeh spent two and a half
 * minutes in Output() counting to sixty billion (GravityPDF/mpdf#115).
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
	 * form is two glyphs and the final one is one, so three glyphs are drawn for two characters.
	 *
	 * Before, the base and its dots were read as a single code point and the letter was lost.
	 */
	public function testAFormOfSeveralGlyphsDrawsAllOfThem()
	{
		$this->assertSame(
			[self::YEH_INIT, self::YEH_INIT_DOTS, self::YEH_FINA],
			$this->drawn([self::FARSI_YEH, self::FARSI_YEH])
		);
	}

	/**
	 * The subset holds the glyphs that were drawn and nothing above the Private Use Area the parser
	 * maps the unmapped glyphs into, which is what the /W array is walked to.
	 */
	public function testTheSubsetHoldsNoCharacterThePdfCannotState()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML($this->html([self::FARSI_YEH, self::FARSI_YEH]));

		$subset = $mpdf->fonts['multipleform']['subset'];

		$this->assertLessThanOrEqual(0xF8FF, max($subset));
		$this->assertContains(self::YEH_INIT_DOTS, $subset);
	}

	/**
	 * Text is drawn in visual order, which for Arabic is the reverse of the order it is written in.
	 *
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in logical order
	 */
	private function drawn($codepoints)
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML($this->html($codepoints));

		$drawn = unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8'));

		return array_reverse(array_values($drawn));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return string them as one paragraph
	 */
	private function html($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		return '<p>' . $html . '</p>';
	}

	/**
	 * Noto Sans Arabic cut down to the one letter, with its initial, medial and final forms written
	 * as Multiple Substitutions: the initial and medial forms name two glyphs and the final one names
	 * one, which is the shape the Nastaliq faces have and the corpus otherwise has nowhere.
	 */
	private function mpdf()
	{
		return new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['multipleform' => [
				'R' => 'NotoSansArabic-MultipleForm-Subset.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'multipleform',
		]);
	}

}
