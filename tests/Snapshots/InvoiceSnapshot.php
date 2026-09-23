<?php

namespace Snapshots;

use Mpdf\Invoice\DocumentComposer;
use Mpdf\Invoice\EN16931\InvoiceFixtures;

/**
 * An invoice printed by HtmlInvoiceWriter through DocumentComposer, in the British convention unless a snapshot
 * says otherwise
 *
 * @group snapshot
 */
abstract class InvoiceSnapshot extends Snapshot
{

	use InvoiceFixtures;

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
		return $this->htmlWriter();
	}

	/**
	 * Print the invoice
	 *
	 * @return void
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf();
		DocumentComposer::compose($this->mpdf, $this->getInvoice(), [$this->getWriter()]);
	}

}
