<?php

namespace Mpdf\Invoice\Output;

use Mpdf\Invoice\DocumentComposer;
use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\EN16931\Writer\CiiInvoiceWriter;
use Mpdf\Invoice\FacturX;
use Mpdf\MpdfException;
use Mpdf\PageStreams;
use Mpdf\Pdf\DocumentProfile;

class AttachmentOutputTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;
	use PageStreams;

	/**
	 * The file is attached beside those set before and the invoice embedded, under the relationship given
	 */
	public function testAttachesBesideTheOtherFiles()
	{
		$mpdf = $this->pdfA3();
		$mpdf->SetAssociatedFiles([['name' => 'terms.txt', 'mime' => 'text/plain', 'content' => 'Terms', 'AFRelationship' => 'Supplement']]);
		$json = new AttachmentOutput(['name' => 'invoice.json', 'content' => '{}', 'mime' => 'application/json', 'description' => 'The invoice as JSON', 'AFRelationship' => 'Data']);
		DocumentComposer::compose($mpdf, $this->invoice(), [new CiiInvoiceWriter(FacturX::EN16931), $this->writerOf($json)]);
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/\/EmbeddedFiles << \/Names \[\(factur-x\.xml\) \d+ 0 R \(terms\.txt\) \d+ 0 R \(invoice\.json\) \d+ 0 R\]/', $output);
		$this->assertMatchesRegularExpression('/<<\/F \(invoice\.json\)\n\/Desc \(The invoice as JSON\)\n.*?\/AFRelationship \/Data/s', $output);
		$this->assertStringContainsString('/Subtype /application#2Fjson', $output);
	}

	/**
	 * A plain PDF takes the attachment too, related as a supplement unless it says otherwise
	 */
	public function testAttachesToAPlainPdf()
	{
		$mpdf = $this->mpdf();
		DocumentComposer::compose($mpdf, $this->invoice(), [$this->writerOf(new AttachmentOutput(['name' => 'invoice.xml', 'content' => '<Invoice/>', 'mime' => 'text/xml']))]);
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/\/EmbeddedFiles << \/Names \[\(invoice\.xml\) \d+ 0 R\]/', $output);
		$this->assertStringContainsString('/AFRelationship /Supplement', $output);
	}

	/**
	 * The PDF/A parts that forbid the attachment, and the name each is given
	 *
	 * @return string[][]
	 */
	public function refusedProvider()
	{
		return [
			'PDF/A-1b' => ['1-B', 'PDF/A-1b'],
			'PDF/A-2b' => ['2-B', 'PDF/A-2b'],
		];
	}

	/**
	 * PDF/A-1 and PDF/A-2 refuse the attachment, naming the part and the way out
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param string $version
	 * @param string $label
	 */
	public function testRefusesAPdfaPartThatForbidsIt($version, $label)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($label . ' cannot attach invoice.json; only PDF/A-3 among the PDF/A parts takes files of any kind. Set PDFAversion to 3-B or 3-U.');

		(new AttachmentOutput(['name' => 'invoice.json', 'content' => '{}', 'mime' => 'application/json']))->check(DocumentProfile::fromMpdf($this->pdfA($version)));
	}

}
