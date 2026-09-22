<?php

namespace Mpdf;

use Mpdf\Pdf\FacturX;

class FacturXTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @var string
	 */
	private static $invoice;

	/**
	 * The EN 16931 invoice fixture
	 *
	 * @return string
	 */
	private function invoice()
	{
		if (self::$invoice === null) {
			self::$invoice = file_get_contents(__DIR__ . '/../data/xml/factur-x-en16931.xml');
		}

		return self::$invoice;
	}

	/**
	 * A PDF/A-3 document with the invoice set, in a mode that embeds its fonts as PDF/A needs
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function invoiceDocument()
	{
		$mpdf = $this->mpdf(['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B']);
		$mpdf->WriteHTML('<h1>Invoice INV-2026-0001</h1>');
		$mpdf->SetFacturX($this->invoice());

		return $mpdf;
	}

	/**
	 * Each guideline ID with the conformance level, file name and relationship it gives the embedded invoice
	 *
	 * @return mixed[]
	 */
	public function guidelineProvider()
	{
		return [
			['urn:factur-x.eu:1p0:minimum', 'MINIMUM', 'factur-x.xml', 'Data'],
			['urn:factur-x.eu:1p0:basicwl', 'BASIC WL', 'factur-x.xml', 'Data'],
			['urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic', 'BASIC', 'factur-x.xml', 'Alternative'],
			['urn:cen.eu:en16931:2017', 'EN 16931', 'factur-x.xml', 'Alternative'],
			['urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended', 'EXTENDED', 'factur-x.xml', 'Alternative'],
			['urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0', 'XRECHNUNG', 'xrechnung.xml', 'Alternative'],
		];
	}

	/**
	 * The conformance level comes from the guideline the invoice names
	 *
	 * @dataProvider guidelineProvider
	 *
	 * @param string $guideline
	 * @param string $level
	 * @param string $filename
	 * @param string $relationship
	 */
	public function testReadsTheLevelFromTheInvoice($guideline, $level, $filename, $relationship)
	{
		$xml = str_replace('<ram:ID>urn:cen.eu:en16931:2017</ram:ID>', '<ram:ID>' . $guideline . '</ram:ID>', $this->invoice());
		$facturX = new FacturX($xml);
		$file = $facturX->getAssociatedFile();

		$this->assertSame($filename, $file['name']);
		$this->assertSame($relationship, $file['AFRelationship']);
		$this->assertStringContainsString('<fx:ConformanceLevel>' . $level . '</fx:ConformanceLevel>', $facturX->getXmpRdf('uuid:x'));
	}

	/**
	 * A level passed in wins over the invoice's, in any case
	 */
	public function testTakesTheLevelItIsGiven()
	{
		$facturX = new FacturX($this->invoice(), 'extended');

		$this->assertStringContainsString('<fx:ConformanceLevel>EXTENDED</fx:ConformanceLevel>', $facturX->getXmpRdf('uuid:x'));
	}

	/**
	 * Invoices with no usable conformance level, and the reason given for each
	 *
	 * @return mixed[]
	 */
	public function rejectedProvider()
	{
		return [
			'no XML' => ['', null, 'needs its XML'],
			'unknown guideline' => ['<ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>urn:peppol:bis</ram:ID>', null, 'Guideline "urn:peppol:bis" is not a Factur-X profile'],
			'no guideline' => ['<rsm:CrossIndustryInvoice/>', null, 'names no guideline'],
			'unknown level' => ['<rsm:CrossIndustryInvoice/>', 'COMFORT', 'conformance level "COMFORT" is not one of MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED, XRECHNUNG'],
		];
	}

	/**
	 * An invoice with no usable conformance level is refused, not embedded with a guessed one
	 *
	 * @dataProvider rejectedProvider
	 *
	 * @param string $xml
	 * @param string|null $level
	 * @param string $message
	 */
	public function testRefusesAnInvoiceWithoutALevel($xml, $level, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		new FacturX($xml, $level);
	}

	/**
	 * The invoice is embedded as factur-x.xml and described by fx properties that a PDF/A extension schema declares,
	 * on the same rdf:about as the rest of the packet
	 */
	public function testEmbedsAndDescribesTheInvoice()
	{
		$output = $this->output($this->invoiceDocument());

		$this->assertMatchesRegularExpression('/<<\/F \(factur-x\.xml\)\n.*?\/AFRelationship \/Alternative/s', $output);
		$this->assertMatchesRegularExpression('/\/EmbeddedFiles << \/Names \[\(factur-x\.xml\) \d+ 0 R\]/', $output);

		preg_match('/<rdf:Description rdf:about="(uuid:[\w-]+)" xmlns:pdf=/', $output, $about);
		$about = preg_quote($about[1], '/');

		$this->assertMatchesRegularExpression('/<rdf:Description rdf:about="' . $about . '" xmlns:pdfaExtension=.*?<pdfaSchema:namespaceURI>urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#<\/pdfaSchema:namespaceURI>\s*<pdfaSchema:prefix>fx<\/pdfaSchema:prefix>/s', $output);
		foreach (['DocumentFileName', 'DocumentType', 'Version', 'ConformanceLevel'] as $property) {
			$this->assertStringContainsString('<pdfaProperty:name>' . $property . '</pdfaProperty:name>', $output);
		}

		$this->assertMatchesRegularExpression('/<rdf:Description rdf:about="' . $about . '" xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#">\s*'
			. '<fx:DocumentType>INVOICE<\/fx:DocumentType>\s*'
			. '<fx:DocumentFileName>factur-x\.xml<\/fx:DocumentFileName>\s*'
			. '<fx:Version>1\.0<\/fx:Version>\s*'
			. '<fx:ConformanceLevel>EN 16931<\/fx:ConformanceLevel>/', $output);
	}

	/**
	 * Files set with SetAssociatedFiles() are embedded after the invoice, whichever was set first
	 */
	public function testKeepsTheOtherAssociatedFiles()
	{
		$mpdf = $this->invoiceDocument();
		$mpdf->SetAssociatedFiles([[
			'name' => 'timesheet.csv',
			'mime' => 'text/csv',
			'description' => 'Hours worked',
			'AFRelationship' => 'Supplement',
			'content' => "date,hours\n2026-09-22,1\n",
		]]);
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/\/EmbeddedFiles << \/Names \[\(factur-x\.xml\) \d+ 0 R \(timesheet\.csv\) \d+ 0 R\]/', $output);
	}

	/**
	 * Configurations that are not PDF/A-3
	 *
	 * @return mixed[]
	 */
	public function notPdfa3Provider()
	{
		return [
			'not PDF/A' => [[]],
			'PDF/A-1b' => [['PDFA' => true, 'PDFAauto' => true]],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B']],
		];
	}

	/**
	 * Only PDF/A-3 may carry the invoice, so any other document fails at output rather than becoming an invalid e-invoice
	 *
	 * @dataProvider notPdfa3Provider
	 *
	 * @param mixed[] $config
	 */
	public function testRefusesADocumentThatIsNotPdfa3($config)
	{
		$mpdf = $this->mpdf($config + ['mode' => '']);
		$mpdf->SetFacturX($this->invoice());

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('A Factur-X invoice must be PDF/A-3');

		$this->output($mpdf);
	}

}
