<?php

namespace Mpdf\Ua;

use Mpdf\Output\Destination;

/**
 * A PDF/UA violation throws in strict mode and is recorded as a warning in auto mode.
 */
class PdfUaModeTest extends PdfUaTestCase
{

	/**
	 * OverWrite() cannot keep the structure tree, so strict mode refuses it.
	 */
	public function testOverWriteInStrictModeThrows()
	{
		$mpdf = $this->makeMpdf(['PDFUA' => true, 'PDFUAauto' => false]);

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/PDF\/UA-1 mode/');

		// Refused before the file is read, so it need not exist
		$mpdf->OverWrite('nonexistent.pdf', 'foo', 'bar', Destination::STRING_RETURN);
	}

	/**
	 * In auto mode OverWrite() still overwrites, and warns that the structure tree is lost.
	 */
	public function testOverWriteInAutoModeWarnsAndReturns()
	{
		$source = $this->makeMpdf();
		$pdf = $this->getOutput($source, '<p>hello placeholder world</p>');

		$file = tempnam(sys_get_temp_dir(), 'mpdf_e21_') . '.pdf';
		file_put_contents($file, $pdf);

		try {
			$mpdf = $this->makeMpdf(['PDFUA' => true, 'PDFUAauto' => true]);
			$out = $mpdf->OverWrite($file, 'placeholder', 'replacement', Destination::STRING_RETURN);

			$this->assertNotEmpty($out, 'OverWrite() must still return PDF bytes in auto mode');
			$this->assertStringStartsWith('%PDF', $out);

			$warnings = $mpdf->getPdfUaWarnings();
			$this->assertNotEmpty($warnings, 'auto mode must record a PDF/UA warning');
			$joined = implode("\n", $warnings);
			$this->assertStringContainsString('OverWrite()', $joined);
			$this->assertStringContainsString('structure tree', $joined);
		} finally {
			@unlink($file);
		}
	}
}
