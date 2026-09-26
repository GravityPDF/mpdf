<?php

namespace Mpdf\Invoice\Output;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Pdf\DocumentProfile;
use Mpdf\Strict;

/**
 * A file attached to the document beside those set before, e.g. the invoice as UBL, JSON or CSV
 *
 * A plain PDF and PDF/A-3 take any file. PDF/A-1 takes none, and PDF/A-2 only files that are PDF/A themselves, which
 * this does not check, so both are refused.
 *
 * @see https://www.iso.org/standard/57229.html ISO 19005-3 (PDF/A-3), associated files
 */
class AttachmentOutput implements OutputInterface
{

	use Strict;

	/**
	 * @var mixed[]
	 */
	private $file;

	/**
	 * @param mixed[] $file As SetAssociatedFiles() takes one: its name and mime, its content or a path to it, and
	 *                      optionally a description and AFRelationship, Supplement unless given
	 */
	public function __construct(array $file)
	{
		$this->file = $file + ['AFRelationship' => 'Supplement'];
	}

	/**
	 * Refuse PDF/A-1 and PDF/A-2
	 *
	 * @param \Mpdf\Pdf\DocumentProfile $document
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function check(DocumentProfile $document)
	{
		if ($document->conformsTo(DocumentProfile::PDFA, '1') || $document->conformsTo(DocumentProfile::PDFA, '2')) {
			throw new MpdfException(sprintf('%s cannot attach %s; only PDF/A-3 among the PDF/A parts takes files of any kind. Set PDFAversion to 3-B or 3-U.', $document->getLabel(DocumentProfile::PDFA), $this->file['name']));
		}
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 */
	public function apply(Mpdf $mpdf)
	{
		$mpdf->SetAssociatedFiles(array_merge((array) $mpdf->associatedFiles, [$this->file]));
	}

}
