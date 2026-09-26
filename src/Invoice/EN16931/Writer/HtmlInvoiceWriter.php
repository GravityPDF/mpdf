<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Output\HtmlOutput;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\Invoice\StringWriterInterface;
use Mpdf\Invoice\TradeDocument;
use Mpdf\Invoice\VatCategory;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\Arrays;

/**
 * Writes an invoice as HTML for the page: the parties, the lines, the VAT breakdown, the totals and how to pay
 *
 * A Formatter sets how its numbers, amounts and dates are written, and labels translate it:
 *
 *     DocumentComposer::compose($mpdf, $invoice, [new HtmlInvoiceWriter(new Formatter(new FrancePreset()), [Invoice::TYPE_INVOICE => 'Facture'])]);
 */
class HtmlInvoiceWriter implements StringWriterInterface
{

	use Strict;

	/**
	 * The styles the invoice's classes are drawn with. The line breaks are written as "\n" because a line break in the
	 * source would be "\r\n" in a Windows checkout.
	 *
	 * @var string
	 */
	private static $css = "<style>\n"
		. ".invoice-details td { padding: 0 4mm 0.5mm 0; }\n"
		. ".invoice-parties { margin-top: 4mm; }\n"
		. ".invoice-parties td { vertical-align: top; }\n"
		. ".invoice-lines { border-collapse: collapse; margin: 6mm 0 4mm; }\n"
		. ".invoice-lines th { border-bottom: 0.3mm solid #444; padding: 1.5mm; }\n"
		. ".invoice-lines td { border-bottom: 0.1mm solid #ccc; padding: 1.5mm; vertical-align: top; }\n"
		. ".invoice-lines .invoice-total td { border-bottom: none; padding: 0.8mm 1.5mm; }\n"
		. ".invoice-text { text-align: left; }\n"
		. ".invoice-number { text-align: right; }\n"
		. '</style>';

	/**
	 * The English labels: the title of each type of invoice, the names of the details, parties, columns and totals,
	 * and sprintf() patterns for the text written around a value
	 *
	 * @var string[]
	 */
	private static $defaultLabels = [
		Invoice::TYPE_INVOICE => 'Invoice',
		Invoice::TYPE_CREDIT_NOTE => 'Credit note',
		Invoice::TYPE_CORRECTED => 'Corrected invoice',
		Invoice::TYPE_PREPAYMENT => 'Prepayment invoice',
		Invoice::TYPE_SELF_BILLED => 'Self-billed invoice',
		'issueDate' => 'Issue date',
		'deliveryDate' => 'Delivery date',
		'dueDate' => 'Due date',
		'buyerReference' => 'Your reference',
		'orderReference' => 'Order',
		'precedingInvoice' => 'Corrects invoice',
		'seller' => 'From',
		'buyer' => 'To',
		'deliverTo' => 'Deliver to',
		'vatId' => 'VAT ID %s',
		'contact' => 'Contact: %s',
		'item' => 'Item',
		'quantity' => 'Quantity',
		'unitPrice' => 'Unit price',
		'vat' => 'VAT',
		'notSubjectToVat' => 'Not subject to VAT',
		'vatGroup' => 'VAT %1$s on %2$s',
		'amount' => 'Amount',
		'allowance' => 'Discount',
		'charge' => 'Charge',
		'linesTotal' => 'Total of the lines',
		'lineTotal' => 'Total excluding VAT',
		'grandTotal' => 'Total including VAT',
		'prepaid' => 'Paid in advance',
		'due' => 'Amount due',
		'paymentReference' => 'Payment reference: %s',
		'iban' => 'IBAN: %s',
		'bic' => 'BIC: %s',
		'accountName' => 'Account name: %s',
		'account' => 'Account: %s',
		'directDebit' => 'Direct debit from %1$s under mandate %2$s, creditor ID %3$s',
		'card' => 'Card ending %s',
		'vatOnDebits' => 'VAT is paid on debits',
	];

	/**
	 * @var string[]
	 */
	private $labels;

	/**
	 * @var \Mpdf\Invoice\Formatter
	 */
	private $formatter;

