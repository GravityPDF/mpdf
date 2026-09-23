<?php

namespace Mpdf\Xmp;

use Mpdf\Invoice\FacturX;
use Mpdf\MpdfException;
use Mpdf\PageStreams;

class XmpExtensionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The packet's one list of extension schemas
	 *
	 * @param string $output
	 *
	 * @return string
	 */
	private function extensionSchemas($output)
	{
		$this->assertSame(1, substr_count($output, '<pdfaExtension:schemas>'));
		preg_match('/<pdfaExtension:schemas>.*?<\/pdfaExtension:schemas>/s', $output, $schemas);

		return $schemas[0];
	}

	/**
	 * Under PDF/A the extension's schema is declared and its values written, escaped, on the packet's rdf:about
	 */
	public function testDeclaresAndWritesTheExtension()
	{
		$mpdf = $this->pdfA('2-B');
		$mpdf->AddXmpExtension(FixedXmpExtension::orderReference());
		$output = $this->output($mpdf);

		$schemas = $this->extensionSchemas($output);
		$this->assertStringContainsString('<pdfaSchema:schema>Example order schema</pdfaSchema:schema>', $schemas);
		$this->assertStringContainsString('<pdfaSchema:namespaceURI>http://example.com/ns/order/1.0/</pdfaSchema:namespaceURI>', $schemas);
		$this->assertMatchesRegularExpression('/<pdfaProperty:name>OrderReference<\/pdfaProperty:name>\s*<pdfaProperty:valueType>Text<\/pdfaProperty:valueType>\s*<pdfaProperty:category>external<\/pdfaProperty:category>\s*<pdfaProperty:description>The buyer&apos;s order reference<\/pdfaProperty:description>/', $schemas);

		preg_match('/<rdf:Description rdf:about="(uuid:[\w-]+)" xmlns:pdf=/', $output, $about);
		$this->assertStringContainsString('<rdf:Description rdf:about="' . $about[1] . '" xmlns:ex="http://example.com/ns/order/1.0/">' . "\n" . '    <ex:OrderReference>PO-4471 &amp; 4472</ex:OrderReference>', $output);
	}

	/**
	 * The Factur-X invoice's schema and a registered one share the packet's one list, the invoice's first
	 */
	public function testDeclaresEverySchemaInOneList()
	{
		$mpdf = $this->pdfA3();
		$mpdf->AddXmpExtension(FixedXmpExtension::orderReference());
		$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/<pdfaSchema:prefix>fx<\/pdfaSchema:prefix>.*<pdfaSchema:prefix>ex<\/pdfaSchema:prefix>/s', $this->extensionSchemas($output));
		$this->assertStringContainsString('<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $output);
		$this->assertStringContainsString('<ex:OrderReference>PO-4471 &amp; 4472</ex:OrderReference>', $output);
	}

	/**
	 * PDF/X defines no extension schemas, so only the values are written
	 */
	public function testWritesOnlyTheValuesUnderPdfx()
	{
		$mpdf = $this->mpdf(['mode' => '', 'PDFX' => true, 'PDFXauto' => true]);
		$mpdf->AddXmpExtension(FixedXmpExtension::orderReference());
		$output = $this->output($mpdf);

		$this->assertStringContainsString('<ex:OrderReference>PO-4471 &amp; 4472</ex:OrderReference>', $output);
		$this->assertStringNotContainsString('pdfaExtension', $output);
	}

	/**
	 * A document mPDF writes no XMP for would drop the extension without a word, so it is refused
	 */
	public function testRefusesADocumentWithoutXmp()
	{
		$mpdf = $this->mpdf();

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('AddXmpExtension() needs a PDF/A or PDF/X document, as mPDF writes XMP metadata for no other. Set PDFA or PDFX to true in the constructor configuration.');

		$mpdf->AddXmpExtension(FixedXmpExtension::orderReference());
	}

	/**
	 * Extensions that would write XMP PDF/A does not accept, and the reason given for each
	 *
	 * @return mixed[]
	 */
	public function malformedProvider()
	{
		$schema = ['schema' => 'Example', 'namespaceURI' => 'http://example.com/ns/', 'prefix' => 'ex', 'properties' => ['Ref' => 'A reference']];

		return [
			'no namespace' => [['namespaceURI' => ''] + $schema, ['Ref' => '1'], 'must describe its schema with a schema name, a namespaceURI, a prefix and at least one property.'],
			'no properties' => [['properties' => []] + $schema, [], 'must describe its schema with a schema name, a namespaceURI, a prefix and at least one property.'],
			'prefix not an XML name' => [['prefix' => '1ex'] + $schema, ['Ref' => '1'], 'has the prefix "1ex", which is not an XML name.'],
			'property not an XML name' => [['properties' => ['Order ref' => 'A reference']] + $schema, [], 'must give each property\'s description, a string, by the property\'s name, an XML name.'],
			'undeclared property' => [$schema, ['Ref' => '1', 'Total' => '9'], 'writes Total, which its schema does not declare. Add each property it writes to its schema.'],
		];
	}

	/**
	 * An extension is checked when it is registered, not when the document is written
	 *
	 * @dataProvider malformedProvider
	 *
	 * @param mixed[] $schema
	 * @param string[] $properties
	 * @param string $message
	 */
	public function testRefusesAMalformedExtension($schema, $properties, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('The XMP extension Mpdf\Xmp\FixedXmpExtension ' . $message);

		$extensions = new XmpExtensions();
		$extensions->add('AddXmpExtension()', new FixedXmpExtension($schema, $properties));
	}

	/**
	 * Extensions whose prefix or namespace the packet already has
	 *
	 * @return mixed[]
	 */
	public function clashingProvider()
	{
		return [
			'a prefix mPDF writes' => ['dc', 'http://example.com/ns/dc/'],
			'a namespace mPDF writes' => ['ex2', 'http://purl.org/dc/elements/1.1/'],
			'the prefix of another extension' => ['ex', 'http://example.com/ns/other/'],
			'the namespace of another extension' => ['ex2', 'http://example.com/ns/order/1.0/'],
		];
	}

	/**
	 * Two declarations of one prefix or namespace would leave XMP that does not parse, so the second is refused
	 *
	 * @dataProvider clashingProvider
	 *
	 * @param string $prefix
	 * @param string $namespaceURI
	 */
	public function testRefusesAPrefixOrNamespaceAlreadyTaken($prefix, $namespaceURI)
	{
		$extensions = new XmpExtensions();
		$extensions->add('AddXmpExtension()', FixedXmpExtension::orderReference());

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage(sprintf('uses the prefix "%s" and namespace %s, but the document\'s XMP already has one or the other. Give each extension a prefix and namespace of its own.', $prefix, $namespaceURI));

		$extensions->add('AddXmpExtension()', FixedXmpExtension::orderReference($prefix, $namespaceURI));
	}

	/**
	 * Each order of registering the Factur-X invoice and an extension with its fx prefix, and the extension refused
	 *
	 * @return mixed[]
	 */
	public function facturXFirstProvider()
	{
		return [
			'invoice first' => [true, 'Mpdf\Xmp\FixedXmpExtension'],
			'extension first' => [false, 'Mpdf\Invoice\FacturX'],
		];
	}

	/**
	 * The Factur-X invoice's fx prefix is taken whichever of the two is registered first
	 *
	 * @dataProvider facturXFirstProvider
	 *
	 * @param bool $invoiceFirst
	 * @param string $refused The class of the extension registered second
	 */
	public function testRefusesTheFacturXPrefixEitherWay($invoiceFirst, $refused)
	{
		$mpdf = $this->pdfA3();
		if ($invoiceFirst) {
			$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
		} else {
			$mpdf->AddXmpExtension(FixedXmpExtension::orderReference('fx'));
		}

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('The XMP extension ' . $refused . ' uses the prefix "fx"');

		if ($invoiceFirst) {
			$mpdf->AddXmpExtension(FixedXmpExtension::orderReference('fx'));
		} else {
			$mpdf->SetEmbeddedInvoice(new FacturX($this->invoice()));
		}
	}

	/**
	 * RDF set with SetAdditionalXmpRdf() that clashes with a registered extension, and the reason given for each
	 *
	 * @return mixed[]
	 */
	public function clashingXmpRdfProvider()
	{
		return [
			'extension schemas' => [
				'<rdf:Description rdf:about="" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"><pdfaExtension:schemas><rdf:Bag/></pdfaExtension:schemas></rdf:Description>',
				'The RDF set with SetAdditionalXmpRdf() declares PDF/A extension schemas. A PDF can hold only one list of them, and mPDF writes it. Register each schema and its properties with AddXmpExtension() instead.',
			],
			'the extension\'s properties' => [
				'<rdf:Description rdf:about="" xmlns:ex="http://example.com/ns/order/1.0/"><ex:OrderReference>PO-1</ex:OrderReference></rdf:Description>',
				'The RDF set with SetAdditionalXmpRdf() has ex properties (http://example.com/ns/order/1.0/), which mPDF already writes for AddXmpExtension(). Remove them from that RDF.',
			],
		];
	}

	/**
	 * RDF that repeats what the extensions write is refused rather than written as XMP that does not parse
	 *
	 * @dataProvider clashingXmpRdfProvider
	 *
	 * @param string $rdf
	 * @param string $message
	 */
	public function testRefusesAdditionalXmpRdfThatClashes($rdf, $message)
	{
		$mpdf = $this->pdfA('2-B');
		$mpdf->AddXmpExtension(FixedXmpExtension::orderReference());
		$mpdf->SetAdditionalXmpRdf($rdf);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		$this->output($mpdf);
	}

}
