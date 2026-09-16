<?php

namespace Mpdf;

/**
 * A Single Substitution Format 1 states its replacement as a number to add to the glyph id, and the
 * spec adds it modulo 65536 - which is how a font names a glyph below the one it covers, or above
 * the end of the range. Cactus Classical Serif reaches its extended em dash with -7504 from glyph
 * 504, Noto Sans SignWriting its alternate forms with -28287 from glyphs 8 to 27, and the three
 * Chiron families wrap both ways.
 *
 * Both halves of mPDF added without the modulo. The parser then read glyphToChar outside its keys
 * and recorded the rule as substituting U+0000; the shaper read glyphIDtoUni past its end and drew
 * nothing at all - `font-variant-alternates: historical-forms` over U+3127 in any of the three
 * Chiron faces lost the character.
 */
class DeltaGlyphIdTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+0627 ARABIC LETTER ALEF, the character the font's one lookup covers */
	const ALEF = 0x0627;

	/** U+0628 ARABIC LETTER BEH, which the lookup does not cover */
	const BEH = 0x0628;

	/**
	 * The glyph the wrap lands on. It is the only one in the font with no character of its own, so
	 * the parser maps it into the Private Use Area.
	 */
	const ALEF_FINA = 0xE000;

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code
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
			'fontdata' => ['gsub11wrap' => [
				'R' => 'NotoSansArabic-GSUB11Wrap-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'gsub11wrap',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * The lookup covers glyph 32770 and adds 32767, so the sum is 65537 and the substitute is glyph
	 * 1. Without the modulo the letter drew as nothing at all.
	 */
	public function testTheGlyphTheDeltaWrapsOntoIsDrawn()
	{
		$this->assertSame([self::ALEF_FINA], $this->drawn([self::ALEF]));
	}

	/**
	 * Text is drawn in visual order, which for Arabic is the reverse of the order it is written in.
	 * The letter the lookup does not cover is left where it is.
	 */
	public function testALetterTheLookupDoesNotCoverIsLeftAlone()
	{
		$this->assertSame([self::ALEF_FINA, self::BEH], $this->drawn([self::BEH, self::ALEF]));
	}

}
