<?php

namespace Mpdf\Invoice;

use Mpdf\Pdf\DocumentProfile;
use Mpdf\Xmp\XmpExtensionInterface;

/**
 * Invoice XML embedded in a PDF/A document, with the XMP that declares it, as Factur-X and the specifications like it define
 *
 * Set one with Mpdf::SetEmbeddedInvoice(). FacturX is the one mPDF provides; extend it for a later version of Factur-X, or
 * implement this for a specification that differs further.
 */
interface EmbeddedInvoiceInterface extends XmpExtensionInterface
{

	/**
	 * The invoice as an entry of Mpdf::$associatedFiles
	 *
	 * @return mixed[] Its 'name', 'content', 'mime', 'description' and 'AFRelationship'
	 */
	public function getAssociatedFile();

	/**
	 * Refuse a document the specification does not let the invoice be embedded in
	 *
	 * @param \Mpdf\Pdf\DocumentProfile $document The standards the document is being written to
	 *
	 * @throws \Mpdf\MpdfException naming what the document must be, and how to make it so
	 */
	public function checkDocument(DocumentProfile $document);

}
