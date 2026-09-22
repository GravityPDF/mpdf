<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter;

/**
 * HtmlInvoiceWriter extended to write dates the French way, as its docblock suggests for a locale
 */
class FrenchHtmlInvoiceWriter extends HtmlInvoiceWriter
{

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
