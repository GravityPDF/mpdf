<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * Writes Cross Industry Invoice or UBL invoice XML as HTML for the page: the parties, the lines, the VAT breakdown, the
 * totals and how to pay
 *
 * It prints what the XML states, totals included, so the printed invoice and the embedded one agree. A Formatter sets
 * how numbers, amounts and dates are written, and labels translate it:
 *
 *     $writer = new HtmlInvoiceWriter(new Formatter(new FrancePreset()), ['380' => 'Facture', 'dueDate' => 'Échéance']);
 *     $mpdf->WriteHTML($writer->write($xml));
 *     $mpdf->SetEmbeddedInvoice(new FacturX($xml));
 *
 * Its elements carry invoice-* classes, which a <style> block ahead of the invoice draws. Pass false as $styles to
 * leave that block out, for a document that styles them itself.
 *
 * A UBL invoice prints the same way, but Factur-X embeds CII only, so a PDF printed from UBL is only a readable copy of
 * the invoice.
 *
 * It reads the XML with InvoiceReader, which neither validates it nor checks its totals.
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
		'383' => 'Debit note',
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
		'exempt' => 'Exempt from VAT',
		'reverseCharge' => 'Reverse charge',
		'intraCommunitySupply' => 'Intra-community supply',
		'export' => 'Export outside the EU',
		'vatGroup' => 'VAT %1$s on %2$s',
		'vatCategoryGroup' => '%1$s on %2$s',
		'amount' => 'Amount',
		'allowance' => 'Discount',
		'charge' => 'Charge',
		'linesTotal' => 'Total of the lines',
		'taxBasisTotal' => 'Total excluding VAT',
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
		'earlyPaymentDiscount' => '%1$s discount if paid within %2$s',
		'earlyPaymentDiscountOn' => '%1$s discount on %3$s if paid within %2$s',
		'latePaymentPenalty' => '%1$s penalty if paid after %2$s',
		'latePaymentPenaltyOn' => '%1$s penalty on %3$s if paid after %2$s',
		'minute' => '%s minute',
		'minutes' => '%s minutes',
		'hour' => '%s hour',
		'hours' => '%s hours',
		'day' => '%s day',
		'days' => '%s days',
		'week' => '%s week',
		'weeks' => '%s weeks',
		'month' => '%s month',
		'months' => '%s months',
		'year' => '%s year',
		'years' => '%s years',
		'kilogram' => '%s kg',
		'tonne' => '%s t',
		'metre' => '%s m',
		'kilometre' => '%s km',
		'squareMetre' => '%s m²',
		'cubicMetre' => '%s m³',
		'litre' => '%s l',
		'kilowattHour' => '%s kWh',
		'vatDueOnInvoice' => 'VAT is due on the invoice date',
		'vatDueOnDelivery' => 'VAT is due on delivery',
		'vatDueOnPayment' => 'VAT is due on payment',
	];

	/**
	 * The label for each UNTDID 2475 code saying when VAT falls due (BT-8), as the reader gives them
	 *
	 * @var string[]
	 */
	private static $vatDueDateLabels = ['5' => 'vatDueOnInvoice', '29' => 'vatDueOnDelivery', '72' => 'vatDueOnPayment'];

	/**
	 * The singular and plural labels of the units quantities and payment periods are given in, by UN/ECE Recommendation
	 * 20 code. A unit written as a symbol has one label for both. A count of pieces has none, so it prints as the
	 * number alone.
	 *
	 * @var string[][]
	 */
	private static $unitLabels = [
		'MIN' => ['minute', 'minutes'],
		'HUR' => ['hour', 'hours'],
		'DAY' => ['day', 'days'],
		'WEE' => ['week', 'weeks'],
		'MON' => ['month', 'months'],
		'ANN' => ['year', 'years'],
		'KGM' => ['kilogram', 'kilogram'],
		'TNE' => ['tonne', 'tonne'],
		'MTR' => ['metre', 'metre'],
		'KMT' => ['kilometre', 'kilometre'],
		'MTK' => ['squareMetre', 'squareMetre'],
		'MTQ' => ['cubicMetre', 'cubicMetre'],
		'LTR' => ['litre', 'litre'],
		'KWH' => ['kilowattHour', 'kilowattHour'],
	];

	/**
	 * The label for each UNTDID 5305 VAT category that has no rate to print in its place
	 *
	 * @var string[]
	 */
	private static $vatCategoryLabels = [
		'O' => 'notSubjectToVat',
		'E' => 'exempt',
		'AE' => 'reverseCharge',
		'K' => 'intraCommunitySupply',
		'G' => 'export',
	];

	/**
	 * The UNTDID 1001 type codes EN 16931 counts as credit notes, titled as 381 unless given a title of their own
	 *
	 * @var string[]
	 */
	private static $creditNoteTypes = ['81', '83', '261', '262', '296', '308', '381', '396', '420', '458', '532'];

	/**
	 * @var string[]
	 */
	private $labels;

	/**
	 * @var \Mpdf\Invoice\Formatter
	 */
	private $formatter;

	/**
	 * @var bool
	 */
	private $styles;

	/**
	 * @param \Mpdf\Invoice\Formatter $formatter
	 * @param string[] $labels Replacements for any of the default labels, keyed as they are, and titles for any other
	 *                         type of invoice, keyed by its UNTDID 1001 code
	 * @param bool $styles Whether to write the <style> block the invoice-* classes are drawn with; false leaves them to
	 *                     styles of your own
	 *
	 * @throws \Mpdf\MpdfException When a label's key is neither a default label's nor a type code
	 */
	public function __construct(Formatter $formatter, array $labels = [], $styles = true)
	{
		foreach (array_keys(array_diff_key($labels, self::$defaultLabels)) as $key) {
			if (!is_int($key)) {
				throw new MpdfException(sprintf('"%s" is not an invoice label; use one of %s', $key, implode(', ', array_filter(array_keys(self::$defaultLabels), 'is_string'))));
			}
		}

		$this->formatter = $formatter;
		$this->labels = $labels + self::$defaultLabels;
		$this->styles = (bool) $styles;
	}

	/**
	 * The invoice as HTML, headed by its type and number, after its <style> block unless $styles was false
	 *
	 * @param string $xml Cross Industry Invoice XML, at any Factur-X / ZUGFeRD profile or as XRechnung CII, or a UBL
	 *                    Invoice or CreditNote
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the XML is neither a Cross Industry Invoice nor a UBL Invoice or CreditNote
	 */
	public function write($xml)
	{
		$invoice = (new InvoiceReader())->read($xml);

		$title = $this->title($invoice['typeCode']);

		$html = $this->styles ? self::$css . "\n" : '';
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
					$line['quantity'] !== null ? $this->quantity($line['quantity'], $line['unitCode']) : '',
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

		return $this->labelled('pricePer', $price, $this->quantity($line['basisQuantity'], $line['basisQuantityUnit']));
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
	 * An allowance's amount as a negative number, or a charge's as a positive one
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
	 * The totals below the lines, the last in bold: the lines' total and the invoice's allowances and charges when it
	 * has any, the total excluding VAT, the VAT by category and rate (or as one sum without a breakdown), the total
	 * including VAT, and any prepayment and rounding with the amount due
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
		$totals[] = [$this->labels['taxBasisTotal'], $sums['taxBasisTotal']];

		foreach ($invoice['vatBreakdown'] as $group) {
			$category = $this->vatCategory($group['category']);
			$basis = $this->money($group['basis'], $currency);
			$label = $category !== null ? $this->labelled('vatCategoryGroup', $category, $basis) : $this->labelled('vatGroup', $this->rate($group['category'], $group['rate']), $basis);
			// The reason is often the category's own name, which the label already gives
			if ($group['exemptionReason'] !== null && strcasecmp($group['exemptionReason'], (string) $category) !== 0) {
				$label .= ' (' . $group['exemptionReason'] . ')';
			}
			$totals[] = [$label, $group['amount']];
		}
		if (!$invoice['vatBreakdown']) {
			$totals[] = [$this->labels['vat'], $sums['taxTotal']];
		}

		$totals[] = [$this->labels['grandTotal'], $sums['grandTotal']];
		if ($sums['prepaidAmount'] || $sums['roundingAmount']) {
			$totals[] = [$this->labels['prepaid'], $sums['prepaidAmount'] ? -$sums['prepaidAmount'] : null];
			$totals[] = [$this->labels['rounding'], $sums['roundingAmount'] ?: null];
			$totals[] = [$this->labels['due'], $sums['duePayableAmount']];
		}

		$totals = array_values(array_filter($totals, function ($total) {
			return $total[1] !== null;
		}));
		$last = count($totals) - 1;

		$html = '<tfoot>';
		foreach ($totals as $i => $total) {
			$html .= $this->total($total[0], $this->money($total[1], $currency), $i === $last);
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
	 * The payment terms, discounts and penalties, the payment reference, each way to pay, and when VAT falls due
	 *
	 * @param mixed[] $invoice
	 *
	 * @return string
	 */
	private function payment(array $invoice)
	{
		$lines = $invoice['paymentTerms'];
		foreach ($invoice['paymentDiscounts'] as $discount) {
			$lines[] = $this->paymentAdjustment('earlyPaymentDiscount', $discount, $invoice['currency']);
		}
		foreach ($invoice['paymentPenalties'] as $penalty) {
			$lines[] = $this->paymentAdjustment('latePaymentPenalty', $penalty, $invoice['currency']);
		}
		if ($invoice['paymentReference'] !== null) {
			$lines[] = $this->labelled('paymentReference', $invoice['paymentReference']);
		}

		foreach ($invoice['paymentMeans'] as $means) {
			$lines = array_merge($lines, $this->paymentMeans($means));
		}

		foreach ($invoice['vatBreakdown'] as $group) {
			if (isset(self::$vatDueDateLabels[(string) $group['dueDateCode']])) {
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
	 * An early payment discount or late payment penalty in words: its rate (or amount), its period, and the amount it is
	 * worked out on when it names one, which switches to the label ending in On
	 *
	 * @param string $label earlyPaymentDiscount or latePaymentPenalty
	 * @param mixed[] $adjustment As InvoiceReader reads paymentDiscounts and paymentPenalties
	 * @param string|null $currency
	 *
	 * @return string|null Null when it gives no period, or neither a rate nor an amount
	 */
	private function paymentAdjustment($label, array $adjustment, $currency)
	{
		if ($adjustment['percent'] !== null) {
			$size = $this->formatter->percent($adjustment['percent']);
		} elseif ($adjustment['amount'] !== null) {
			$size = $this->money($adjustment['amount'], $currency);
		} else {
			return null;
		}

		if ($adjustment['period'] === null) {
			return null;
		}
		$period = $this->period($adjustment['period'], $adjustment['periodUnit']);

		if ($adjustment['basisAmount'] !== null) {
			return $this->labelled($label . 'On', $size, $period, $this->money($adjustment['basisAmount'], $currency));
		}

		return $this->labelled($label, $size, $period);
	}

	/**
	 * A payment period by its unit's label; a unit without one is written by its code, as a bare number would say nothing
	 *
	 * @param float $length
	 * @param string|null $unit UN/ECE Recommendation 20 code, e.g. DAY
	 *
	 * @return string
	 */
	private function period($length, $unit)
	{
		$measure = $this->measure($length, $unit);

		return $measure !== null ? $measure : trim($this->formatter->number($length) . ' ' . $unit);
	}

	/**
	 * A quantity by its unit's label, or the number alone for a count of pieces or a unit without a label
	 *
	 * @param float $quantity
	 * @param string|null $unit UN/ECE Recommendation 20 code, e.g. HUR
	 *
	 * @return string
	 */
	private function quantity($quantity, $unit)
	{
		$measure = $this->measure($quantity, $unit);

		return $measure !== null ? $measure : $this->formatter->number($quantity);
	}

	/**
	 * A number by its unit's label, singular for exactly one, or null for a unit without a label
	 *
	 * @param float $number
	 * @param string|null $unit UN/ECE Recommendation 20 code
	 *
	 * @return string|null
	 */
	private function measure($number, $unit)
	{
		if (!isset(self::$unitLabels[(string) $unit])) {
			return null;
		}

		return $this->labelled(self::$unitLabels[$unit][$number == 1 ? 0 : 1], $this->formatter->number($number));
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
	 * A line's or group's VAT: its category's label for a category without a rate to print, or the rate
	 *
	 * @param string|null $category
	 * @param float|null $rate
	 *
	 * @return string
	 */
	private function rate($category, $rate)
	{
		$label = $this->vatCategory($category);
		if ($label !== null) {
			return $label;
		}

		return $rate !== null ? $this->formatter->percent($rate) : '';
	}

	/**
	 * The label of a VAT category without a rate to print, such as the reverse charge, or null for any other
	 *
	 * @param string|null $category UNTDID 5305 code
	 *
	 * @return string|null
	 */
	private function vatCategory($category)
	{
		return isset(self::$vatCategoryLabels[(string) $category]) ? $this->labels[self::$vatCategoryLabels[$category]] : null;
	}

	/**
	 * The title for a type of invoice: its own, the credit note's for any credit note without one, or the invoice's
	 *
	 * @param string|null $typeCode UNTDID 1001 code
	 *
	 * @return string
	 */
	private function title($typeCode)
	{
		if (isset($this->labels[(string) $typeCode])) {
			return $this->labels[$typeCode];
		}

		return $this->labels[in_array((string) $typeCode, self::$creditNoteTypes, true) ? '381' : '380'];
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
	 * A date as the formatter writes it, a date the reader kept as text unchanged, or null
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
