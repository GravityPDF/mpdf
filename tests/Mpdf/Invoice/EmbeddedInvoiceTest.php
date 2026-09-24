<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\PageStreams;
use Mpdf\Pdf\DocumentProfile;

class EmbeddedInvoiceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const COMFORT = 'urn:ferd:CrossIndustryDocument:invoice:1p0:comfort';

	/**
	 * A specification that differs from Factur-X is a subclass that overrides what differs: here the file, levels and XMP
	 */
	public function testEmbedsAnInvoiceOfAnotherSpecification()
	{
		$mpdf = $this->pdfA3();
		$mpdf->SetEmbeddedInvoice(new Zugferd1($this->invoice(self::COMFORT)));
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/\/EmbeddedFiles << \/Names \[\(ZUGFeRD-invoice\.xml\) \d+ 0 R\]/', $output);
		$this->assertMatchesRegularExpression('/<<\/F \(ZUGFeRD-invoice\.xml\)\n.*?\/AFRelationship \/Alternative/s', $output);
		$this->assertStringContainsString('<pdfaSchema:schema>ZUGFeRD PDFA Extension Schema</pdfaSchema:schema>', $output);
		$this->assertStringContainsString('<pdfaSchema:namespaceURI>urn:ferd:pdfa:CrossIndustryDocument:invoice:1p0#</pdfaSchema:namespaceURI>', $output);
		$this->assertStringContainsString('<zf:DocumentFileName>ZUGFeRD-invoice.xml</zf:DocumentFileName>', $output);
		$this->assertStringContainsString('<zf:ConformanceLevel>COMFORT</zf:ConformanceLevel>', $output);
		$this->assertStringNotContainsString('<fx:', $output);
	}

	/**
	 * The document holds one invoice, so setting another replaces the first, file and XMP both, rather than clashing with its prefix
	 */
	public function testReplacesTheInvoice()
	{
		$mpdf = $this->pdfA3();
		$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
		$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice(), FacturX::EXTENDED));
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/\/EmbeddedFiles << \/Names \[\(factur-x\.xml\) \d+ 0 R\]/', $output);
		$this->assertSame(1, substr_count($output, '<fx:ConformanceLevel>'));
		$this->assertStringContainsString('<fx:ConformanceLevel>EXTENDED</fx:ConformanceLevel>', $output);
	}

	/**
	 * A subclass's errors name its own specification, levels and constructor
	 */
	public function testNamesTheSpecificationInItsErrors()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Guideline "urn:cen.eu:en16931:2017" is not a ZUGFeRD guideline, so the conformance level is unknown. Pass one of BASIC, COMFORT, EXTENDED as the second argument to the Mpdf\Invoice\Zugferd1 constructor.');

		new Zugferd1($this->invoice('urn:cen.eu:en16931:2017'));
	}

	/**
	 * The invoice is shown the standards the document is being written to, and decides itself whether it may go in
	 */
	public function testShowsTheInvoiceTheDocument()
	{
		$seen = null;
		$invoice = $this->createMock(EmbeddedInvoiceInterface::class);
		$invoice->method('getXmpExtensionSchema')->willReturn(['schema' => 'Example', 'namespaceURI' => 'http://example.com/ns/', 'prefix' => 'ex', 'properties' => ['Ref' => 'A reference']]);
		$invoice->method('getXmpProperties')->willReturn([]);
		$invoice->method('checkDocument')->willReturnCallback(function ($document) use (&$seen) {
			$seen = $document;
		});

		$this->pdfA('2-U')->SetEmbeddedInvoice($invoice);

		$this->assertInstanceOf('Mpdf\Pdf\DocumentProfile', $seen);
		$this->assertSame('1.7', $seen->getPdfVersion(), 'PDF/A-2 is PDF 1.7 whatever the header says');
		$this->assertSame([DocumentProfile::PDFA => '2-U'], $seen->getStandards());
	}

	/**
	 * An invoice's refusal stands: the document is left without it
	 */
	public function testKeepsNoInvoiceItsCheckRefuses()
	{
		$mpdf = $this->mpdf(['mode' => '', 'PDFX' => true, 'PDFXauto' => true]);
		try {
			$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
			$this->fail('A PDF/X document took a Factur-X invoice');
		} catch (MpdfException $e) {
			// FacturXTest checks the wording
		}

		$output = $this->output($mpdf);
		$this->assertStringNotContainsString('factur-x.xml', $output);
		$this->assertStringNotContainsString('<fx:', $output);
	}

	/**
	 * The document is checked again as it is written, as the settings the invoice was checked against can still change
	 */
	public function testChecksTheDocumentAgainAtOutput()
	{
		$mpdf = $this->pdfA3();
		$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
		$mpdf->PDFAversion = '2-B';

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('SetEmbeddedInvoice() needs a PDF/A-3 document for Mpdf\Invoice\FacturX, but this one is PDF/A-2b.');

		$this->output($mpdf);
	}

}
