<?php

namespace Mpdf;

/**
 * FreeSerif's v2 script tags use the pref, blwf and pstf Lookups written for the original Indic
 * tags, which state Consonant + Halant. mPDF tries a Halant after the base against them swapped, so
 * the font forms its post-base forms. HarfBuzz takes them as written and forms none:
 *
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0915,094D,0930 --no-positions --no-clusters
 *   [kadeva|virama|radeva]
 *
 * The glyphs pinned here are what hb-shape 14.3.1 and CoreText draw with FreeSerif under its original
 * script tags only, and with the v2 Lookups restated Halant + Consonant:
 *
 *   $ hb-shape --font-file=FreeSerif-v2-order.ttf --unicodes=0915,094D,0930 --no-positions --no-clusters
 *   [dev_ka__ra.rkrf]
 */
class IndicOldOrderPostBaseFormsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Runs with a consonant after the base that FreeSerif gives a post-base form, keyed by the glyphs
	 * the font forms for them. Code points from 0xE000 are the Private Use stand-ins mPDF gives glyphs
	 * with no Unicode value of their own.
	 *
	 * @return array[] code points in, code points drawn
	 */
	public function dataRuns()
	{
		return [
			'Devanagari KA VIRAMA RA: dev_ka__ra.rkrf' => [
				[0x0915, 0x094D, 0x0930],
				[0xE952],
			],
			'Bengali KA VIRAMA YA: bn_ka, bn_yaphala' => [
				[0x0995, 0x09CD, 0x09AF],
				[0x0995, 0xE34C],
			],
			'Gurmukhi PA VIRAMA RA: pa_gur, gur_ra.blwf' => [
				[0x0A2A, 0x0A4D, 0x0A30],
				[0x0A2A, 0xE808],
			],
			'Gujarati KA VIRAMA RA: guj_kra.vatu' => [
				[0x0A95, 0x0ACD, 0x0AB0],
				[0xE850],
			],
			'Malayalam KA VIRAMA LA: mal_k1l3' => [
				[0x0D15, 0x0D4D, 0x0D32],
				[0xE008],
			],
		];
	}

	/**
	 * FreeSerif forms the post-base form its Consonant + Halant Lookups describe.
	 *
	 * @dataProvider dataRuns
	 */
	public function testHalantAfterTheBaseMeetsAConsonantHalantLookup($codepoints, $expected)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:freeserif">' . $html . '</p>');

		$this->assertSame($expected, $mpdf->drawnCodepoints(0));
	}

}
