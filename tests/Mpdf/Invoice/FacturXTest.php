<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\PageStreams;

class FacturXTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A PDF/A-3 document with the invoice set, in a mode that embeds its fonts as PDF/A needs
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function invoiceDocument()
	{
		$mpdf = $this->pdfA3();
		$mpdf->WriteHTML('<h1>Invoice INV-2026-0001</h1>');
		$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));

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
		$facturX = new FacturX($this->invoice($guideline));
		$file = $facturX->getAssociatedFile();

		$this->assertSame($filename, $file['name']);
		$this->assertSame($relationship, $file['AFRelationship']);
		$properties = $facturX->getXmpProperties();
		$this->assertSame($level, $properties['ConformanceLevel']);
		$this->assertSame($filename, $properties['DocumentFileName']);
	}

	/**
	 * A level passed in wins over the invoice's, in any case
	 */
	public function testTakesTheLevelItIsGiven()
	{
		$facturX = new FacturX($this->invoice(), 'extended');

		$properties = $facturX->getXmpProperties();
		$this->assertSame('EXTENDED', $properties['ConformanceLevel']);
	}

	/**
	 * Invoices with no usable conformance level, and the reason given for each
	 *
	 * @return mixed[]
	 */
	public function rejectedProvider()
	{
		return [
			'no XML' => ['', null, 'The Factur-X invoice XML must be a non-empty string.'],
			'unknown guideline' => ['<ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>urn:peppol:bis</ram:ID>', null, 'Guideline "urn:peppol:bis" is not a Factur-X guideline, so the conformance level is unknown. Pass one of MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED, XRECHNUNG as the second argument to the Mpdf\Invoice\FacturX constructor.'],
			'no guideline' => ['<rsm:CrossIndustryInvoice/>', null, 'The invoice XML does not name a guideline (the ID in GuidelineSpecifiedDocumentContextParameter)'],
			'unknown level' => ['<rsm:CrossIndustryInvoice/>', 'COMFORT', 'Factur-X conformance level "COMFORT" is not valid. Pass one of MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED, XRECHNUNG as the second argument to the Mpdf\Invoice\FacturX constructor.'],
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

		$this->assertSame(1, substr_count($output, '<pdfaExtension:schemas>'));
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
	 * RDF the user adds is written beside the fx XMP when it declares nothing the fx XMP does
	 */
	public function testKeepsTheAdditionalXmpRdf()
	{
		$mpdf = $this->invoiceDocument();
		$mpdf->SetAdditionalXmpRdf('   <rdf:Description rdf:about="" xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"><xmpRights:Marked>True</xmpRights:Marked></rdf:Description>' . "\n");
		$output = $this->output($mpdf);

		$this->assertStringContainsString('<xmpRights:Marked>True</xmpRights:Marked>', $output);
		$this->assertStringContainsString('<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $output);
	}

	/**
	 * RDF that clashes with the fx XMP, and the reason given for each
	 *
	 * @return mixed[]
	 */
	public function clashingXmpRdfProvider()
	{
		$extensionSchema = '   <rdf:Description rdf:about="" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#">'
			. '<pdfaExtension:schemas><rdf:Bag><rdf:li rdf:parseType="Resource"><pdfaSchema:namespaceURI>http://example.com/ns/</pdfaSchema:namespaceURI></rdf:li></rdf:Bag></pdfaExtension:schemas>'
			. '</rdf:Description>' . "\n";
		$fx = '   <rdf:Description rdf:about="" xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#"><fx:DocumentType>INVOICE</fx:DocumentType></rdf:Description>' . "\n";

		return [
			'fx properties' => [$fx, 'The RDF set with SetAdditionalXmpRdf() has fx properties (urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#), which mPDF already writes for SetEmbeddedInvoice(). Remove them from that RDF.'],
			'a hand-written Factur-X XMP' => [$extensionSchema . $fx, 'The RDF set with SetAdditionalXmpRdf() has fx properties'],
		];
	}

	/**
	 * A packet holds one pdfaExtension:schemas and each property once, so RDF that repeats either is refused rather than
	 * written as XMP that does not parse
	 *
	 * @dataProvider clashingXmpRdfProvider
	 *
	 * @param string $rdf
	 * @param string $message
	 */
	public function testRefusesAdditionalXmpRdfThatClashes($rdf, $message)
	{
		$mpdf = $this->invoiceDocument();
		$mpdf->SetAdditionalXmpRdf($rdf);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		$this->output($mpdf);
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
	 * Configurations that are not PDF/A-3, and what the refusal says each is
	 *
	 * @return mixed[]
	 */
	public function notPdfa3Provider()
	{
		return [
			'not PDF/A' => [[], 'not PDF/A'],
			'PDF/A-1b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B'], 'PDF/A-1b'],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B'], 'PDF/A-2b'],
		];
	}

	/**
	 * Only PDF/A-3 may carry the invoice, so any other document is refused when the invoice is set rather than becoming an invalid e-invoice
	 *
	 * @dataProvider notPdfa3Provider
	 *
	 * @param mixed[] $config
	 * @param string $kind What the message says the document is
	 */
	public function testRefusesADocumentThatIsNotPdfa3($config, $kind)
	{
		$mpdf = $this->mpdf($config + ['mode' => '']);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('SetEmbeddedInvoice() needs a PDF/A-3 document for Mpdf\Invoice\FacturX, but this one is ' . $kind . '. Set PDFA to true and PDFAversion to 3-B or 3-U in the constructor configuration.');

		$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
	}

}
