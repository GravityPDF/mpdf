<?php

namespace Mpdf\Invoice;

use Mpdf\InvoiceFixtures;
use Mpdf\MpdfException;

class CiiInvoiceReaderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * The invoice's details, parties, lines, VAT and totals come from the XML as it states them
	 */
	public function testReadsTheInvoice()
	{
		$invoice = $this->read($this->invoiceXml('en16931.xml'));

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
		$invoice = $this->read($this->invoiceXml('minimum.xml'));

		$this->assertSame([], $invoice['lines']);
		$this->assertSame([], $invoice['vatBreakdown']);
		$this->assertSame([], $invoice['paymentMeans']);
		$this->assertNull($invoice['deliverTo']);
		$this->assertNull($invoice['dueDate']);
		$this->assertSame(182.14, $invoice['totals']['taxTotal']);
		$this->assertSame(1121.11, $invoice['totals']['duePayableAmount']);
	}

	/**
	 * EXTENDED's early payment discounts and late payment penalties are read from the payment terms
	 */
	public function testReadsExtendedDiscountAndPenaltyTerms()
	{
		$terms = '<ram:ApplicableTradePaymentPenaltyTerms><ram:BasisDateTime><udt:DateTimeString format="102">20261023</udt:DateTimeString></ram:BasisDateTime><ram:BasisPeriodMeasure unitCode="MON">1</ram:BasisPeriodMeasure><ram:CalculationPercent>1.5</ram:CalculationPercent></ram:ApplicableTradePaymentPenaltyTerms>'
			. '<ram:ApplicableTradePaymentDiscountTerms><ram:BasisPeriodMeasure unitCode="DAY">10</ram:BasisPeriodMeasure><ram:BasisAmount>900.00</ram:BasisAmount><ram:ActualDiscountAmount>18.00</ram:ActualDiscountAmount></ram:ApplicableTradePaymentDiscountTerms>';
		$invoice = $this->read(str_replace('</ram:SpecifiedTradePaymentTerms>', '</ram:SpecifiedTradePaymentTerms><ram:SpecifiedTradePaymentTerms>' . $terms . '</ram:SpecifiedTradePaymentTerms>', $this->invoiceXml('en16931.xml')));

		$this->assertSame([['percent' => null, 'amount' => 18.0, 'basisAmount' => 900.0, 'period' => 10.0, 'periodUnit' => 'DAY', 'basisDate' => null]], $invoice['paymentDiscounts']);
		$this->assertSame('MON', $invoice['paymentPenalties'][0]['periodUnit']);
		$this->assertSame(1.5, $invoice['paymentPenalties'][0]['percent']);
		$this->assertSame('2026-10-23', $invoice['paymentPenalties'][0]['basisDate']->format('Y-m-d'));
		$this->assertSame([], $this->read($this->invoiceXml('en16931.xml'))['paymentDiscounts']);
	}

	/**
	 * XRechnung's Skonto lines are read as early payment discounts, and the rest of the terms as lines of text
	 */
	public function testReadsSkontoLinesAsDiscounts()
	{
		$terms = "30 days net\n#SKONTO#TAGE=14#PROZENT=2.00#\n#SKONTO#TAGE=7#PROZENT=3.00#BASISBETRAG=900.00#\n";
		$invoice = $this->read(str_replace("30 days net\n#SKONTO#TAGE=14#PROZENT=2.00#\n", $terms, $this->invoiceXml('xrechnung.xml')));

		$this->assertSame(['30 days net'], $invoice['paymentTerms']);
		$this->assertSame([
			['percent' => 2.0, 'amount' => null, 'basisAmount' => null, 'period' => 14.0, 'periodUnit' => 'DAY', 'basisDate' => null],
			['percent' => 3.0, 'amount' => null, 'basisAmount' => 900.0, 'period' => 7.0, 'periodUnit' => 'DAY', 'basisDate' => null],
		], $invoice['paymentDiscounts']);
	}

	/**
	 * A direct debit takes its mandate from the payment terms and its creditor from the settlement
	 */
	public function testReadsTheDirectDebit()
	{
		$means = $this->read($this->invoiceXml('en16931-intra-community.xml'))['paymentMeans'][0];

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
			$this->invoiceXml('en16931.xml')
		);

		$this->assertSame(182.14, $this->read($xml)['totals']['taxTotal']);
	}

	/**
	 * A date in a format other than 102 is kept as its text rather than guessed at
	 */
	public function testKeepsADateInAnotherFormatAsText()
	{
		$xml = str_replace('<udt:DateTimeString format="102">20260923</udt:DateTimeString>', '<udt:DateTimeString format="610">202609</udt:DateTimeString>', $this->invoiceXml('en16931.xml'));

		$this->assertSame('202609', $this->read($xml)['issueDate']);
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

		$this->read($xml);
	}

	/**
	 * XML read
	 *
	 * @param string $xml
	 *
	 * @return mixed[]
	 */
	private function read($xml)
	{
		return (new CiiInvoiceReader())->read($xml);
	}

}
