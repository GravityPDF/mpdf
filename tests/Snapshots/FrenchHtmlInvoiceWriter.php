<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter;

/**
 * HtmlInvoiceWriter extended to write numbers, amounts and dates the French way, as its docblock suggests for a locale
 */
class FrenchHtmlInvoiceWriter extends HtmlInvoiceWriter
{

	/**
	 * @param float $number
	 *
	 * @return string
	 */
	protected function number($number)
	{
		return str_replace('.', ',', parent::number($number));
	}

	/**
	 * @param float $amount
	 * @param string $currency
	 *
	 * @return string
	 */
	protected function money($amount, $currency)
	{
		return number_format($amount, 2, ',', "\xc2\xa0") . "\xc2\xa0" . ($currency === 'EUR' ? '€' : $currency);
	}

	/**
	 * @param \DateTimeInterface|null $date
	 *
	 * @return string|null
	 */
	protected function date($date)
	{
		return $date !== null ? $date->format('d/m/Y') : null;
	}

}