	/**
	 * @param \Mpdf\Invoice\Formatter $formatter
	 * @param string[] $labels Replacements for any of the default labels, keyed as they are, and titles for any other
	 *                         type of invoice, keyed by its UNTDID 1001 code
	 *
	 * @throws \Mpdf\MpdfException When a label's key is neither a default label's nor a type code
	 */
	public function __construct(Formatter $formatter, array $labels = [])
	{
		foreach (array_keys(array_diff_key($labels, self::$defaultLabels)) as $key) {
			if (!is_int($key)) {
				throw new MpdfException(sprintf('"%s" is not an invoice label; use one of %s', $key, implode(', ', array_filter(array_keys(self::$defaultLabels), 'is_string'))));
			}
		}

		$this->formatter = $formatter;
		$this->labels = $labels + self::$defaultLabels;
	}

	/**
	 * The invoice as HTML, for DocumentComposer to write onto the page
	 *
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return \Mpdf\Invoice\Output\HtmlOutput
	 *
	 * @throws \Mpdf\MpdfException When the document is not an invoice
	 */
	public function output(TradeDocument $document)
	{
		return new HtmlOutput($this->write($document));
	}

	/**
	 * The invoice as HTML, headed by its type and number
	 *
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the document is not an invoice
	 */
	public function write(TradeDocument $document)
	{
		if (!$document instanceof Invoice) {
			throw new MpdfException(sprintf('%s writes invoices, not %s', __CLASS__, get_class($document)));
		}

		$title = Arrays::get($this->labels, $document->getTypeCode(), $this->labels[Invoice::TYPE_INVOICE]);

		$html = self::$css . "\n";
		$html .= '<h1>' . $this->escape($title . ' ' . $document->getId()) . '</h1>' . "\n";
		$html .= $this->details($document);
		$html .= $this->parties($document);
		$html .= $this->lines($document);
		$html .= $this->payment($document);

		foreach ($document->getNotes() as $note) {
			$html .= '<p>' . nl2br($this->escape($note['content'])) . '</p>' . "\n";
		}

		return $html;
	}

	/**
	 * The dates and references, each only when set
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function details(Invoice $invoice)
	{
		$details = [
			'issueDate' => $this->date($invoice->getIssueDate()),
			'deliveryDate' => $this->date($invoice->getDeliveryDate()),
			'dueDate' => $this->date($invoice->getDueDate()),
			'buyerReference' => $invoice->getBuyerReference(),
			'orderReference' => $invoice->getOrderReference(),
			'precedingInvoice' => $this->precedingInvoices($invoice),
		];

		$html = '<table class="invoice-details">';
		foreach ($this->filled($details) as $label => $value) {
			$html .= '<tr><td>' . $this->escape($this->labels[$label]) . '</td><td>' . $this->escape($value) . '</td></tr>';
		}

		return $html . '</table>' . "\n";
	}

	/**
	 * The invoices this one corrects, each with its date when it has one
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string|null
	 */
	private function precedingInvoices(Invoice $invoice)
	{
		$references = [];
		foreach ($invoice->getPrecedingInvoices() as $preceding) {
			$date = $this->date($preceding['issueDate']);
			$references[] = $date !== null ? $preceding['id'] . ' (' . $date . ')' : $preceding['id'];
		}

		return $references ? implode(', ', $references) : null;
	}

	/**
	 * The seller, the buyer and where the goods went, side by side
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function parties(Invoice $invoice)
	{
		$parties = $this->filled(['seller' => $invoice->getSeller(), 'buyer' => $invoice->getBuyer(), 'deliverTo' => $invoice->getDeliverTo()]);

		$html = '<table class="invoice-parties" width="100%"><tr>';
		foreach ($parties as $label => $party) {
			$html .= $this->party($this->labels[$label], $party, floor(100 / count($parties)));
		}

		return $html . '</tr></table>' . "\n";
	}

	/**
	 * A party's cell: name, address, VAT ID, contact, and email address when it receives invoices at one
	 *
	 * @param string $label
	 * @param \Mpdf\Invoice\Party $party
	 * @param int $width The percentage of the row the cell takes
	 *
	 * @return string
	 */
	private function party($label, Party $party, $width)
	{
		$contact = $this->filled([$party->getContactName(), $party->getContactPhone(), $party->getContactEmail()]);
		$lines = array_merge([$party->getName()], $this->formatter->address($party), [
			$party->getVatId() !== null ? $this->labelled('vatId', $party->getVatId()) : null,
			$contact ? $this->labelled('contact', implode(', ', $contact)) : null,
			$party->getElectronicAddressScheme() === 'EM' ? $party->getElectronicAddress() : null,
		]);

		return '<td width="' . $width . '%"><strong>' . $this->escape($label) . '</strong><br>'
			. implode('<br>', array_map([$this, 'escape'], $this->filled($lines)))
			. '</td>';
	}

