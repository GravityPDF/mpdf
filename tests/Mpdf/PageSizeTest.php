<?php

namespace Mpdf;

/**
 * The size property of an @page rule. A page-size name sets the sheet and an orientation turns it. Two lengths give a
 * page box centred on the sheet, with its margins measured from the box.
 */
class PageSizeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The first page gets one sheet of the size and orientation the size property gives, with the page's margins
	 * inside it
	 *
	 * @dataProvider providerFirstSheet
	 */
	public function testFirstSheet($format, $css, $width, $height, $sideMargin)
	{
		$mpdf = $this->render('@page { ' . $css . ' }', '<h1>Invoice</h1><p>Thank you for your order.</p>', $format);

		$this->assertCount(1, $mpdf->pages);
		$this->assertSame([$width, $height], $this->sheet($mpdf, 1));
		$this->assertSame([$sideMargin, $sideMargin], $this->sideMargins($mpdf, 1));
	}

	/**
	 * Page-size names, orientations and page boxes, set on portrait and landscape documents
	 *
	 * @return array
	 */
	public function providerFirstSheet()
	{
		return [
			'A4 with margins' => ['A4', 'size: A4; margin: 2cm', 210.0, 297.0, 20.0],
			'A4' => ['A4', 'size: A4', 210.0, 297.0, 15.0],
			'letter' => ['A4', 'size: letter; margin: 1in', 215.9, 279.4, 25.4],
			'A5 landscape' => ['A4', 'size: A5 landscape', 210.0, 148.0, 15.0],
			'A4 landscape' => ['A4', 'size: A4 landscape', 297.0, 210.0, 15.0],
			'landscape' => ['A4', 'size: landscape', 297.0, 210.0, 15.0],
			'landscape with margins' => ['A4', 'size: landscape; margin: 1cm', 297.0, 210.0, 10.0],
			'portrait on a landscape document' => ['A4-L', 'size: portrait', 210.0, 297.0, 15.0],
			'auto on a landscape document' => ['A4-L', 'size: auto', 297.0, 210.0, 15.0],
			'percentage margins on a landscape document' => ['A4-L', 'margin: 10%', 297.0, 210.0, 29.7],
			'page box with no margin' => ['A4', 'size: 100mm 150mm', 210.0, 297.0, 70.0],
			'page box wider than tall' => ['A4', 'size: 150mm 100mm', 297.0, 210.0, 88.5],
			'page box wider than the unturned sheet' => ['A4', 'size: 250mm 150mm', 297.0, 210.0, 38.5],
			'page box taller than wide on a landscape document' => ['A4-L', 'size: 100mm 150mm', 210.0, 297.0, 70.0],
		];
	}

	/**
	 * Every page has the same sheet and margins when the style sheet is written on its own before the body
	 *
	 * @dataProvider providerStyleSheetWrittenFirst
	 */
	public function testStyleSheetWrittenFirst($css, $width, $height, $sideMargin)
	{
		$mpdf = new Mpdf();
		$mpdf->WriteHTML('@page { ' . $css . ' }', HTMLParserMode::HEADER_CSS);
		$mpdf->WriteHTML('<p>Invoice</p><pagebreak /><p>Terms</p>');
		$mpdf->Close();

		$this->assertCount(2, $mpdf->pages);
		foreach ([1, 2] as $page) {
			$this->assertSame([$width, $height], $this->sheet($mpdf, $page));
			$this->assertSame([$sideMargin, $sideMargin], $this->sideMargins($mpdf, $page));
		}
	}

	/**
	 * An orientation and a page box written as a style sheet of their own
	 *
	 * @return array
	 */
	public function providerStyleSheetWrittenFirst()
	{
		return [
			'landscape' => ['size: landscape', 297.0, 210.0, 15.0],
			'page box' => ['size: 100mm 150mm', 210.0, 297.0, 70.0],
		];
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
	 * @param string $format The document's format, as the format option takes it
	 * @return \Mpdf\Mpdf
	 */
	private function render($css, $html, $format = 'A4')
	{
		$mpdf = new Mpdf(['format' => $format]);
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
