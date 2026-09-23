<?php

namespace Mpdf\Invoice;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Pdf\DocumentProfile;

/**
 * Composes a trade document into a PDF with the writers given, applying what each outputs (see Output\OutputInterface)
 *
 * Every writer runs, and every output is checked against the document, before any output is applied, so a writer that
 * cannot express the document, or an output the document cannot take, leaves it as it was.
 *
 *     DocumentComposer::compose($mpdf, $invoice, [new CiiInvoiceWriter(FacturX::EN16931)]);
 */
class DocumentComposer
{

	/**
	 * Its one method is static; there is nothing to make
	 */
	private function __construct()
	{
	}

	/**
	 * Run each writer on the document and apply what it outputs, once every output has passed its check
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param \Mpdf\Invoice\TradeDocument $document
	 * @param \Mpdf\Invoice\WriterInterface[] $writers
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function compose(Mpdf $mpdf, TradeDocument $document, array $writers)
	{
		$profile = DocumentProfile::fromMpdf($mpdf);
		$outputs = [];
		foreach ($writers as $writer) {
			if (!$writer instanceof WriterInterface) {
				throw new MpdfException('Each invoice writer must implement ' . WriterInterface::class);
			}

			$output = $writer->output($document);
			$output->check($profile);
			$outputs[] = $output;
		}

		foreach ($outputs as $output) {
			$output->apply($mpdf);
		}
	}

}
