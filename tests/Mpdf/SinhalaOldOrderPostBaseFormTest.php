<?php

namespace Mpdf;

/**
 * A Sinhala font whose blwf Lookup states RA + AL-LAKUNA, the Consonant + Halant order of the original
 * Indic script tags, forms its below-base RA as a font stating AL-LAKUNA + RA does.
 *
 * The fixture is NotoSansSinhala-Subset's glyphs, cmap and GDEF with a GSUB written by hand in
 * fontTools 4.59.2: one blwf Lookup under sinh, uni0DBB uni0DCA -> glyph00022 (E009 here). HarfBuzz
 * does not swap, so it draws the fixture's runs unchanged. The glyphs expected are what hb-shape 14.3.1
 * draws from a copy whose Lookup states uni0DCA uni0DBB instead:
 *
 *   $ hb-shape --font-file=NewOrder.ttf --unicodes=0D9A,200D,0DCA,0DBB --no-positions --no-clusters
 *   [gid2|gid1|gid22]
 *
 * where gid1 is the ZWJ, drawn as nothing.
 */
class SinhalaOldOrderPostBaseFormTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Runs with an AL-LAKUNA and RA after the base, keyed by what is drawn.
	 *
	 * @return array[] code points in, code points drawn
	 */
	public function dataRuns()
	{
		return [
			'KA ZWJ AL-LAKUNA RA: the below-base RA' => [
				[0x0D9A, 0x200D, 0x0DCA, 0x0DBB],
				[0x0D9A, 0xE009],
			],
			'LA ZWJ AL-LAKUNA RA: the below-base RA' => [
				[0x0DBD, 0x200D, 0x0DCA, 0x0DBB],
				[0x0DBD, 0xE009],
			],
			'KA ZWJ AL-LAKUNA RA AA: the below-base RA, then the vowel sign' => [
				[0x0D9A, 0x200D, 0x0DCA, 0x0DBB, 0x0DCF],
				[0x0D9A, 0xE009, 0x0DCF],
			],
			'KA AL-LAKUNA ZWJ RA: the ZWJ parts them, and nothing is formed' => [
				[0x0D9A, 0x0DCA, 0x200D, 0x0DBB],
				[0x0D9A, 0x0DCA, 0x0DBB],
			],
		];
	}

	/**
	 * The AL-LAKUNA after the base is tried against the RA + AL-LAKUNA ligature swapped.
	 *
	 * @dataProvider dataRuns
	 */
	public function testAlLakunaAfterTheBaseMeetsAConsonantHalantLookup($codepoints, $expected)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['sinhalaoldorder' => ['R' => 'NotoSansSinhala-OldOrderBlwf-Synthetic.ttf', 'useOTL' => 0xFF]],
		]);
		$mpdf->WriteHTML('<p style="font-family:sinhalaoldorder">' . $html . '</p>');

		$this->assertSame($expected, $mpdf->drawnCodepoints(0));
	}

	/**
	 * The shared virama list holds the virama the shaper's configuration gives each script from
	 * Devanagari to Sinhala, and no other.
	 */
	public function testTheViramaListMatchesTheShaperConfiguration()
	{
		$expected = [];
		foreach (range(Unicode\Ucdn::SCRIPT_DEVANAGARI, Unicode\Ucdn::SCRIPT_SINHALA) as $script) {
			$expected[sprintf('%05X', Shaper\Indic::$indic_configs[$script][1])] = true;
		}

		$this->assertSame($expected, Shaper\Indic::$viramas);
	}

}
