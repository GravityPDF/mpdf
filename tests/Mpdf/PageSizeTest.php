<?php

namespace Mpdf;

/**
 * The size property of an @page rule. A page-size name sets the sheet, as a browser sets the paper, and two lengths
 * give a page box centred on the sheet, whose margins are measured from the page box once.
 */
class PageSizeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A page-size name gives one sheet of that size, with the page's margins inside it
	 *
	 * @dataProvider providerPageSizeName
	 */
	public function testPageSizeNameSetsTheSheet($css, $width, $height, $sideMargin)
	{
		$mpdf = $this->render('@page { ' . $css . ' }', '<h1>Invoice</h1><p>Thank you for your order.</p>');

		$this->assertCount(1, $mpdf->pages);
		$this->assertSame([$width, $height], $this->sheet($mpdf, 1));
		$this->assertSame([$sideMargin, $sideMargin], $this->sideMargins($mpdf, 1));
	}

	/**
	 * Sheets named with and without an orientation and margins
	 *
	 * @return array
	 */
	public function providerPageSizeName()
	{
		return [
			'A4 with margins' => ['size: A4; margin: 2cm', 210.0, 297.0, 20.0],
			'A4' => ['size: A4', 210.0, 297.0, 15.0],
			'letter' => ['size: letter; margin: 1in', 215.9, 279.4, 25.4],
			'A5 landscape' => ['size: A5 landscape', 210.0, 148.0, 15.0],
			'A4 landscape' => ['size: A4 landscape', 297.0, 210.0, 15.0],
		];
	}

	/**
	 * Two lengths with no margin give a page box centred on the sheet with the default margins inside it
	 */
	public function testPageBoxWithoutMarginKeepsTheDefaultMargins()
	{
		$mpdf = $this->render('@page { size: 100mm 150mm; }', '<p>Invoice</p>');

		$this->assertCount(1, $mpdf->pages);
		$this->assertSame([210.0, 297.0], $this->sheet($mpdf, 1));
		$this->assertSame([70.0, 70.0], $this->sideMargins($mpdf, 1));
	}

	/**
	 * The margins are the same when the style sheet is written on its own before the body
	 */
	public function testPageBoxMarginsAreTheSameWhenTheStyleSheetIsWrittenFirst()
	{
		$mpdf = new Mpdf();
		$mpdf->WriteHTML('@page { size: 100mm 150mm; }', HTMLParserMode::HEADER_CSS);
		$mpdf->WriteHTML('<p>Invoice</p><pagebreak /><p>Terms</p>');
		$mpdf->Close();

		$this->assertCount(2, $mpdf->pages);
		$this->assertSame([70.0, 70.0], $this->sideMargins($mpdf, 1));
		$this->assertSame([70.0, 70.0], $this->sideMargins($mpdf, 2));
	}

	/**
	 * A named page box with no margin of its own takes the default margins inside its own page box, not the outer
	 * width of the plain rule's page box as well
	 */
	public function testNamedPageBoxTakesTheDefaultMarginsInsideItsOwnBox()
	{
		$mpdf = $this->render(
			'@page { size: 100mm 150mm; } @page wide { size: 180mm 250mm; } div.wide { page: wide; }',
			'<p>Invoice</p><div class="wide">Terms</div>'
		);

		$this->assertCount(2, $mpdf->pages);
		$this->assertSame([70.0, 70.0], $this->sideMargins($mpdf, 1));
		$this->assertSame([30.0, 30.0], $this->sideMargins($mpdf, 2));
	}

	/**
	 * Render a style sheet and a body
	 *
	 * @param string $css
	 * @param string $html
	 * @return \Mpdf\Mpdf
	 */
	private function render($css, $html)
	{
		$mpdf = new Mpdf();
		$mpdf->WriteHTML('<style>' . $css . '</style>' . $html);
		$mpdf->Close();

		return $mpdf;
	}

	/**
	 * The width and height of a page's sheet as it is laid out, in millimetres to one place
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param int $page
	 * @return float[]
	 */
	private function sheet(Mpdf $mpdf, $page)
	{
		return [round($mpdf->pageDim[$page]['w'], 1), round($mpdf->pageDim[$page]['h'], 1)];
	}

	/**
	 * The left and right margins of a page from the sheet edge, in millimetres to one place
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param int $page
	 * @return float[]
	 */
	private function sideMargins(Mpdf $mpdf, $page)
	{
		return array_map(function ($margin) {
			return round($margin, 1);
		}, $mpdf->pageDim[$page]['sideMargins']);
	}

}
