<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\Preset\EurPreset;
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
		$writer = new HtmlInvoiceWriter();

		$this->assertSame(WriterInterface::HTML, $writer->getFormat());
		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/invoice.html', $writer->write($this->invoice()));
	}

	/**
	 * Without a prepayment the grand total is what is due, and an exemption reason follows its VAT group
	 */
	public function testWritesTheReverseCharge()
	{
		$html = (new HtmlInvoiceWriter())->write($this->reverseChargeInvoice());

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
		$html = (new HtmlInvoiceWriter([Invoice::TYPE_CREDIT_NOTE => 'Avoir', 'issueDate' => 'Date', 'vatGroup' => 'TVA %1$s sur %2$s']))->write($invoice);

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
		$formatter = (new Formatter(new EurPreset()))->withCurrencyFormat('EUR', '€%s')->withPercentFormat('%s pc');
		$html = (new HtmlInvoiceWriter([], $formatter))->write($this->invoice());

		$this->assertStringContainsString('>7,5<', $html);
		$this->assertStringContainsString('>€12,99<', $html);
		$this->assertStringContainsString('>5,5 pc<', $html);
		$this->assertStringContainsString('<strong>€1.021,11</strong>', $html);
		$this->assertStringContainsString('<td>Due date</td><td>23.10.2026</td>', $html);
	}

	/**
	 * A trade document that is not an invoice is refused
	 */
	public function testRefusesADocumentThatIsNotAnInvoice()
	{
		$document = $this->getMockBuilder(TradeDocument::class)->disableOriginalConstructor()->getMockForAbstractClass();

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('writes invoices, not');

		(new HtmlInvoiceWriter())->write($document);
	}

}
