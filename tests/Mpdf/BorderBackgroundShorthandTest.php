<?php

namespace Mpdf;

/**
 * The border and background shorthands read their parts in any order, and are dropped when a part is not one of them
 */
class BorderBackgroundShorthandTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A 2mm red side, as the content stream strokes it
	 */
	const RED_2MM_SIDE = "5.669 w\n0.800 0.000 0.000 RG";

	/**
	 * @dataProvider borderOrderProvider
	 *
	 * @param string $css Declarations for a block
	 * @param int    $sides How many sides are drawn 2mm and red
	 */
	public function testDrawsTheBorderWhateverTheOrderOfItsParts($css, $sides)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($css) {
			$mpdf->WriteHTML('<div style="' . $css . '">Text</div>');
		});

		$this->assertSame($sides, substr_count($pdf, self::RED_2MM_SIDE));
	}

	/**
	 * Each case: the declarations, and how many 2mm red sides they draw
	 *
	 * @return array[]
	 */
	public function borderOrderProvider()
	{
		return [
			'width style colour' => ['border: 2mm solid #c00', 4],
			'style colour width' => ['border: solid #c00 2mm', 4],
			'width colour style' => ['border: 2mm #c00 solid', 4],
			'colour width style' => ['border: #c00 2mm solid', 4],
			'style width colour' => ['border: solid 2mm #c00', 4],
			'one side' => ['border-bottom: solid rgb(204 0 0) 2mm', 1],
		];
	}

	/**
	 * A border with a colour mPDF cannot read is dropped, so the earlier rule's border is drawn
	 */
	public function testKeepsTheEarlierBorderForAnInvalidOne()
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<style>div { border: 2mm solid #c00 } div.x { border: 1px solid bogus }</style><div class="x">Text</div>');
		});

		$this->assertSame(4, substr_count($pdf, self::RED_2MM_SIDE));
	}

	/**
	 * A colour after the image is painted under it, and the image stays at the top left
	 */
	public function testPaintsAColourGivenAfterTheImage()
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; background: url(' . $this->backgroundImage() . ') no-repeat #0f0">Text</div>');
		});

		$this->assertStringContainsString('0.000 1.000 0.000 rg', $pdf);
		$this->assertStringContainsString('/Matrix [1 0 0 1 42.520 ', $pdf);
		$this->assertStringContainsString('/BBox [0 0 75.000 87.000]', $pdf);
	}

	/**
	 * The size after the slash covers the box
	 */
	public function testSizesTheImageAfterTheSlash()
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; background: url(' . $this->backgroundImage() . ') no-repeat center / cover">Text</div>');
		});

		// 60mm wide, and as tall as the 100 by 116 pixel image is at that width
		$this->assertStringContainsString('/BBox [0 0 170.079 197.291]', $pdf);
	}

	/**
	 * A background with a colour mPDF cannot read is dropped, so the earlier rule's colour is painted
	 */
	public function testKeepsTheEarlierBackgroundForAnInvalidOne()
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<style>div { background: #0f0 } div.x { background: url(' . $this->backgroundImage() . ') bogus }</style><div class="x">Text</div>');
		});

		$this->assertStringContainsString('0.000 1.000 0.000 rg', $pdf);
		$this->assertStringNotContainsString('/Pattern', $pdf);
	}

}
