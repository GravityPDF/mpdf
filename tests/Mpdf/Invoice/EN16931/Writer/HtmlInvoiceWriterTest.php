<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\Preset\GermanyPreset;
use Mpdf\Invoice\TradeDocument;
use Mpdf\Invoice\WriterInterface;
use Mpdf\MpdfException;

class HtmlInvoiceWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * The invoice is written with its details, parties, lines, totals and payment, its text escaped
	 */
	public function testWritesTheInvoice()
	{
		$writer = $this->htmlWriter();

		$this->assertSame(WriterInterface::HTML, $writer->getFormat());
		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/invoice.html', $writer->write($this->invoice()));
	}

	/**
	 * Without a prepayment the grand total is what is due, and an exemption reason follows its VAT group
	 */
	public function testWritesTheReverseCharge()
	{
		$html = $this->htmlWriter()->write($this->reverseChargeInvoice());

		$this->assertStringContainsString('VAT 0% on 900.00 EUR (Reverse charge)', $html);
		$this->assertStringContainsString('<strong>900.00 EUR</strong>', $html);
		$this->assertStringNotContainsString('Amount due', $html);
	}

	/**
	 * Labels given replace the defaults, including the title of each type of invoice
	 */
	public function testTakesLabels()
	{
		$invoice = $this->invoice()->setTypeCode(Invoice::TYPE_CREDIT_NOTE);
		$html = $this->htmlWriter([Invoice::TYPE_CREDIT_NOTE => 'Avoir', 'issueDate' => 'Date', 'vatGroup' => 'TVA %1$s sur %2$s'])->write($invoice);

		$this->assertStringContainsString('<h1>Avoir INV-2026-0001</h1>', $html);
		$this->assertStringContainsString('<td>Date</td>', $html);
		$this->assertStringContainsString('TVA 20% sur 900.00 EUR', $html);
		$this->assertStringContainsString('<td>Due date</td>', $html);
	}

	/**
	 * The formatter given writes every quantity, amount, rate and date
	 */
	public function testWritesWithTheFormatter()
	{
		$formatter = (new Formatter(new GermanyPreset()))->withCurrencyFormat('EUR', '€%s')->withPercentFormat('%s pc');
		$html = (new HtmlInvoiceWriter($formatter))->write($this->invoice());

		$this->assertStringContainsString('>7,5<', $html);
		$this->assertStringContainsString('>€12,99<', $html);
		$this->assertStringContainsString('>5,5 pc<', $html);
		$this->assertStringContainsString('<strong>€1.021,11</strong>', $html);
		$this->assertStringContainsString('<td>Due date</td><td>23.10.2026</td>', $html);
	}

	/**
	 * A line's discount shows under it, and the invoice's discount and shipping between the lines and the total before
	 * VAT, with the card it was paid by and the seller's contact
	 */
	public function testWritesAllowancesChargesAndCard()
	{
		$html = $this->htmlWriter()->write($this->shopInvoice());

		$this->assertStringContainsString('<br><small>Display model -50.00 EUR</small>', $html);
		$this->assertStringContainsString('>Total of the lines</td><td class="invoice-number">592.00 EUR<', $html);
		$this->assertStringContainsString('>Loyalty discount</td><td class="invoice-number">-59.20 EUR<', $html);
		$this->assertStringContainsString('>Shipping</td><td class="invoice-number">24.90 EUR<', $html);
		$this->assertStringContainsString('>Total excluding VAT</td><td class="invoice-number">557.70 EUR<', $html);
		$this->assertStringContainsString('Card ending 4242', $html);
		$this->assertStringContainsString('Contact: Accounts, +33 1 23 45 67 89, accounts@seller.example', $html);
	}

	/**
	 * A credit note names the invoice it corrects
	 */
	public function testNamesTheInvoiceCorrected()
	{
		$html = $this->htmlWriter()->write($this->creditNote());

		$this->assertStringContainsString('<h1>Credit note CN-2026-0007</h1>', $html);
		$this->assertStringContainsString('<td>Corrects invoice</td><td>INV-2026-0001 (23/09/2026)</td>', $html);
	}

	/**
	 * Where the goods went takes a column of its own, a direct debit says which account it is taken from, and an
	 * electronic address that is not an email address is left to the XML
	 */
	public function testWritesTheDeliveryAndDirectDebit()
	{
		$html = $this->htmlWriter()->write($this->intraCommunityInvoice());

		$this->assertStringContainsString('<td width="33%"><strong>Deliver to</strong><br>Buyer GmbH Lager<br>Industriestraße 5<br>20457 Hamburg<br>DE</td>', $html);
		$this->assertStringContainsString('Direct debit from DE02120300000000202051 under mandate MANDATE-42, creditor ID FR98ZZZ999999', $html);
		$this->assertStringNotContainsString('04011000-12345-34', $html);
	}

	/**
	 * The option to pay VAT on debits is printed, as France requires it to be mentioned
	 */
	public function testMentionsVatOnDebits()
	{
		$this->assertStringContainsString('<br>VAT is paid on debits</p>', $this->htmlWriter()->write($this->frenchInvoice()));
		$this->assertStringNotContainsString('debits', $this->htmlWriter()->write($this->invoice()));
	}

	/**
	 * A party's address is laid out as its country lays it out: a British postcode on a line of its own
	 */
	public function testLaysOutTheAddressesByCountry()
	{
		$this->assertStringContainsString('<br>10 Downing Street<br>London<br>SW1A 2AA<br>GB</td>', $this->htmlWriter()->write($this->ukInvoice()));
	}

	/**
	 * The fixture invoice to a British buyer
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function ukInvoice()
	{
		$invoice = new Invoice('INV-2026-0003', new \DateTime('2026-09-23'), 'GBP', $this->seller(), (new Party('Buyer Ltd', 'GB'))->setAddress('10 Downing Street', 'SW1A 2AA', 'London'));

		return $invoice->addLine(new LineItem('Consulting', 1, 100, 20));
	}

	/**
	 * A trade document that is not an invoice is refused
	 */
	public function testRefusesADocumentThatIsNotAnInvoice()
	{
		$document = $this->getMockBuilder(TradeDocument::class)->disableOriginalConstructor()->getMockForAbstractClass();

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('writes invoices, not');

		$this->htmlWriter()->write($document);
	}

}
