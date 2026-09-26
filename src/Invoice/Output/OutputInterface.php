<?php

namespace Mpdf\Invoice\Output;

use Mpdf\Mpdf;
use Mpdf\Pdf\DocumentProfile;

/**
 * What a writer's output does to the document: printed onto the page, embedded, attached, or anything a format of the
 * user's own needs
 *
 * DocumentComposer checks every output before it applies any.
 */
interface OutputInterface
{

	/**
	 * Refuse a document the output cannot be applied to
	 *
	 * @param \Mpdf\Pdf\DocumentProfile $document
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function check(DocumentProfile $document);

	/**
	 * Apply the output to the document
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function apply(Mpdf $mpdf);

}
