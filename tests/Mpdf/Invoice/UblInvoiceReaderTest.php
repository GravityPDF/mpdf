<?php

namespace Mpdf\Invoice;

use Mpdf\InvoiceFixtures;
use Mpdf\MpdfException;

class UblInvoiceReaderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * A UBL invoice is read as the same array as the Cross Industry Invoice stating the same invoice
	 *
	 * @dataProvider ublTwinProvider
	 *
	 * @param string $fixture
	 */
	public function testReadsWhatItsCrossIndustryInvoiceTwinReads($fixture)
	{
		$this->assertEquals((new CiiInvoiceReader())->read($this->invoiceXml($fixture)), $this->read($this->invoiceXml('ubl/' . $fixture)));
	}

	/**
	 * A CreditNote's lines are read from its CreditNoteLines and its due date from its payment means
	 */
	public function testReadsACreditNote()
	{
		$xml = str_replace('<cbc:PaymentMeansCode>58</cbc:PaymentMeansCode>', '<cbc:PaymentMeansCode>58</cbc:PaymentMeansCode><cbc:PaymentDueDate>2026-10-16</cbc:PaymentDueDate>', $this->invoiceXml('ubl/en16931-credit-note.xml'));
		$invoice = $this->read($xml);

		$this->assertSame('381', $invoice['typeCode']);
		$this->assertSame('2026-10-16', $invoice['dueDate']->format('Y-m-d'));
		$this->assertSame([1.5, 'HUR', 180.0], [$invoice['lines'][0]['quantity'], $invoice['lines'][0]['unitCode'], $invoice['lines'][0]['netAmount']]);
		$this->assertSame('INV-2026-0001', $invoice['precedingInvoices'][0]['id']);
	}

	/**
	 * A party with no legal name is named by its trading name
	 */
	public function testNamesAPartyByItsTradingNameWithoutALegalName()
	{
		$xml = str_replace(
			'<cbc:RegistrationName>Seller SARL</cbc:RegistrationName>',
			'</cac:PartyLegalEntity><cac:PartyLegalEntity>',
			str_replace('<cac:PostalAddress>', '<cac:PartyName><cbc:Name>Seller Shop</cbc:Name></cac:PartyName><cac:PostalAddress>', $this->invoiceXml('ubl/en16931.xml'))
		);

		$this->assertSame('Seller Shop', $this->read($xml)['seller']['name']);
	}

	/**
	 * An account is an IBAN only when it has an IBAN's form, as UBL does not say
	 */
	public function testTakesAnAccountForAnIbanByItsForm()
	{
		$xml = str_replace('<cbc:ID>FR7630006000011234567890189</cbc:ID>', '<cbc:ID>12345678</cbc:ID>', $this->invoiceXml('ubl/en16931.xml'));

		$this->assertTrue($this->read($this->invoiceXml('ubl/en16931.xml'))['paymentMeans'][0]['iban']);
		$this->assertSame(['12345678', false], [$this->read($xml)['paymentMeans'][0]['account'], $this->read($xml)['paymentMeans'][0]['iban']]);
	}

	/**
	 * Where the VAT is also given in another currency, the total is the one in the invoice's own
	 */
	public function testReadsTheVatInTheInvoiceCurrency()
	{
		$xml = str_replace(
			'<cac:TaxTotal>',
			'<cac:TaxTotal><cbc:TaxAmount currencyID="SEK">2003.54</cbc:TaxAmount></cac:TaxTotal><cac:TaxTotal>',
			$this->invoiceXml('ubl/en16931.xml')
		);

		$this->assertSame(182.14, $this->read($xml)['totals']['taxTotal']);
	}

	/**
	 * A date with a time zone is read as its day, and a date written another way is kept as its text
	 */
	public function testReadsADateWithATimeZone()
	{
		$xml = str_replace(
			['<cbc:IssueDate>2026-09-23</cbc:IssueDate>', '<cbc:DueDate>2026-10-23</cbc:DueDate>'],
			['<cbc:IssueDate>2026-09-23+02:00</cbc:IssueDate>', '<cbc:DueDate>23/10/2026</cbc:DueDate>'],
			$this->invoiceXml('ubl/en16931.xml')
		);
		$invoice = $this->read($xml);

		$this->assertSame('2026-09-23', $invoice['issueDate']->format('Y-m-d'));
		$this->assertSame('23/10/2026', $invoice['dueDate']);
	}

	/**
	 * A code for when VAT is due is given in UNTDID 2475, as Cross Industry Invoice XML gives it, and one EN 16931 does
	 * not have for it is left out
	 */
	public function testReadsWhenVatIsDue()
	{
		$period = '<cac:InvoicePeriod><cbc:DescriptionCode>%s</cbc:DescriptionCode></cac:InvoicePeriod><cac:OrderReference>';
		$xml = $this->invoiceXml('ubl/en16931.xml');

		$this->assertSame('72', $this->read(str_replace('<cac:OrderReference>', sprintf($period, '432'), $xml))['vatBreakdown'][1]['dueDateCode']);
		$this->assertNull($this->read(str_replace('<cac:OrderReference>', sprintf($period, '5'), $xml))['vatBreakdown'][0]['dueDateCode']);
	}

	/**
	 * XML that is not a UBL Invoice or CreditNote is refused, as a Cross Industry Invoice is
	 */
	public function testRefusesACrossIndustryInvoice()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('reads UBL invoice XML, not a {urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100}CrossIndustryInvoice document');

		$this->read($this->invoiceXml('en16931.xml'));
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
		return (new UblInvoiceReader())->read($xml);
	}

}
