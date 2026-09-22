<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\TradeDocument;
use Mpdf\Invoice\WriterInterface;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\NumericString;

/**
 * Writes an invoice as HTML for the page: the parties, the lines, the VAT breakdown, the totals and how to pay
 *
 * Pass labels to translate it and the separators its numbers are written with, e.g. for French
 * new HtmlInvoiceWriter(['380' => 'Facture', 'issueDate' => 'Date'], ',', "\xc2\xa0"). Extend it to format dates or
 * amounts further.
 *
 *     $mpdf->WriteInvoice($invoice, [new HtmlInvoiceWriter()]);
 */
class HtmlInvoiceWriter implements WriterInterface
{

	use Strict;

	/**
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
		'seller' => 'From',
		'buyer' => 'To',
		'vatId' => 'VAT ID',
		'item' => 'Item',
		'quantity' => 'Quantity',
		'unitPrice' => 'Unit price',
		'vat' => 'VAT',
		'vatGroup' => 'VAT %1$s on %2$s',
		'amount' => 'Amount',
		'lineTotal' => 'Total excluding VAT',
		'grandTotal' => 'Total including VAT',
		'prepaid' => 'Paid in advance',
		'due' => 'Amount due',
		'paymentReference' => 'Payment reference',
		'iban' => 'IBAN',
		'bic' => 'BIC',
		'accountName' => 'Account name',
	];

	/**
	 * @var string[]
	 */
	private $labels;

	/**
	 * @var string
	 */
	private $decimalPoint;

	/**
	 * @var string
	 */
	private $thousandsSeparator;

	/**
	 * @param string[] $labels Replacements for any of the default labels, keyed as they are
	 * @param string $decimalPoint
	 * @param string $thousandsSeparator
	 */
	public function __construct(array $labels = [], $decimalPoint = '.', $thousandsSeparator = ',')
	{
		$this->labels = $labels + self::$defaultLabels;
		$this->decimalPoint = $decimalPoint;
		$this->thousandsSeparator = $thousandsSeparator;
	}

	/**
	 * @return string
	 */
	public function getFormat()
	{
		return self::HTML;
	}

	/**
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function write(TradeDocument $document)
	{
		if (!$document instanceof Invoice) {
			throw new MpdfException(sprintf('%s writes invoices, not %s', __CLASS__, get_class($document)));
		}

		$title = isset($this->labels[$document->getTypeCode()]) ? $this->labels[$document->getTypeCode()] : $this->labels[Invoice::TYPE_INVOICE];

		$html = '<style>
.invoice-details td { padding: 0 4mm 0.5mm 0; }
.invoice-parties { margin-top: 4mm; }
.invoice-parties td { vertical-align: top; }
.invoice-lines { border-collapse: collapse; margin: 6mm 0 4mm; }
.invoice-lines th { border-bottom: 0.3mm solid #444; padding: 1.5mm; }
.invoice-lines td { border-bottom: 0.1mm solid #ccc; padding: 1.5mm; vertical-align: top; }
.invoice-lines .invoice-total td { border-bottom: none; padding: 0.8mm 1.5mm; }
.invoice-text { text-align: left; }
.invoice-number { text-align: right; }
</style>' . "\n";

		$html .= '<h1>' . $this->escape($title . ' ' . $document->getId()) . '</h1>' . "\n";
		$html .= $this->details($document);
		$html .= '<table class="invoice-parties" width="100%"><tr>'
			. $this->party($this->labels['seller'], $document->getSeller())
			. $this->party($this->labels['buyer'], $document->getBuyer())
			. '</tr></table>' . "\n";
		$html .= $this->lines($document);
		$html .= $this->payment($document);

		foreach ($document->getNotes() as $note) {
			$html .= '<p>' . nl2br($this->escape($note)) . '</p>' . "\n";
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
		];

		$html = '<table class="invoice-details">';
		foreach ($this->filled($details) as $label => $value) {
			$html .= '<tr><td>' . $this->escape($this->labels[$label]) . '</td><td>' . $this->escape($value) . '</td></tr>';
		}

		return $html . '</table>' . "\n";
	}

	/**
	 * A seller or buyer's cell: name, address, VAT ID and email
	 *
	 * @param string $label
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return string
	 */
	private function party($label, Party $party)
	{
		$lines = [
			$party->getName(),
			$party->getStreet(),
			$party->getAdditionalStreet(),
			trim($party->getPostcode() . ' ' . $party->getCity()),
			$party->getCountryCode(),
			$party->getVatId() !== null ? $this->labels['vatId'] . ' ' . $party->getVatId() : null,
			$party->getEmail(),
		];

		return '<td width="50%"><strong>' . $this->escape($label) . '</strong><br>'
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
			$item = $this->escape($line->getName());
			if ($line->getDescription() !== null) {
				$item .= '<br><small>' . $this->escape($line->getDescription()) . '</small>';
			}

			$html .= $this->row('td', $item, [
				$this->number($line->getQuantity()),
				$this->money($line->getUnitPrice(), $currency),
				$this->rate($line->getVatCategory(), $line->getVatRate()),
				$this->money($line->getNetAmount(), $currency),
			]);
		}

