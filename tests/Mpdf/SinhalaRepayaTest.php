<?php

namespace Mpdf;

/**
 * A Sinhala repaya is formed, and drawn after the consonant it sits on (#287).
 *
 * The Indic reordering recognises a reph only where the font's rphf table names the Ra, and the parser
 * built that table for the nine Devanagari-family scripts alone, from two-glyph matches. Sinhala's
 * reph is explicit - Ra + AL-LAKUNA + ZWJ - so its fonts state rphf on three glyphs, and the table was
 * always empty. The rphf feature was then never masked onto the Ra, and abvs took the Ra and the
 * AL-LAKUNA instead: the wrong glyph, in front of the consonant.
 *
 *   $ hb-shape --font-file=kaputaunicode.ttf --unicodes=0DBB,0DCA,200D,0D9A --no-positions --no-clusters
 *   [uni0D9A|rapaya]
 *   $ hb-shape --font-file=kaputaunicode.ttf --unicodes=0DBB,0DCA,0D9A --no-positions --no-clusters
 *   [uni0DBB0DCA|uni0D9A]
 *
 * mPDF drew the second of these for both. The glyphs past U+E000 are the Private Use Area stand-ins
 * the fonts' unencoded glyphs are given: rapaya is E04B in Kaputa and E008 in the Noto subset, and the
 * abvs ligature of Ra and AL-LAKUNA is E048 and E00E.
 */
class SinhalaRepayaTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataRuns()
	{
		return [
			'Kaputa, the repaya on KA' => ['kaputaunicode', [0x0DBB, 0x0DCA, 0x200D, 0x0D9A], [0x0D9A, 0xE04B]],
			'Kaputa, the repaya on KA with a vowel sign after it' => ['kaputaunicode', [0x0DBB, 0x0DCA, 0x200D, 0x0D9A, 0x0DCF], [0x0D9A, 0xE04B, 0x0DCF]],
			'Kaputa, the repaya on KA with a vowel sign written before it' => ['kaputaunicode', [0x0DBB, 0x0DCA, 0x200D, 0x0D9A, 0x0DD9], [0x0DD9, 0x0D9A, 0xE04B]],
			'Kaputa, no ZWJ and so no repaya' => ['kaputaunicode', [0x0DBB, 0x0DCA, 0x0D9A], [0xE048, 0x0D9A]],
			'Noto subset, the repaya on KA' => ['sinhalasubset', [0x0DBB, 0x0DCA, 0x200D, 0x0D9A], [0x0D9A, 0xE008]],
			'Noto subset, no ZWJ and so no repaya' => ['sinhalasubset', [0x0DBB, 0x0DCA, 0x0D9A], [0xE00E, 0x0D9A]],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testTheRepayaIsDrawnWhereHarfBuzzDrawsIt($family, $codepoints, $expected)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['sinhalasubset' => ['R' => 'NotoSansSinhala-Subset.ttf', 'useOTL' => 0xFF]],
		]);
		$mpdf->WriteHTML('<p style="font-family:' . $family . '">' . $html . '</p>');

		$this->assertSame($expected, array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8'))));
	}

}
