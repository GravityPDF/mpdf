<?php

namespace Mpdf\Invoice\Output;

use Mpdf\Invoice\EmbeddedInvoiceInterface;
use Mpdf\Mpdf;
use Mpdf\Pdf\DocumentProfile;
use Mpdf\Strict;

/**
 * An invoice embedded with SetEmbeddedInvoice(), under whatever specification it implements, in place of any embedded
 * before
 */
class EmbeddedInvoiceOutput implements OutputInterface
{

	use Strict;

	/**
	 * @var \Mpdf\Invoice\EmbeddedInvoiceInterface
	 */
	private $invoice;

	/**
	 * @param \Mpdf\Invoice\EmbeddedInvoiceInterface $invoice
	 */
	public function __construct(EmbeddedInvoiceInterface $invoice)
	{
		$this->invoice = $invoice;
	}

	/**
	 * @return \Mpdf\Invoice\EmbeddedInvoiceInterface
	 */
	public function getInvoice()
	{
		return $this->invoice;
	}

	/**
	 * The document must be one the invoice can be embedded in
	 *
	 * @param \Mpdf\Pdf\DocumentProfile $document
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function check(DocumentProfile $document)
	{
		$this->invoice->checkDocument($document);
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function apply(Mpdf $mpdf)
	{
		$mpdf->SetEmbeddedInvoice($this->invoice);
	}

}
