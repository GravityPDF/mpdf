<?php

namespace Mpdf\Invoice;

/**
 * Writes a trade document in a format of its own, and says what DocumentComposer does with it
 */
interface WriterInterface
{

	/**
	 * The document written, as what DocumentComposer applies to the PDF: an HtmlOutput printed onto the page, an
	 * EmbeddedInvoiceOutput embedded as an e-invoice, or an OutputInterface of the user's own for another format
	 *
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return \Mpdf\Invoice\Output\OutputInterface
	 *
	 * @throws \Mpdf\MpdfException When the writer cannot express the document
	 */
	public function output(TradeDocument $document);

}
