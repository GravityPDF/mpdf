<?php

namespace Mpdf\Invoice;

use Mpdf\InvoiceFixtures;
use Mpdf\MpdfException;

class InvoiceReaderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * A Cross Industry Invoice and the UBL stating the same invoice are each read by the reader for their syntax
	 *
	 * @dataProvider ublTwinProvider
	 *
	 * @param string $fixture
	 */
	public function testReadsEachSyntax($fixture)
	{
		$cii = (new CiiInvoiceReader())->read($this->invoiceXml($fixture));

		$this->assertEquals($cii, (new InvoiceReader())->read($this->invoiceXml($fixture)));
		$this->assertEquals($cii, (new InvoiceReader())->read($this->invoiceXml('ubl/' . $fixture)));
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
