<?php

namespace Mpdf\Invoice;

/**
 * Reads what a printed invoice shows from Cross Industry Invoice or UBL XML, whichever the XML is, as
 * AbstractInvoiceReader describes
 *
 *     $invoice = (new InvoiceReader())->read($xml);
 */
class InvoiceReader extends AbstractInvoiceReader
{

	/**
	 * The invoice a parsed document states, read by CiiInvoiceReader or UblInvoiceReader by its root element
	 *
	 * @param \DOMDocument $document
	 *
	 * @return mixed[] As AbstractInvoiceReader describes
	 *
	 * @throws \Mpdf\MpdfException When the document is neither a Cross Industry Invoice nor a UBL Invoice or CreditNote
	 */
	protected function readDocument(\DOMDocument $document)
	{
		$root = $this->root($document, 'Cross Industry Invoice or UBL invoice XML', [
			'{' . CiiInvoiceReader::NS_RSM . '}CrossIndustryInvoice',
			'{' . UblInvoiceReader::NS_INVOICE . '}Invoice',
			'{' . UblInvoiceReader::NS_CREDIT_NOTE . '}CreditNote',
		], []);

		return $this->reader($root)->readDocument($document);
	}

	/**
	 * The reader for the syntax of a root element readDocument() has checked
	 *
	 * @param \DOMElement $root
	 *
	 * @return \Mpdf\Invoice\AbstractInvoiceReader
	 */
	private function reader(\DOMElement $root)
	{
		return $root->namespaceURI === CiiInvoiceReader::NS_RSM ? new CiiInvoiceReader() : new UblInvoiceReader();
	}

}
