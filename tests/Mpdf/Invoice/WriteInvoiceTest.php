<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\EN16931\Writer\CiiInvoiceWriter;
use Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter;
use Mpdf\Invoice\PdfA3\FacturX;
use Mpdf\MpdfException;
use Mpdf\PageStreams;

class WriteInvoiceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;
	use PageStreams;

	/**
	 * The HTML writer prints the invoice and the XML writer embeds it, in one call
	 */
	public function testPrintsAndEmbedsTheInvoice()
	{
		$mpdf = $this->pdfA3();
		$mpdf->WriteInvoice($this->invoice(), [new HtmlInvoiceWriter(), new CiiInvoiceWriter(FacturX::EN16931)]);
		$output = $this->output($mpdf);

		$this->assertStringContainsString('<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $output);
		$this->assertStringContainsString('/EmbeddedFiles << /Names [(factur-x.xml)', $output);
	}

	/**
	 * With only the HTML writer the invoice is printed and nothing is embedded, so the document need not be PDF/A
	 */
	public function testPrintsTheInvoiceAlone()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteInvoice($this->invoice(), [new HtmlInvoiceWriter()]);
		$output = $this->output($mpdf);

		$this->assertNull($mpdf->facturX);
		$this->assertStringNotContainsString('/EmbeddedFiles', $output);
		$text = $this->drawnText($output);
		$this->assertStringContainsString('Invoice INV-2026-0001', $text);
		$this->assertStringContainsString('1,021.11 EUR', $text);
	}

	/**
	 * Writers mPDF cannot use, and the reason it gives
	 *
	 * @return mixed[]
	 */
	public function refusedProvider()
	{
		return [
			'not a writer' => [[new \stdClass()], 'Each invoice writer must implement'],
			'two XML writers' => [[new CiiInvoiceWriter(FacturX::EN16931), new CiiInvoiceWriter(FacturX::MINIMUM)], 'takes one XML writer'],
		];
	}

	/**
	 * A writer mPDF cannot use is refused
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param mixed[] $writers
	 * @param string $message
	 */
	public function testRefusesWritersItCannotUse(array $writers, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		$this->pdfA3()->WriteInvoice($this->minimumInvoice(), $writers);
	}

	/**
	 * A writer that cannot express the invoice stops the others writing it too, leaving the document as it was
	 */
	public function testWritesNothingWhenAWriterFails()
	{
		$mpdf = $this->pdfA3();

		try {
			$mpdf->WriteInvoice($this->invoice(), [new HtmlInvoiceWriter(), new CiiInvoiceWriter(FacturX::MINIMUM)]);
			$this->fail('MINIMUM cannot carry the prepayment, so the XML writer should have refused the invoice');
		} catch (MpdfException $e) {
			$this->assertStringContainsString('MINIMUM cannot carry a prepaid amount', $e->getMessage());
		}

		$this->assertSame(0, $mpdf->page);
		$this->assertNull($mpdf->facturX);
	}

	/**
	 * A writer of a format mPDF has no use for is refused
	 */
	public function testRefusesAnUnknownFormat()
	{
		$writer = $this->getMockBuilder(WriterInterface::class)->getMock();
		$writer->method('getFormat')->willReturn('json');

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Invoice writer format "json" is not one mPDF can write');

		$this->mpdf()->WriteInvoice($this->invoice(), [$writer]);
	}

}
