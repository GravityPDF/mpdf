<?php

namespace Mpdf\Invoice;

use Mpdf\InvoiceFixtures;
use Mpdf\MpdfException;

class InvoiceReaderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * A Cross Industry Invoice, a UBL Invoice and a UBL CreditNote are each read by the reader for their syntax
	 */
	public function testReadsEachSyntax()
	{
		$reader = new InvoiceReader();

		$this->assertEquals((new CiiInvoiceReader())->read($this->invoiceXml('en16931.xml')), $reader->read($this->invoiceXml('en16931.xml')));
		foreach (['ubl/en16931.xml', 'ubl/en16931-credit-note.xml'] as $fixture) {
			$this->assertEquals((new UblInvoiceReader())->read($this->invoiceXml($fixture)), $reader->read($this->invoiceXml($fixture)));
		}
	}

	/**
	 * XML it cannot read, and the reason given for each
	 *
	 * @return string[][]
	 */
	public function refusedProvider()
	{
		return [
			'a UBL order' => ['<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>', 'reads Cross Industry Invoice or UBL invoice XML, not a {urn:oasis:names:specification:ubl:schema:xsd:Order-2}Order document'],
			'no namespace' => ['<Invoice/>', 'not a {}Invoice document'],
		];
	}

	/**
	 * XML in neither syntax is refused
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param string $xml
	 * @param string $message
	 */
	public function testRefusesWhatItCannotRead($xml, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		(new InvoiceReader())->read($xml);
	}

}
