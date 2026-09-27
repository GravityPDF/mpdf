<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\Arrays;

/**
 * Writes Cross Industry Invoice XML as HTML for the page: the parties, the lines, the VAT breakdown, the totals and how
 * to pay
 *
 * It prints what the XML states, totals included, so the printed invoice and the embedded one agree. A Formatter sets
 * how numbers, amounts and dates are written, and labels translate it:
 *
 *     $writer = new HtmlInvoiceWriter(new Formatter(new FrancePreset()), ['380' => 'Facture', 'dueDate' => 'Échéance']);
 *     $mpdf->WriteHTML($writer->write($xml));
 *     $mpdf->SetEmbeddedInvoice(new FacturX($xml));
 *
 * It reads the XML with CiiInvoiceReader, which neither validates it nor checks its totals.
 */
class HtmlInvoiceWriter
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
	 * The English labels: the title of each type of invoice by its UNTDID 1001 code, the names of the details, parties,
	 * columns and totals, and sprintf() patterns for the text written around a value
	 *
	 * @var string[]
	 */
	private static $defaultLabels = [
		'380' => 'Invoice',
		'381' => 'Credit note',
		'384' => 'Corrected invoice',
		'386' => 'Prepayment invoice',
		'389' => 'Self-billed invoice',
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
		'taxNumber' => 'Tax number %s',
		'contact' => 'Contact: %s',
		'item' => 'Item',
		'quantity' => 'Quantity',
		'unitPrice' => 'Unit price',
		'pricePer' => '%1$s per %2$s',
		'vat' => 'VAT',
		'notSubjectToVat' => 'Not subject to VAT',
		'vatGroup' => 'VAT %1$s on %2$s',
		'amount' => 'Amount',
		'allowance' => 'Discount',
		'charge' => 'Charge',
		'linesTotal' => 'Total of the lines',
		'lineTotal' => 'Total excluding VAT',
		'vatTotal' => 'VAT',
		'grandTotal' => 'Total including VAT',
		'prepaid' => 'Paid in advance',
		'rounding' => 'Rounding',
		'due' => 'Amount due',
		'paymentReference' => 'Payment reference: %s',
		'iban' => 'IBAN: %s',
		'bic' => 'BIC: %s',
		'accountName' => 'Account name: %s',
		'account' => 'Account: %s',
		'directDebit' => 'Direct debit from %1$s under mandate %2$s, creditor ID %3$s',
		'card' => 'Card ending %s',
		'vatDueOnInvoice' => 'VAT is due on the invoice date',
		'vatDueOnDelivery' => 'VAT is due on delivery',
		'vatDueOnPayment' => 'VAT is due on payment',
	];

	/**
	 * The label for each UNTDID 2475 code saying when VAT falls due (BT-8), as CII writes them
	 *
	 * @var string[]
	 */
	private static $vatDueDateLabels = ['5' => 'vatDueOnInvoice', '29' => 'vatDueOnDelivery', '72' => 'vatDueOnPayment'];

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
	 * The invoice as HTML, headed by its type and number
	 *
	 * @param string $xml Cross Industry Invoice XML, at any Factur-X / ZUGFeRD profile or as XRechnung CII
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the XML is not a Cross Industry Invoice
	 */
	public function write($xml)
	{
		$invoice = (new CiiInvoiceReader())->read($xml);

		$title = Arrays::get($this->labels, $invoice['typeCode'], $this->labels['380']);

		$html = self::$css . "\n";
		$html .= '<h1>' . $this->escape($title . ' ' . $invoice['id']) . '</h1>' . "\n";
		$html .= $this->details($invoice);
		$html .= $this->parties($invoice);
		$html .= $this->lines($invoice);
		$html .= $this->payment($invoice);

		foreach ($invoice['notes'] as $note) {
			$html .= '<p>' . nl2br($this->escape($note)) . '</p>' . "\n";
		}

		return $html;
	}

	/**
	 * The dates and references, each only when set
	 *
	 * @param mixed[] $invoice
	 *
	 * @return string
	 */
	private function details(array $invoice)
	{
		$details = [
			'issueDate' => $this->date($invoice['issueDate']),
			'deliveryDate' => $this->date($invoice['deliveryDate']),
			'dueDate' => $this->date($invoice['dueDate']),
			'buyerReference' => $invoice['buyerReference'],
			'orderReference' => $invoice['orderReference'],
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
	 * @param mixed[] $invoice
	 *
	 * @return string|null
	 */
	private function precedingInvoices(array $invoice)
	{
		$references = [];
		foreach ($invoice['precedingInvoices'] as $preceding) {
			$date = $this->date($preceding['issueDate']);
			$references[] = $date !== null ? $preceding['id'] . ' (' . $date . ')' : $preceding['id'];
		}

		return $references ? implode(', ', $references) : null;
	}

	/**
	 * The seller, the buyer and where the goods went, side by side
	 *
	 * @param mixed[] $invoice
	 *
	 * @return string
	 */
	private function parties(array $invoice)
	{
		$parties = $this->filled(['seller' => $invoice['seller'], 'buyer' => $invoice['buyer'], 'deliverTo' => $invoice['deliverTo']]);

		$html = '<table class="invoice-parties" width="100%"><tr>';
		foreach ($parties as $label => $party) {
			$html .= $this->party($this->labels[$label], $party, floor(100 / count($parties)));
		}

		return $html . '</tr></table>' . "\n";
	}

	/**
	 * A party's cell: name, address, VAT ID, tax number, contact, and email address when it receives invoices at one
	 *
	 * @param string $label
	 * @param mixed[] $party
	 * @param int $width The percentage of the row the cell takes
	 *
	 * @return string
	 */
	private function party($label, array $party, $width)
	{
		$contact = $this->filled($party['contact']);
		$lines = array_merge([$party['name']], $this->formatter->address($party['address']), [
			$party['vatId'] !== null ? $this->labelled('vatId', $party['vatId']) : null,
			$party['taxNumber'] !== null ? $this->labelled('taxNumber', $party['taxNumber']) : null,
			$contact ? $this->labelled('contact', implode(', ', $contact)) : null,
			$party['electronicAddressScheme'] === 'EM' ? $party['electronicAddress'] : null,
		]);

		return '<td width="' . $width . '%"><strong>' . $this->escape($label) . '</strong><br>'
			. implode('<br>', array_map([$this, 'escape'], $this->filled($lines)))
			. '</td>';
	}

	/**
	 * The lines, when the XML has any, with the totals below them
	 *
	 * @param mixed[] $invoice
	 *
	 * @return string
	 */
	private function lines(array $invoice)
	{
		$currency = $invoice['currency'];

		$html = '<table class="invoice-lines" width="100%">';

		if ($invoice['lines']) {
			$labels = [$this->labels['quantity'], $this->labels['unitPrice'], $this->labels['vat'], $this->labels['amount']];
			$html .= '<thead>' . $this->row('th', $this->escape($this->labels['item']), $labels) . '</thead><tbody>';

			foreach ($invoice['lines'] as $line) {
				$html .= $this->row('td', $this->item($line, $currency), [
					$this->number($line['quantity']),
					$this->unitPrice($line, $currency),
					$this->rate($line['vatCategory'], $line['vatRate']),
					$this->money($line['netAmount'], $currency),
				]);
			}

			$html .= '</tbody>';
		}

		return $html . $this->totals($invoice) . '</table>' . "\n";
	}

	/**
	 * A line's item cell: its name, then its description and allowances and charges in small print
	 *
	 * @param mixed[] $line
	 * @param string $currency
	 *
	 * @return string
	 */
	private function item(array $line, $currency)
	{
		$small = [$line['description']];
		foreach ($line['allowanceCharges'] as $allowanceCharge) {
			$small[] = $this->allowanceChargeLabel($allowanceCharge) . ' ' . $this->money($this->signed($allowanceCharge), $currency);
		}

		$html = $this->escape((string) $line['name']);
		foreach ($this->filled($small) as $text) {
			$html .= '<br><small>' . $this->escape($text) . '</small>';
		}

		return $html;
	}

	/**
	 * A line's net price, and the quantity it is for when that is not one
	 *
	 * @param mixed[] $line
	 * @param string $currency
	 *
	 * @return string
	 */
	private function unitPrice(array $line, $currency)
	{
		$price = $this->money($line['unitPrice'], $currency);
		if ($price === '' || $line['basisQuantity'] === null || $line['basisQuantity'] == 1) {
			return $price;
		}

		return $this->labelled('pricePer', $price, $this->formatter->number($line['basisQuantity']));
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
	 * @param mixed[] $allowanceCharge
	 *
	 * @return string
	 */
	private function allowanceChargeLabel(array $allowanceCharge)
	{
		if ($allowanceCharge['reason'] !== null) {
			return $allowanceCharge['reason'];
		}

		return $this->labels[$allowanceCharge['charge'] ? 'charge' : 'allowance'];
	}

	/**
	 * An allowance's amount taken off, or a charge's added
	 *
	 * @param mixed[] $allowanceCharge
	 *
	 * @return float|null
	 */
	private function signed(array $allowanceCharge)
	{
		if ($allowanceCharge['amount'] === null) {
			return null;
		}

		return $allowanceCharge['charge'] ? $allowanceCharge['amount'] : -$allowanceCharge['amount'];
	}

	/**
	 * The total of the lines and the invoice's allowances and charges, the total excluding VAT, the VAT of each
	 * category and rate (or all of it, when the XML gives no breakdown), and the total, less any prepayment and with any
	 * rounding; the last in bold
	 *
	 * @param mixed[] $invoice
	 *
	 * @return string
	 */
	private function totals(array $invoice)
	{
		$currency = $invoice['currency'];
		$sums = $invoice['totals'];

		$totals = [];
		if ($invoice['allowanceCharges']) {
			$totals[] = [$this->labels['linesTotal'], $sums['lineTotal']];
			foreach ($invoice['allowanceCharges'] as $allowanceCharge) {
				$totals[] = [$this->allowanceChargeLabel($allowanceCharge), $this->signed($allowanceCharge)];
			}
		}
		$totals[] = [$this->labels['lineTotal'], $sums['taxBasisTotal']];

		foreach ($invoice['vatBreakdown'] as $group) {
			$label = $group['category'] === 'O' ? $this->labels['notSubjectToVat'] : $this->labelled('vatGroup', $this->rate($group['category'], $group['rate']), $this->money($group['basis'], $currency));
			if ($group['exemptionReason'] !== null) {
				$label .= ' (' . $group['exemptionReason'] . ')';
			}
			$totals[] = [$label, $group['amount']];
		}
		if (!$invoice['vatBreakdown']) {
			$totals[] = [$this->labels['vatTotal'], $sums['taxTotal']];
		}

		$totals[] = [$this->labels['grandTotal'], $sums['grandTotal']];
		if ($sums['prepaidAmount'] || $sums['roundingAmount']) {
			if ($sums['prepaidAmount']) {
				$totals[] = [$this->labels['prepaid'], -$sums['prepaidAmount']];
			}
			if ($sums['roundingAmount']) {
				$totals[] = [$this->labels['rounding'], $sums['roundingAmount']];
			}
			$totals[] = [$this->labels['due'], $sums['duePayableAmount']];
		}

		$totals = array_values(array_filter($totals, function ($total) {
			return $total[1] !== null;
		}));

		$html = '<tfoot>';
		foreach ($totals as $i => $total) {
			$html .= $this->total($total[0], $this->money($total[1], $currency), $i === count($totals) - 1);
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
	 * The payment terms and reference, each way to pay, and when VAT falls due, when there are any
	 *
	 * @param mixed[] $invoice
	 *
	 * @return string
	 */
	private function payment(array $invoice)
	{
		$lines = $invoice['paymentTerms'] !== null ? explode("\n", $invoice['paymentTerms']) : [];
		if ($invoice['paymentReference'] !== null) {
			$lines[] = $this->labelled('paymentReference', $invoice['paymentReference']);
		}

		foreach ($invoice['paymentMeans'] as $means) {
			$lines = array_merge($lines, $this->paymentMeans($means));
		}

		foreach ($invoice['vatBreakdown'] as $group) {
			if ($group['dueDateCode'] !== null && isset(self::$vatDueDateLabels[$group['dueDateCode']])) {
				$lines[] = $this->labels[self::$vatDueDateLabels[$group['dueDateCode']]];
				break;
			}
		}

		$lines = $this->filled(array_map(function ($line) {
			return $line !== null ? trim($line) : null;
		}, $lines));

		return $lines ? '<p>' . implode('<br>', array_map([$this, 'escape'], $lines)) . '</p>' . "\n" : '';
	}

	/**
	 * How to pay by one means: the account to transfer to, the account a direct debit is taken from, or the card
	 *
	 * @param mixed[] $means
	 *
	 * @return string[]
	 */
	private function paymentMeans(array $means)
	{
		$lines = [$means['information']];

		$account = [
			$means['iban'] ? 'iban' : 'account' => $means['account'],
			'bic' => $means['bic'],
			'accountName' => $means['accountName'],
		];
		foreach ($this->filled($account) as $label => $value) {
			$lines[] = $this->labelled($label, $value);
		}

		if ($means['debitedAccount'] !== null) {
			$lines[] = $this->labelled('directDebit', $means['debitedAccount'], (string) $means['mandate'], (string) $means['creditorId']);
		}

		if ($means['card'] !== null) {
			$lines[] = $this->labelled('card', $means['card']);
		}

		return $lines;
	}

	/**
	 * A line's or group's VAT: the rate, or that it is not subject to VAT (category O), which has none
	 *
	 * @param string|null $category
	 * @param float|null $rate
	 *
	 * @return string
	 */
	private function rate($category, $rate)
	{
		if ($category === 'O') {
			return $this->labels['notSubjectToVat'];
		}

		return $rate !== null ? $this->formatter->percent($rate) : '';
	}

	/**
	 * A quantity as the formatter writes it, or nothing when there is none
	 *
	 * @param float|null $number
	 *
	 * @return string
	 */
	private function number($number)
	{
		return $number !== null ? $this->formatter->number($number) : '';
	}

	/**
	 * An amount as the formatter writes it, or nothing when there is none
	 *
	 * @param float|null $amount
	 * @param string|null $currency
	 *
	 * @return string
	 */
	private function money($amount, $currency)
	{
		return $amount !== null ? $this->formatter->money($amount, (string) $currency) : '';
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
	 * A date as the formatter writes it, a date in a format the reader does not parse as it is, or null when there is
	 * none
	 *
	 * @param \DateTimeInterface|string|null $date
	 *
	 * @return string|null
	 */
	private function date($date)
	{
		return $date instanceof \DateTimeInterface ? $this->formatter->date($date) : $date;
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
