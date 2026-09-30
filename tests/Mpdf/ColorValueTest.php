<?php

namespace Mpdf;

/**
 * Colour values mPDF read wrongly: hex colours with an alpha, channels outside their range, and rebeccapurple
 */
class ColorValueTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @dataProvider colorsProvider
	 *
	 * @param string   $css      Declarations for a paragraph
	 * @param string[] $drawn    Operators and opacities the document must contain
	 * @param string[] $notDrawn Ones it must not
	 */
	public function testDrawsTheColour($css, array $drawn, array $notDrawn = [])
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($css) {
			$mpdf->WriteHTML('<p style="' . $css . '">Text</p>');
		});

		foreach ($drawn as $needle) {
			$this->assertStringContainsString($needle, $pdf);
		}

		foreach ($notDrawn as $needle) {
			$this->assertStringNotContainsString($needle, $pdf);
		}
	}

	/**
	 * Each case: the declarations, what they must draw, and what they must not
	 *
	 * @return array[]
	 */
	public function colorsProvider()
	{
		return [
			'#rgba in the background shorthand' => ['background: #f008', ['1.000 0.000 0.000 rg', '/ca 0.53']],
			'#rrggbbaa in the background shorthand' => ['background: #0000ff80', ['0.000 0.000 1.000 rg', '/ca 0.5']],
			'#rrggbbaa in background-color' => ['background-color: #0000ff80', ['0.000 0.000 1.000 rg', '/ca 0.5']],
			'a fractional channel' => ['color: rgb(255.5, 0, 0)', ['1.000 0.000 0.000 rg']],
			'channels past each end' => ['color: rgb(300, -20, 0)', ['1.000 0.000 0.000 rg']],
			'an alpha above 1' => ['background-color: rgba(0, 0, 255, 1.5)', ['0.000 0.000 1.000 rg'], ['/ca 1.']],
			'rebeccapurple' => ['color: rebeccapurple', ['0.400 0.200 0.600 rg']],
		];
	}

}