		return $html . '</tbody>' . $this->totals($invoice) . '</table>' . "\n";
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
	 * The line total, the VAT of each category and rate, and the total, less any prepayment; the last in bold
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function totals(Invoice $invoice)
	{
		$currency = $invoice->getCurrency();

		$totals = [[$this->labels['lineTotal'], $invoice->getLineTotal()]];
		foreach ($invoice->getVatBreakdown() as $group) {
			$label = sprintf($this->labels['vatGroup'], $this->rate($group['category'], $group['rate']), $this->money($group['basis'], $currency));
			$reason = $invoice->getExemptionReason($group['category']);
			if ($reason !== null) {
				$label .= ' (' . $reason . ')';
			}
			$totals[] = [$label, $group['amount']];
		}
		$totals[] = [$this->labels['grandTotal'], $invoice->getGrandTotal()];
		if ($invoice->getPrepaidAmount() != 0) {
			$totals[] = [$this->labels['prepaid'], -$invoice->getPrepaidAmount()];
			$totals[] = [$this->labels['due'], $invoice->getDuePayableAmount()];
		}

		$last = array_pop($totals);

		$html = '<tfoot>';
		foreach ($totals as $total) {
			$html .= $this->total($total[0], $this->escape($this->money($total[1], $currency)));
		}
		$html .= $this->total($last[0], '<strong>' . $this->escape($this->money($last[1], $currency)) . '</strong>');

		return $html . '</tfoot>';
	}

	/**
	 * A row of the totals below the lines
	 *
	 * @param string $label
	 * @param string $amount The amount's HTML
	 *
	 * @return string
	 */
	private function total($label, $amount)
	{
		return '<tr class="invoice-total"><td colspan="4" class="invoice-number">' . $this->escape($label) . '</td>'
			. '<td class="invoice-number">' . $amount . '</td></tr>';
	}

	/**
	 * The payment terms and the account to pay into, when there are any
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	private function payment(Invoice $invoice)
	{
		$lines = [$invoice->getPaymentTerms()];

		$account = [
			'paymentReference' => $invoice->getPaymentReference(),
			'iban' => $invoice->getIban(),
			'bic' => $invoice->getBic(),
			'accountName' => $invoice->getAccountName(),
		];
		foreach ($this->filled($account) as $label => $value) {
			$lines[] = $this->labels[$label] . ': ' . $value;
		}

		$lines = $this->filled($lines);

		return $lines ? '<p>' . implode('<br>', array_map([$this, 'escape'], $lines)) . '</p>' . "\n" : '';
	}

	/**
	 * A line's or group's VAT: the rate, or the category where it has none
	 *
	 * @param string $category
	 * @param float $rate
	 *
	 * @return string
	 */
	private function rate($category, $rate)
	{
		return $category === LineItem::NOT_SUBJECT_TO_VAT ? $category : $this->number($rate) . '%';
	}

	/**
	 * A quantity or VAT rate, to at most four decimals and without trailing zeros
	 *
	 * @param float $number
	 *
	 * @return string
	 */
	protected function number($number)
	{
		$decimal = NumericString::decimal($number, 4);
		$point = strpos($decimal, '.');

		return number_format((float) $decimal, $point === false ? 0 : strlen($decimal) - $point - 1, $this->decimalPoint, $this->thousandsSeparator);
	}

	/**
	 * An amount, to the cent, with its currency
	 *
	 * @param float $amount
	 * @param string $currency
	 *
	 * @return string
	 */
	protected function money($amount, $currency)
	{
		return number_format($amount, 2, $this->decimalPoint, $this->thousandsSeparator) . ' ' . $currency;
	}

	/**
	 * A date, or null when there is none
	 *
	 * @param \DateTimeInterface|null $date
	 *
	 * @return string|null
	 */
	protected function date($date)
	{
		return $date !== null ? $date->format('Y-m-d') : null;
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
	 * @param string $text
	 *
	 * @return string
	 */
	private function escape($text)
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

}
