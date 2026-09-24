<?php

namespace Mpdf\Ua;

/**
 * Right-to-left text is drawn in visual order, so each run bidi reordered is wrapped in a Span
 * whose /ActualText gives its characters in the order they were written (Matterhorn 09-001).
 *
 * @group pdfua
 */
class RightToLeftActualTextTest extends PdfUaTestCase
{

	/**
	 * A right-to-left paragraph is read back as written, not as drawn.
	 */
	public function testRightToLeftParagraphCarriesItsLogicalText()
	{
		$pdf = $this->getOutput($this->makeMpdf(), '<p dir="rtl" lang="he">שלום עולם</p>');

		$this->assertContains($this->actualTextOf('שלום עולם'), $this->actualTexts($pdf));
	}

	/**
	 * Brackets drawn mirrored are given back as they were written.
	 */
	public function testMirroredBracketsAreGivenBackAsWritten()
	{
		$pdf = $this->getOutput($this->makeMpdf(), '<p dir="rtl" lang="he">שלום (עולם)</p>');

		$this->assertContains($this->actualTextOf('שלום (עולם)'), $this->actualTexts($pdf));
	}

	/**
	 * Numbers inside right-to-left text keep their place in the logical order.
	 */
	public function testNumbersKeepTheirLogicalPlace()
	{
		$pdf = $this->getOutput($this->makeMpdf(), '<p dir="rtl" lang="he">עמוד 12 מתוך 30</p>');

		$this->assertContains($this->actualTextOf('עמוד 12 מתוך 30'), $this->actualTexts($pdf));
	}

	/**
	 * In a left-to-right paragraph only the right-to-left run is wrapped, inside the marked
	 * content of the Span that holds it.
	 */
	public function testOnlyTheRightToLeftRunOfAMixedLineIsWrapped()
	{
		$pdf = $this->getOutput($this->makeMpdf(), '<p>Hello <span lang="he">שלום עולם</span> end</p>');

		$this->assertSame([$this->actualTextOf('שלום עולם')], $this->actualTexts($pdf));
		$this->assertMatchesRegularExpression(
			'/\/Span <<\/MCID \d+>> BDC\s+q [^\n]*\/Span <<\/ActualText <FEFF[0-9A-F]+>>> BDC\s*BT [^\n]* ET EMC Q\s+EMC/',
			$pdf
		);
	}

	/**
	 * Text drawn through Text(), as a rotated table cell is, is wrapped as well.
	 */
	public function testRotatedTableCellCarriesItsLogicalText()
	{
		$pdf = $this->getOutput(
			$this->makeMpdf(),
			'<table><tr><td style="text-rotate: 90" dir="rtl" lang="he">שלום עולם</td></tr></table>'
		);

		$this->assertContains($this->actualTextOf('שלום עולם'), $this->actualTexts($pdf));
	}

	/**
	 * Shaped Arabic is given back as the letters written: joining forms as their letters and a
	 * lam-alef ligature as the lam and alef it was formed from.
	 */
	public function testShapedArabicIsGivenBackAsItsLetters()
	{
		$mpdf = $this->makeMpdf([
			'fontdata' => [
				'dejavusans' => [
					'R' => 'DejaVuSans.ttf',
					'useOTL' => 0xFF,
				],
			],
		]);
		$pdf = $this->getOutput($mpdf, '<p dir="rtl" lang="ar" style="font-family: dejavusans;">لا سلام</p>');

		$this->assertContains($this->actualTextOf('لا سلام'), $this->actualTexts($pdf));
	}

	/**
	 * Text drawn left to right has no reordering to undo.
	 */
	public function testLeftToRightTextIsNotWrapped()
	{
		$pdf = $this->getOutput($this->makeMpdf(), '<p>Hello world</p>');

		$this->assertSame([], $this->actualTexts($pdf));
	}

	/**
	 * Without PDF/UA the content stream is left as it was.
	 */
	public function testNothingIsWrappedOutsidePdfUa()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'en-GB']);
		$mpdf->compress = false;

		$this->assertSame([], $this->actualTexts($this->getOutput($mpdf, '<p dir="rtl" lang="he">שלום עולם</p>')));
	}
}
