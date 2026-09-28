<?php

namespace Mpdf\Invoice;

/**
 * Reads what a printed invoice shows from Cross Industry Invoice or UBL XML, whichever the XML is, into the array
 * AbstractInvoiceReader describes
 *
 *     $invoice = (new InvoiceReader())->read($xml);
 */
class InvoiceReader extends AbstractInvoiceReader
{

	/**
	 * The reader for each namespace a root element can be in
	 *
	 * @var string[]
	 */
	private static $readers = [
		CiiInvoiceReader::NS_RSM => CiiInvoiceReader::class,
		UblInvoiceReader::NS_INVOICE => UblInvoiceReader::class,
		UblInvoiceReader::NS_CREDIT_NOTE => UblInvoiceReader::class,
	];

	/**
	 * The invoice a parsed document states, read by the reader for its root element's namespace
	 *
	 * @param \DOMDocument $document
	 *
	 * @return mixed[] As AbstractInvoiceReader describes
	 *
	 * @throws \Mpdf\MpdfException When the document is neither a Cross Industry Invoice nor a UBL Invoice or CreditNote
	 */
	protected function readDocument(\DOMDocument $document)
	{
		$root = $document->documentElement;
		if ($root->namespaceURI === null || !isset(self::$readers[$root->namespaceURI])) {
			throw $this->unreadable($root, 'Cross Industry Invoice or UBL invoice XML');
		}

		return $this->reader(self::$readers[$root->namespaceURI])->readDocument($document);
	}

	/**
	 * A reader of the class named
	 *
	 * @param string $class
	 *
	 * @return \Mpdf\Invoice\AbstractInvoiceReader
	 */
	private function reader($class)
	{
		return new $class();
	}

}
