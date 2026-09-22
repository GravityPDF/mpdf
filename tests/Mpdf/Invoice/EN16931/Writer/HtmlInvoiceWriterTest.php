<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\Preset\GermanyPreset;
use Mpdf\Invoice\Preset\UnitedKingdomPreset;
use Mpdf\Invoice\TradeDocument;
use Mpdf\Invoice\WriterInterface;
use Mpdf\MpdfException;

class HtmlInvoiceWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * The writer in the British convention, with any labels given
	 *
	 * @param string[] $labels
	 *
	 * @return \Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter
	 */
	private function writer(array $labels = [])
	{
		return new HtmlInvoiceWriter(new Formatter(new UnitedKingdomPreset()), $labels);
	}

	/**
	 * The invoice is written with its details, parties, lines, totals and payment, its text escaped
	 */
	public function testWritesTheInvoice()
	{
		$writer = $this->writer();

		$this->assertSame(WriterInterface::HTML, $writer->getFormat());
		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/invoice.html', $writer->write($this->invoice()));
	}

	/**
	 * Without a prepayment the grand total is what is due, and an exemption reason follows its VAT group
	 */
	public function testWritesTheReverseCharge()
	{
		$html = $this->writer()->write($this->reverseChargeInvoice());

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
		$html = $this->writer([Invoice::TYPE_CREDIT_NOTE => 'Avoir', 'issueDate' => 'Date', 'vatGroup' => 'TVA %1$s sur %2$s'])->write($invoice);

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
	 * A party's address is laid out as its country lays them out: a British postcode on a line of its own
	 */
	public function testLaysOutTheAddressesByCountry()
	{
		$this->assertStringContainsString('<br>10 Downing Street<br>London<br>SW1A 2AA<br>GB</td>', $this->writer()->write($this->ukInvoice()));
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

		$this->writer()->write($document);
	}

}
