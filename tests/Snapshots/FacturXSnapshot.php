<?php

namespace Snapshots;

use Mpdf\Invoice\FacturX;

/**
 * A Factur-X invoice: a PDF/A-3b document printing the invoice XML from tests/data/invoice, with that XML embedded at
 * the level its guideline names
 *
 * @group snapshot
 */
abstract class FacturXSnapshot extends InvoiceSnapshot
{

	/**
	 * Print the invoice and embed its XML
	 *
	 * @return void
	 */
	public function generatePdf()
	{
		$xml = $this->getXml();

		$this->mpdf = $this->createMpdf(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B']);
		$this->mpdf->WriteHTML($this->getWriter()->write($xml));
		$this->mpdf->SetEmbeddedInvoice(new FacturX($xml));
	}

}