	/**
	 * The lines, with the totals below them
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function lines(Invoice $invoice)
	{
		$currency = $invoice->getCurrency();

		$labels = [$this->labels['quantity'], $this->labels['unitPrice'], $this->labels['vat'], $this->labels['amount']];
		$html = '<table class="invoice-lines" width="100%"><thead>' . $this->row('th', $this->escape($this->labels['item']), $labels) . '</thead><tbody>';

		foreach ($invoice->getLines() as $line) {
			$html .= $this->row('td', $this->item($line, $currency), [
				$this->formatter->number($line->getQuantity()),
				$this->formatter->money($line->getUnitPrice(), $currency),
				$this->rate($line->getVatCategory(), $line->getVatRate()),
				$this->formatter->money($line->getNetAmount(), $currency),
			]);
		}

		return $html . '</tbody>' . $this->totals($invoice) . '</table>' . "\n";
	}

	/**
	 * A line's item cell: its name, then its description and allowances and charges in small print
	 *
	 * @param \Mpdf\Invoice\LineItem $line
	 * @param string $currency
	 *
	 * @return string
	 */
	private function item(LineItem $line, $currency)
	{
		$small = [$line->getDescription()];
		foreach ($line->getAllowanceCharges() as $allowanceCharge) {
			$small[] = $this->allowanceChargeLabel($allowanceCharge) . ' ' . $this->formatter->money($allowanceCharge->getSignedAmount(), $currency);
		}

		$html = $this->escape($line->getName());
		foreach ($this->filled($small) as $text) {
			$html .= '<br><small>' . $this->escape($text) . '</small>';
		}

		return $html;
	}

	/**
	 * A row of the lines table: the item, then the numbers aligned right
	 *
	 * @param string $cell th or td
	 * @param string $item The item's HTML
	 * @param string[] $numbers
	 *
	 * @return string
	 */
	private function row($cell, $item, array $numbers)
	{
		$html = '<tr><' . $cell . ' class="invoice-text">' . $item . '</' . $cell . '>';
		foreach ($numbers as $number) {
			$html .= '<' . $cell . ' class="invoice-number">' . $this->escape($number) . '</' . $cell . '>';
		}

		return $html . '</tr>';
	}

	/**
	 * What an allowance or charge is for: its reason, or Discount or Charge
	 *
	 * @param \Mpdf\Invoice\AllowanceCharge $allowanceCharge
	 *
	 * @return string
	 */
	private function allowanceChargeLabel(AllowanceCharge $allowanceCharge)
	{
		if ($allowanceCharge->getReason() !== null) {
			return $allowanceCharge->getReason();
		}

		return $this->labels[$allowanceCharge->isCharge() ? 'charge' : 'allowance'];
	}

