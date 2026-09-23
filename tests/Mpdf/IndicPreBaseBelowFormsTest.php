<?php

namespace Mpdf;

/**
 * blwf is applied before the base as well as after it, in the scripts HarfBuzz configures with
 * BLWF_MODE_PRE_AND_POST - every Indic script but Telugu and Kannada.
 *
 * FreeSerif's blwf Lookups are written Consonant-Halant under the v2 tags. After the base mPDF
 * swaps a Halant-Consonant pair to meet them; before the base the text is already in that order.
 * `hb-shape` 14.3.1:
 *
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0915,094D,0930,094D,0915 --no-positions --no-clusters
 *   [dev_ka.half|dev_rakaar|kadeva]
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0A30,0A4D,0A15 --no-positions --no-clusters
 *   [gur_ra.blwf|ka_gur]
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0D30,0D4D,0D32,0D4D,0D15 --no-positions --no-clusters
 *   [mal_r3xx|mal_l4|ka_mal]
 */
class IndicPreBaseBelowFormsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Runs whose consonants before the base take a below-base form, and what FreeSerif draws for
	 * them. Code points from 0xE000 are the Private Use stand-ins mPDF gives glyphs with no Unicode
	 * value of their own.
	 *
	 * @return array[] code points in, code points drawn
	 */
	public function dataRuns()
	{
		return [
			'Devanagari KA VIRAMA RA VIRAMA KA: half KA, rakaar, KA' => [
				[0x0915, 0x094D, 0x0930, 0x094D, 0x0915],
				[0xE8C0, 0xE777, 0x0915],
			],
			'Gurmukhi RA VIRAMA KA: below-base RA, KA' => [
				[0x0A30, 0x0A4D, 0x0A15],
				[0xE808, 0x0A15],
			],
			'Malayalam RA VIRAMA LA VIRAMA KA: RA, below-base LA, KA' => [
				[0x0D30, 0x0D4D, 0x0D32, 0x0D4D, 0x0D15],
				[0xE319, 0xE2FE, 0x0D15],
			],
		];
	}

	/**
	 * FreeSerif draws each run as hb-shape does.
	 *
	 * @dataProvider dataRuns
	 */
	public function testConsonantsBeforeTheBaseTakeTheirBelowBaseForms($codepoints, $expected)
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
