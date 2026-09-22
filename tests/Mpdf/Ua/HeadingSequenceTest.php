<?php

namespace Mpdf\Ua;

/**
 * The order of headings (ISO 14289-1 §7.4.2): the first is H1 and none skips a level on the way down.
 *
 * PDFUAauto mode moves a heading to the level it may take and warns; strict mode throws.
 *
 * @group pdfua
 */
class HeadingSequenceTest extends PdfUaTestCase
{

	/**
	 * A document whose first heading is an h2 has it tagged H1, with a warning.
	 *
	 * @return void
	 */
	public function testFirstHeadingPromotedToH1WhenH2Comes()
	{
		$mpdf   = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<h2>Only heading</h2>');

		$this->assertStringContainsString('/S /H1', $output);
		$this->assertStringNotContainsString('/S /H2', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('7.4.2', $warnings[0]);
	}

	/**
	 * An h3 straight after an h1 is tagged H2, with a warning.
	 *
	 * @return void
	 */
	public function testSkippedLevelPromotedToValidLevel()
	{
		$mpdf   = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<h1>Title</h1><h3>Skipped sub</h3>');

		$this->assertStringContainsString('/S /H1', $output);
		$this->assertStringContainsString('/S /H2', $output);
		$this->assertStringNotContainsString('/S /H3', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('7.4.2', $warnings[0]);
	}

	/**
	 * An h1 followed by an h2 is tagged as written, without a warning.
	 *
	 * @return void
	 */
	public function testValidSequenceNotChanged()
	{
		$mpdf   = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<h1>Title</h1><h2>Section</h2>');

		$this->assertStringContainsString('/S /H1', $output);
		$this->assertStringContainsString('/S /H2', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertCount(0, $warnings);
	}

	/**
	 * Each skip is moved and warned about on its own: h1, h4, h5 is tagged H1, H2, H3.
	 *
	 * @return void
	 */
	public function testMultipleSkipsEachClampedIndependently()
	{
		$mpdf   = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<h1>A</h1><h4>B</h4><h5>C</h5>');

		$this->assertStringContainsString('/S /H1', $output);
		$this->assertStringContainsString('/S /H2', $output);
		$this->assertStringContainsString('/S /H3', $output);
		$this->assertStringNotContainsString('/S /H4', $output);
		$this->assertStringNotContainsString('/S /H5', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertCount(2, $warnings);
	}

	/**
	 * Going back up a level, as in h1, h2, h1, is allowed and needs no warning.
	 *
	 * @return void
	 */
	public function testAscendingAfterDescendingIsValid()
	{
		$mpdf   = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<h1>A</h1><h2>B</h2><h1>C</h1>');

		$this->assertStringContainsString('/S /H1', $output);
		$this->assertStringContainsString('/S /H2', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertCount(0, $warnings);
	}

	/**
	 * In strict mode a first heading other than h1 throws.
	 *
	 * @return void
	 */
	public function testStrictModeThrowsOnNonH1First()
	{
		$this->expectException('\Mpdf\MpdfException');
		$this->expectExceptionMessageMatches('/7\.4\.2/');

		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$this->getOutput($mpdf, '<h2>No H1 before me</h2>');
	}

	/**
	 * In strict mode a heading that skips a level throws.
	 *
	 * @return void
	 */
	public function testStrictModeThrowsOnSkippedLevel()
	{
		$this->expectException('\Mpdf\MpdfException');
		$this->expectExceptionMessageMatches('/7\.4\.2/');

		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$this->getOutput($mpdf, '<h1>Title</h1><h3>Skipped</h3>');
	}
}
