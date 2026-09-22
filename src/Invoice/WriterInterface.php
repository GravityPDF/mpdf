<?php

namespace Mpdf\Invoice;

/**
 * Writes a trade document in one format: the XML a hybrid PDF embeds, or the HTML it prints
 */
interface WriterInterface
{

	const HTML = 'html';

	const XML = 'xml';

	/**
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the writer cannot express the document
	 */
	public function write(TradeDocument $document);

	/**
	 * What write() returns, which decides what Mpdf::WriteInvoice() does with it: HTML is written onto the page,
	 * and XML, which must be a Factur-X / ZUGFeRD Cross Industry Invoice, is embedded with SetFacturX()
	 *
	 * @return string WriterInterface::HTML or WriterInterface::XML
	 */
	public function getFormat();

}
