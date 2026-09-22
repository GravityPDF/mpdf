<?php

namespace Mpdf\Ua;

use Mpdf\Mpdf;

/**
 * Optional content needs PDF 1.5, which may raise the declared version but never lower it.
 */
class OptionalContentVersionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * PDF/UA-1 is declared on PDF 1.7.
	 */
	public function testSetVisibilityUnderPdfuaKeepsPdf17()
	{
		$mpdf = new Mpdf(['PDFUA' => true]);

		$mpdf->SetVisibility('printonly');

		$this->assertSame('1.7', $mpdf->pdf_version);
	}

	/**
	 * A layer is optional content too.
	 */
	public function testBeginLayerUnderPdfuaKeepsPdf17()
	{
		$mpdf = new Mpdf(['PDFUA' => true]);

		$mpdf->BeginLayer(1);

		$this->assertSame('1.7', $mpdf->pdf_version);
	}

	/**
	 * A plain document is raised from 1.4.
	 */
	public function testSetVisibilityRaisesPlainDocumentToPdf15()
	{
		$mpdf = new Mpdf();

		$mpdf->SetVisibility('printonly');

		$this->assertSame('1.5', $mpdf->pdf_version);
	}
}
