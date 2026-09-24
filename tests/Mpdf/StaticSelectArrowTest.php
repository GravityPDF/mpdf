<?php

namespace Mpdf;

/**
 * A drop-down drawn into the page has a triangle for its arrow, a path in the text colour rather than a ZapfDingbats
 * glyph, so PDF/A and PDF/X documents draw it without a font they cannot embed (GravityPDF/mpdf#454)
 */
class StaticSelectArrowTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The grey drop-down button and the triangle filled on it: the button's x, y, width and height, the triangle's
	 * colour and its three corners
	 */
	const BUTTON = '/0\.745 g\n([\d.]+) ([\d.]+) ([\d.]+) -([\d.]+) re B \nq (.+?) ([\d.]+) ([\d.]+) m ([\d.]+) ([\d.]+) l ([\d.]+) ([\d.]+) l f Q\n/';

	/**
	 * The triangle points down from the middle of the button, half a font size wide, and nothing is drawn as text for it
	 */
	public function testTheArrowIsATriangleCentredOnTheButton()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'useActiveForms' => false]);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<form><select name="fruit" style="font-size: 10pt"><option>Apple</option><option>Banana</option></select></form>');
		$pdf = $this->output($mpdf);

		$this->assertSame(['Apple'], $mpdf->drawnText);
		$this->assertStringNotContainsString('ZapfDingbats', $pdf);

		$pages = $this->pages($pdf);
		$this->assertSame(1, preg_match(self::BUTTON, $pages[0], $button));
		list(, $x, $y, $w, $h, , $leftX, $leftY, $rightX, $rightY, $tipX, $tipY) = array_map('floatval', $button);

		$this->assertEqualsWithDelta($x + $w / 2, $tipX, 0.002);
		$this->assertEqualsWithDelta($x + $w / 2, ($leftX + $rightX) / 2, 0.002);
		$this->assertEqualsWithDelta($y - $h / 2, ($leftY + $tipY) / 2, 0.002);
		$this->assertSame($leftY, $rightY);
		$this->assertGreaterThan($tipY, $leftY);
		$this->assertEqualsWithDelta(5, $rightX - $leftX, 0.002);
	}

	/**
	 * The triangle is filled in the colour the select's text is written in, grey when it is disabled
	 *
	 * @dataProvider colours
	 *
	 * @param string $attributes
	 * @param string $colour the fill operator the text and the triangle are drawn with
	 */
	public function testTheArrowTakesTheTextColour($attributes, $colour)
	{
		$pages = $this->pages($this->render('<form><select name="fruit" ' . $attributes . '><option>Apple</option></select></form>', ['useActiveForms' => false]));

		$this->assertStringContainsString('q ' . $colour . '  0 Tr BT', $pages[0]);
		$this->assertSame(1, preg_match(self::BUTTON, $pages[0], $button));
		$this->assertSame($colour, $button[5]);
	}

	/**
	 * Selects styled with a colour, disabled, and left as they are
	 *
	 * @return string[][]
	 */
	public function colours()
	{
		return [
			'plain' => ['', '0.000 g'],
			'coloured' => ['style="color: #c00000"', '0.753 0.000 0.000 rg'],
			'disabled' => ['disabled', '0.498 g'],
		];
	}

	/**
	 * A PDF/A or PDF/X document with a select is written without warnings where mPDF is not allowed to fix it, and
	 * draws the same triangle an ordinary document does
	 *
	 * @dataProvider strictDocuments
	 *
	 * @param mixed[] $config
	 */
	public function testAStrictPdfaOrPdfxDocumentWithASelectIsWritten($config)
	{
		$html = '<form><select name="fruit"><option>Apple</option><option>Banana</option></select></form>';

		$mpdf = $this->mpdf($config + ['mode' => '', 'useActiveForms' => false]);
		$mpdf->SetTitle('Select');
		$mpdf->WriteHTML($html);
		$pages = $this->pages($this->output($mpdf));

		$this->assertSame([], $mpdf->PDFAXwarnings);
		$this->assertSame(1, preg_match(self::BUTTON, $pages[0], $strict));

		$plain = $this->pages($this->render($html, ['mode' => '', 'useActiveForms' => false]));
		$this->assertSame(1, preg_match(self::BUTTON, $plain[0], $button));
		$this->assertSame($button, $strict);
	}

	/**
	 * PDF/A and PDF/X documents mPDF may only warn about, not fix
	 *
	 * @return mixed[][]
	 */
	public function strictDocuments()
	{
		return [
			'PDF/A-1b' => [['PDFA' => true, 'PDFAauto' => false, 'PDFAversion' => '1-B']],
			'PDF/A-3b' => [['PDFA' => true, 'PDFAauto' => false, 'PDFAversion' => '3-B']],
			'PDF/X-1a' => [['PDFX' => true, 'PDFXauto' => false, 'PDFXversion' => '1a']],
			'PDF/X-4' => [['PDFX' => true, 'PDFXauto' => false]],
		];
	}

}