	/**
	 * The total of the lines and the invoice's allowances and charges, the total excluding VAT, the VAT of each
	 * category and rate, and the total, less any prepayment; the last in bold
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function totals(Invoice $invoice)
	{
		$currency = $invoice->getCurrency();
		$sums = $invoice->getTotals();

		$totals = [];
		if ($invoice->getAllowanceCharges()) {
			$totals[] = [$this->labels['linesTotal'], $sums->getLineTotal()];
			foreach ($invoice->getAllowanceCharges() as $allowanceCharge) {
				$totals[] = [$this->allowanceChargeLabel($allowanceCharge), $allowanceCharge->getSignedAmount()];
			}
		}
		$totals[] = [$this->labels['lineTotal'], $sums->getTaxBasisTotal()];
		foreach ($sums->getVatBreakdown() as $group) {
			$label = $this->labelled('vatGroup', $this->rate($group['category'], $group['rate']), $this->formatter->money($group['basis'], $currency));
			$reason = $invoice->getExemptionReason($group['category']);
			if ($reason !== null) {
				$label .= ' (' . $reason . ')';
			}
			$totals[] = [$label, $group['amount']];
		}
		$totals[] = [$this->labels['grandTotal'], $sums->getGrandTotal()];
		if ($invoice->getPrepaidAmount() != 0) {
			$totals[] = [$this->labels['prepaid'], -$invoice->getPrepaidAmount()];
			$totals[] = [$this->labels['due'], $sums->getDuePayableAmount()];
		}

		$html = '<tfoot>';
		foreach ($totals as $i => $total) {
			$html .= $this->total($total[0], $this->formatter->money($total[1], $currency), $i === count($totals) - 1);
		}

		return $html . '</tfoot>';
	}

	/**
	 * A row of the totals below the lines
	 *
	 * @param string $label
	 * @param string $amount
	 * @param bool $bold
	 *
	 * @return string
	 */
	private function total($label, $amount, $bold)
	{
		$amount = $this->escape($amount);

		return '<tr class="invoice-total"><td colspan="4" class="invoice-number">' . $this->escape($label) . '</td>'
			. '<td class="invoice-number">' . ($bold ? '<strong>' . $amount . '</strong>' : $amount) . '</td></tr>';
	}

	/**
	 * The payment terms and reference, and each way to pay, when there are any
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function payment(Invoice $invoice)
	{
		$lines = [$invoice->getPaymentTerms()];
		if ($invoice->getPaymentReference() !== null) {
			$lines[] = $this->labelled('paymentReference', $invoice->getPaymentReference());
		}

		foreach ($invoice->getPaymentMeans() as $means) {
			$lines = array_merge($lines, $this->paymentMeans($means));
		}

		if ($invoice->isVatOnDebits()) {
			$lines[] = $this->labels['vatOnDebits'];
		}

		$lines = $this->filled($lines);

		return $lines ? '<p>' . implode('<br>', array_map([$this, 'escape'], $lines)) . '</p>' . "\n" : '';
	}

	/**
	 * How to pay by one means: the account to transfer to, the account a direct debit is taken from, or the card
	 *
	 * @param \Mpdf\Invoice\PaymentMeans $means
	 *
	 * @return string[]
	 */
	private function paymentMeans(PaymentMeans $means)
	{
		$lines = [$means->getInformation()];

		if ($means->getAccount() !== null) {
			$account = [
				$means->isIban() ? 'iban' : 'account' => $means->getAccount(),
				'bic' => $means->getBic(),
				'accountName' => $means->getAccountName(),
			];
			foreach ($this->filled($account) as $label => $value) {
				$lines[] = $this->labelled($label, $value);
			}
		}

		if ($means->getMandateReference() !== null) {
			$lines[] = $this->labelled('directDebit', $means->getDebitedAccount(), $means->getMandateReference(), $means->getCreditorId());
		}

		if ($means->getCardNumber() !== null) {
			$lines[] = $this->labelled('card', $means->getCardNumber());
		}

		return $lines;
	}

	/**
	 * A line's or group's VAT: the rate, or that it is not subject to VAT, which has none
	 *
	 * @param string $category
	 * @param float $rate
	 *
	 * @return string
	 */
	private function rate($category, $rate)
	{
		return !VatCategory::hasRate($category) ? $this->labels['notSubjectToVat'] : $this->formatter->percent($rate);
	}

	/**
	 * A label's pattern filled with the values given
	 *
	 * @param string $label
	 * @param string ...$values
	 *
	 * @return string
	 */
	private function labelled($label, ...$values)
	{
		return vsprintf($this->labels[$label], $values);
	}

	/**
	 * A date as the formatter writes it, or null when there is none
	 *
	 * @param \DateTimeInterface|null $date
	 *
	 * @return string|null
	 */
	private function date($date)
	{
		return $date !== null ? $this->formatter->date($date) : null;
	}

	/**
	 * The values that are set, keeping their keys
	 *
	 * @param mixed[] $values
	 *
	 * @return mixed[]
	 */
	private function filled(array $values)
	{
		return array_filter($values, function ($value) {
			return $value !== null && $value !== '';
		});
	}

	/**
	 * Text made safe to put in HTML
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function escape($text)
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

}
