<?php

namespace Mpdf\Invoice;

/**
 * A writer whose format is text, which it gives on its own too: to save, send or attach outside DocumentComposer
 *
 *     file_put_contents('invoice.xml', $writer->write($invoice));
 */
interface StringWriterInterface extends WriterInterface
{

	/**
	 * The document in the writer's format
	 *
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the writer cannot express the document
	 */
	public function write(TradeDocument $document);

}
