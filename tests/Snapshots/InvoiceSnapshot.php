<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter;

/**
 * An invoice printed by HtmlInvoiceWriter through WriteInvoice()
 *
 * @group snapshot
 */
abstract class InvoiceSnapshot extends Snapshot
{

	/**
	 * The invoice to print
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	abstract protected function getInvoice();

	/**
	 * The writer to print it with
	 *
	 * @return \Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter
	 */
	protected function getWriter()
	{
		return new HtmlInvoiceWriter();
	}

	/**
	 * Print the invoice
	 *
	 * @return void
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteInvoice($this->getInvoice(), [$this->getWriter()]);
	}

}
