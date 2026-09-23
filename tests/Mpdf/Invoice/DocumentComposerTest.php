<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\EN16931\Writer\CiiInvoiceWriter;
use Mpdf\Invoice\FacturX;
use Mpdf\Invoice\Output\EmbeddedInvoiceOutput;
use Mpdf\Invoice\Output\AttachmentOutput;
use Mpdf\Invoice\Output\OutputInterface;
use Mpdf\MpdfException;
use Mpdf\PageStreams;
use Mpdf\Pdf\DocumentProfile;

class DocumentComposerTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;
	use PageStreams;

	/**
	 * The HTML writer prints the invoice and the XML writer embeds it, in one call
	 */
	public function testPrintsAndEmbedsTheInvoice()
	{
		$mpdf = $this->pdfA3();
		DocumentComposer::compose($mpdf, $this->invoice(), [$this->htmlWriter(), new CiiInvoiceWriter(FacturX::EN16931)]);
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
		DocumentComposer::compose($mpdf, $this->invoice(), [$this->htmlWriter()]);
		$output = $this->output($mpdf);

		$this->assertStringNotContainsString('/EmbeddedFiles', $output);
		$text = $this->drawnText($output);
		$this->assertStringContainsString('Invoice INV-2026-0001', $text);
		$this->assertStringContainsString('1,021.11 EUR', $text);
	}

	/**
	 * Anything but a writer is refused
	 */
	public function testRefusesWhatIsNotAWriter()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Each invoice writer must implement Mpdf\\Invoice\\WriterInterface');

		DocumentComposer::compose($this->pdfA3(), $this->invoice(), [new \stdClass()]);
	}

	/**
	 * A writer or output that fails beside the HTML writer, the document it fails in, and why
	 *
	 * @return mixed[]
	 */
	public function failureProvider()
	{
		return [
			'a writer that cannot express the invoice' => ['3-B', new CiiInvoiceWriter(FacturX::MINIMUM), 'MINIMUM cannot carry a prepaid amount'],
			'an invoice the document cannot embed' => [null, new CiiInvoiceWriter(FacturX::EN16931), 'SetEmbeddedInvoice() needs a PDF/A-3 document'],
			'an attachment the document cannot take' => ['2-B', new AttachmentOutput(['name' => 'invoice.json', 'content' => '{}', 'mime' => 'application/json']), 'PDF/A-2b cannot attach invoice.json'],
		];
	}

	/**
	 * Every writer runs and every output is checked before any is applied, so one that fails leaves the document as it
	 * was, whatever order the writers come in
	 *
	 * @dataProvider failureProvider
	 *
	 * @param string|null $pdfa The PDFAversion of the document, or null for a plain PDF
	 * @param \Mpdf\Invoice\WriterInterface|\Mpdf\Invoice\Output\OutputInterface $failing A writer, or the output of one
	 * @param string $message
	 */
	public function testWritesNothingWhenOneFails($pdfa, $failing, $message)
	{
		$mpdf = $pdfa === null ? $this->mpdf() : $this->pdfA($pdfa);
		$writers = [$this->htmlWriter(), $failing instanceof WriterInterface ? $failing : $this->writerOf($failing)];

		try {
			DocumentComposer::compose($mpdf, $this->invoice(), $writers);
			$this->fail('The writer should have failed with: ' . $message);
		} catch (MpdfException $e) {
			$this->assertStringContainsString($message, $e->getMessage());
		}

		$this->assertSame(0, $mpdf->page);
	}

	/**
	 * A writer decides what the document embeds, so it need not be Factur-X
	 */
	public function testEmbedsTheInvoiceTheWriterGives()
	{
		$mpdf = $this->pdfA3();
		DocumentComposer::compose($mpdf, $this->invoice(), [$this->writerOf(new EmbeddedInvoiceOutput(new Zugferd1($this->invoiceXml(), Zugferd1::COMFORT)))]);
		$output = $this->output($mpdf);

		$this->assertStringContainsString('<zf:ConformanceLevel>COMFORT</zf:ConformanceLevel>', $output);
		$this->assertStringContainsString('/EmbeddedFiles << /Names [(ZUGFeRD-invoice.xml)', $output);
		$this->assertStringNotContainsString('factur-x.xml', $output);
	}

	/**
	 * An output of the user's own is checked against the document, then applied to it, once each
	 */
	public function testAppliesAnOutputOfItsOwn()
	{
		$mpdf = $this->pdfA3();
		$output = $this->getMockBuilder(OutputInterface::class)->getMock();
		$output->expects($this->once())->method('check')->with($this->callback(function (DocumentProfile $document) {
			return $document->conformsTo(DocumentProfile::PDFA, '3');
		}));
		$output->expects($this->once())->method('apply')->with($this->identicalTo($mpdf));

		DocumentComposer::compose($mpdf, $this->invoice(), [$this->htmlWriter(), $this->writerOf($output)]);

		$this->assertSame(1, $mpdf->page);
	}

}
