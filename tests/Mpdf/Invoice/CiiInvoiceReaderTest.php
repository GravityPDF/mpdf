<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;

class CiiInvoiceReaderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The invoice's details, parties, lines, VAT and totals come from the XML as it states them
	 */
	public function testReadsTheInvoice()
	{
		$invoice = $this->read('en16931.xml');

		$this->assertSame('INV-2026-0001', $invoice['id']);
		$this->assertSame('380', $invoice['typeCode']);
		$this->assertSame('EUR', $invoice['currency']);
		$this->assertSame('2026-09-23', $invoice['issueDate']->format('Y-m-d'));
		$this->assertSame('2026-10-23', $invoice['dueDate']->format('Y-m-d'));
		$this->assertSame(['Late payment penalty: 3x the legal interest rate'], $invoice['notes']);
		$this->assertSame('BR-42', $invoice['buyerReference']);

		$this->assertSame('Buyer GmbH & Co. KG', $invoice['buyer']['name']);
		$this->assertSame(['street' => 'Hauptstraße 1', 'additional' => 'Gebäude <B>', 'postcode' => '10115', 'city' => 'Berlin', 'subdivision' => null, 'country' => 'DE'], $invoice['buyer']['address']);
		$this->assertSame('DE123456789', $invoice['buyer']['vatId']);
		$this->assertSame('EM', $invoice['buyer']['electronicAddressScheme']);

		$this->assertCount(2, $invoice['lines']);
		$this->assertSame(['Consulting', 'September retainer', 7.5, 'HUR', 120.0, 'S', 20.0, 900.0], [
			$invoice['lines'][0]['name'],
			$invoice['lines'][0]['description'],
			$invoice['lines'][0]['quantity'],
			$invoice['lines'][0]['unitCode'],
			$invoice['lines'][0]['unitPrice'],
			$invoice['lines'][0]['vatCategory'],
			$invoice['lines'][0]['vatRate'],
			$invoice['lines'][0]['netAmount'],
		]);

		$this->assertCount(2, $invoice['vatBreakdown']);
		$this->assertSame(['category' => 'S', 'rate' => 5.5, 'basis' => 38.97, 'amount' => 2.14, 'exemptionReason' => null, 'dueDateCode' => null], $invoice['vatBreakdown'][1]);
		$this->assertSame(1121.11, $invoice['totals']['grandTotal']);
		$this->assertSame(100.0, $invoice['totals']['prepaidAmount']);
		$this->assertSame(1021.11, $invoice['totals']['duePayableAmount']);
		$this->assertNull($invoice['totals']['roundingAmount']);
	}

	/**
	 * What a MINIMUM invoice leaves out is null or empty, not an error
	 */
	public function testReadsWhatMinimumCarries()
	{
		$invoice = $this->read('minimum.xml');

		$this->assertSame([], $invoice['lines']);
		$this->assertSame([], $invoice['vatBreakdown']);
		$this->assertSame([], $invoice['paymentMeans']);
		$this->assertNull($invoice['deliverTo']);
		$this->assertNull($invoice['dueDate']);
		$this->assertSame(182.14, $invoice['totals']['taxTotal']);
		$this->assertSame(1121.11, $invoice['totals']['duePayableAmount']);
	}

	/**
	 * A direct debit takes its mandate from the payment terms and its creditor from the settlement
	 */
	public function testReadsTheDirectDebit()
	{
		$means = $this->read('en16931-intra-community.xml')['paymentMeans'][0];

		$this->assertSame('59', $means['typeCode']);
		$this->assertSame('DE02120300000000202051', $means['debitedAccount']);
		$this->assertSame('MANDATE-42', $means['mandate']);
		$this->assertSame('FR98ZZZ999999', $means['creditorId']);
	}

	/**
	 * Where the VAT is also given in another currency, the total is the one in the invoice's own
	 */
	public function testReadsTheVatInTheInvoiceCurrency()
	{
		$xml = str_replace(
			'<ram:TaxTotalAmount currencyID="EUR">182.14</ram:TaxTotalAmount>',
			'<ram:TaxTotalAmount currencyID="SEK">2003.54</ram:TaxTotalAmount><ram:TaxTotalAmount currencyID="EUR">182.14</ram:TaxTotalAmount>',
			$this->xml('en16931.xml')
		);

		$this->assertSame(182.14, (new CiiInvoiceReader())->read($xml)['totals']['taxTotal']);
	}

	/**
	 * A date in a format other than 102 is kept as its text rather than guessed at
	 */
	public function testKeepsADateInAnotherFormatAsText()
	{
		$xml = str_replace('<udt:DateTimeString format="102">20260923</udt:DateTimeString>', '<udt:DateTimeString format="610">202609</udt:DateTimeString>', $this->xml('en16931.xml'));

		$this->assertSame('202609', (new CiiInvoiceReader())->read($xml)['issueDate']);
	}

	/**
	 * XML it cannot read, and the reason given for each
	 *
	 * @return string[][]
	 */
	public function refusedProvider()
	{
		return [
			'empty' => ['', 'must be a non-empty string'],
			'not XML' => ['<rsm:CrossIndustryInvoice', 'does not parse'],
			'a document type declaration' => ['<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>', 'document type declaration'],
			'UBL' => ['<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>', 'reads Cross Industry Invoice XML, not a {urn:oasis:names:specification:ubl:schema:xsd:Invoice-2}Invoice document'],
			'no namespace' => ['<CrossIndustryInvoice/>', 'not a {}CrossIndustryInvoice document'],
		];
	}

	/**
	 * XML that is empty, does not parse, could bring in entities or is not a Cross Industry Invoice is refused
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

		(new CiiInvoiceReader())->read($xml);
	}

	/**
	 * A fixture in tests/data/invoice, read
	 *
	 * @param string $fixture
	 *
	 * @return mixed[]
	 */
	private function read($fixture)
	{
		return (new CiiInvoiceReader())->read($this->xml($fixture));
	}

	/**
	 * The XML of a fixture in tests/data/invoice
	 *
	 * @param string $fixture
	 *
	 * @return string
	 */
	private function xml($fixture)
	{
		return file_get_contents(__DIR__ . '/../../data/invoice/' . $fixture);
	}

}
