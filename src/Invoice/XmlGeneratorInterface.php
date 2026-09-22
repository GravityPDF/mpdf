<?php

namespace Mpdf\Invoice;

/**
 * Turns a trade document into the XML a hybrid PDF carries, e.g. the CII of a Factur-X invoice
 */
interface XmlGeneratorInterface
{

	/**
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the generator cannot express the document
	 */
	public function generate(TradeDocument $document);

}
