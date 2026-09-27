<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\GermanyPreset;
use Mpdf\Invoice\Preset\UnitedKingdomPreset;
use Mpdf\MpdfException;

class HtmlInvoiceWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The invoice is written with its details, parties, lines, totals and payment, its text escaped
	 */
	public function testWritesTheInvoice()
	{
		$this->assertStringEqualsFile(__DIR__ . '/../../data/invoice/invoice.html', $this->write('en16931.xml'));
	}

	/**
	 * Without a prepayment the grand total is what is due, and an exemption reason follows its VAT group
	 */
	public function testWritesTheReverseCharge()
	{
		$html = $this->write('en16931-reverse-charge.xml');

		$this->assertStringContainsString('VAT 0% on 900.00 EUR (Reverse charge)', $html);
		$this->assertStringContainsString('<strong>900.00 EUR</strong>', $html);
		$this->assertStringNotContainsString('Amount due', $html);
	}

	/**
	 * Labels given replace the defaults, including the title of each type of invoice
	 */
	public function testTakesLabels()
	{
		$xml = str_replace('<ram:TypeCode>380</ram:TypeCode>', '<ram:TypeCode>381</ram:TypeCode>', $this->xml('en16931.xml'));
		$html = $this->htmlWriter(['381' => 'Avoir', 'issueDate' => 'Date', 'vatGroup' => 'TVA %1$s sur %2$s'])->write($xml);

		$this->assertStringContainsString('<h1>Avoir INV-2026-0001</h1>', $html);
		$this->assertStringContainsString('<td>Date</td>', $html);
		$this->assertStringContainsString('TVA 20% sur 900.00 EUR', $html);
		$this->assertStringContainsString('<td>Due date</td>', $html);
	}

	/**
	 * A label written around a value is a pattern, so a translation places the value and its punctuation
	 */
	public function testTakesLabelPatterns()
	{
		$html = $this->htmlWriter(['paymentReference' => 'Référence de paiement : %s', 'vatId' => 'N° TVA : %s'])->write($this->xml('en16931.xml'));

		$this->assertStringContainsString('Référence de paiement : INV-2026-0001', $html);
		$this->assertStringContainsString('N° TVA : FR32123456789', $html);
	}

	/**
	 * A label the writer has no use for is refused, lest a mistyped key leave its English label in place, but any type
	 * of invoice may be given a title, and one without is titled as an invoice
	 */
	public function testRefusesAnUnknownLabel()
	{
		$xml = str_replace('<ram:TypeCode>380</ram:TypeCode>', '<ram:TypeCode>326</ram:TypeCode>', $this->xml('en16931.xml'));
		$this->assertStringContainsString('<h1>Partial invoice INV-2026-0001</h1>', $this->htmlWriter(['326' => 'Partial invoice'])->write($xml));
		$this->assertStringContainsString('<h1>Invoice INV-2026-0001</h1>', $this->htmlWriter()->write($xml));

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('"dueDat" is not an invoice label');

		$this->htmlWriter(['dueDat' => 'Échéance']);
	}

	/**
	 * A line and VAT group not subject to VAT say so rather than giving a rate
	 */
	public function testSaysWhenALineIsNotSubjectToVat()
	{
		$html = $this->write('en16931-not-subject.xml');

		$this->assertStringContainsString('<td class="invoice-number">Not subject to VAT</td>', $html);
		$this->assertStringContainsString('>Not subject to VAT (Outside the scope of VAT)</td>', $html);
	}

	/**
	 * The formatter given writes every quantity, amount, rate and date
	 */
	public function testWritesWithTheFormatter()
	{
		$formatter = (new Formatter(new GermanyPreset()))->withCurrencyFormat('EUR', '€%s')->withPercentFormat('%s pc');
		$html = (new HtmlInvoiceWriter($formatter))->write($this->xml('en16931.xml'));

		$this->assertStringContainsString('>7,5<', $html);
		$this->assertStringContainsString('>€12,99<', $html);
		$this->assertStringContainsString('>5,5 pc<', $html);
		$this->assertStringContainsString('<strong>€1.021,11</strong>', $html);
		$this->assertStringContainsString('<td>Due date</td><td>23.10.2026</td>', $html);
	}

	/**
	 * A line's discount shows under it, and the invoice's discount and shipping between the lines and the total before
	 * VAT, with the card it was paid by and the seller's contact and tax number
	 */
	public function testWritesAllowancesChargesAndCard()
	{
		$html = $this->write('en16931-shop.xml');

		$this->assertStringContainsString('<br><small>Display model -50.00 EUR</small>', $html);
		$this->assertStringContainsString('>Total of the lines</td><td class="invoice-number">592.00 EUR<', $html);
		$this->assertStringContainsString('>Loyalty discount</td><td class="invoice-number">-59.20 EUR<', $html);
		$this->assertStringContainsString('>Shipping</td><td class="invoice-number">24.90 EUR<', $html);
		$this->assertStringContainsString('>Total excluding VAT</td><td class="invoice-number">557.70 EUR<', $html);
		$this->assertStringContainsString('Card ending 4242', $html);
		$this->assertStringContainsString('Contact: Accounts, +33 1 23 45 67 89, accounts@seller.example', $html);
		$this->assertStringContainsString('<br>Tax number 201/113/40209<br>', $html);
	}

	/**
	 * A credit note names the invoice it corrects
	 */
	public function testNamesTheInvoiceCorrected()
	{
		$html = $this->write('en16931-credit-note.xml');

		$this->assertStringContainsString('<h1>Credit note CN-2026-0007</h1>', $html);
		$this->assertStringContainsString('<td>Corrects invoice</td><td>INV-2026-0001 (23/09/2026)</td>', $html);
	}

	/**
	 * Where the goods went takes a column of its own, a direct debit says which account it is taken from, and an
	 * electronic address that is not an email address is left to the XML
	 */
	public function testWritesTheDeliveryAndDirectDebit()
	{
		$html = $this->write('en16931-intra-community.xml');

		$this->assertStringContainsString('<td width="33%"><strong>Deliver to</strong><br>Buyer GmbH Lager<br>Industriestraße 5<br>20457 Hamburg<br>DE</td>', $html);
		$this->assertStringContainsString('Direct debit from DE02120300000000202051 under mandate MANDATE-42, creditor ID FR98ZZZ999999', $html);
		$this->assertStringNotContainsString('04011000-12345-34', $html);
	}

	/**
	 * When VAT falls due is printed, as France requires the option to pay it on debits to be mentioned
	 */
	public function testMentionsWhenVatFallsDue()
	{
		$html = $this->htmlWriter(['vatDueOnInvoice' => 'Option pour le paiement de la taxe d’après les débits'])->write($this->xml('en16931-france.xml'));

		$this->assertStringContainsString('<br>Option pour le paiement de la taxe d’après les débits</p>', $html);
		$this->assertStringNotContainsString('VAT is due', $this->write('en16931.xml'));
	}

	/**
	 * A party's address is laid out as its country lays it out: a British postcode on a line of its own
	 */
	public function testLaysOutTheAddressesByCountry()
	{
		$this->assertStringContainsString('<br>10 Downing Street<br>London<br>SW1A 2AA<br>GB<br>', $this->write('en16931-uk.xml'));
	}

	/**
	 * An invoice without lines, as MINIMUM and BASIC WL are, has no lines table head, and one without a VAT breakdown
	 * gives its VAT as one sum
	 */
	public function testWritesAnInvoiceWithoutLines()
	{
		$minimum = $this->write('minimum.xml');
		$this->assertStringNotContainsString('<thead>', $minimum);
		$this->assertStringContainsString('<td colspan="4" class="invoice-number">VAT</td><td class="invoice-number">182.14 EUR</td>', $minimum);
		$this->assertStringContainsString('<strong>1,121.11 EUR</strong>', $minimum);

		$basicWl = $this->write('basic-wl.xml');
		$this->assertStringNotContainsString('<thead>', $basicWl);
		$this->assertStringContainsString('VAT 5.5% on 38.97 EUR', $basicWl);
	}

	/**
	 * XRechnung CII is printed as any EN 16931 invoice is, the buyer's Leitweg-ID as its reference and each line of the
	 * payment terms on a line of its own
	 */
	public function testWritesAnXRechnung()
	{
		$html = $this->write('xrechnung.xml');

		$this->assertStringContainsString('<td>Your reference</td><td>04011000-12345-34</td>', $html);
		$this->assertStringContainsString('<p>30 days net<br>#SKONTO#TAGE=14#PROZENT=2.00#<br>', $html);
	}

	/**
	 * A price for more than one unit says what quantity it is for, and a rounding amount is shown before the amount due
	 */
	public function testWritesThePriceBasisAndRounding()
	{
		$xml = str_replace(
			['<ram:ChargeAmount>12.99</ram:ChargeAmount>', '<ram:TotalPrepaidAmount>100.00</ram:TotalPrepaidAmount>', '<ram:DuePayableAmount>1021.11</ram:DuePayableAmount>'],
			['<ram:ChargeAmount>12.99</ram:ChargeAmount><ram:BasisQuantity unitCode="C62">10</ram:BasisQuantity>', '<ram:TotalPrepaidAmount>100.00</ram:TotalPrepaidAmount><ram:RoundingAmount>-0.11</ram:RoundingAmount>', '<ram:DuePayableAmount>1021.00</ram:DuePayableAmount>'],
			$this->xml('en16931.xml')
		);
		$html = $this->htmlWriter()->write($xml);

		$this->assertStringContainsString('>12.99 EUR per 10<', $html);
		$this->assertStringContainsString('>Rounding</td><td class="invoice-number">-0.11 EUR<', $html);
		$this->assertStringContainsString('>Amount due</td><td class="invoice-number"><strong>1,021.00 EUR</strong><', $html);
	}

	/**
	 * XML that is not a Cross Industry Invoice is refused, as a UBL invoice is
	 */
	public function testRefusesXmlThatIsNotACrossIndustryInvoice()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('reads Cross Industry Invoice XML, not a {urn:oasis:names:specification:ubl:schema:xsd:Invoice-2}Invoice document');

		$this->htmlWriter()->write('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>');
	}

	/**
	 * A fixture in tests/data/invoice written by the British writer
	 *
	 * @param string $fixture
	 *
	 * @return string
	 */
	private function write($fixture)
	{
		return $this->htmlWriter()->write($this->xml($fixture));
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

	/**
	 * The writer in the British convention, with any labels given
	 *
	 * @param string[] $labels
	 *
	 * @return \Mpdf\Invoice\HtmlInvoiceWriter
	 */
	private function htmlWriter(array $labels = [])
	{
		return new HtmlInvoiceWriter(new Formatter(new UnitedKingdomPreset()), $labels);
	}

}
