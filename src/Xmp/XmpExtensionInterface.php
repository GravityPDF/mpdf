<?php

namespace Mpdf\Xmp;

/**
 * Properties in a namespace of their own for the document's XMP metadata, with the PDF/A extension schema that declares them
 *
 * Register one with Mpdf::AddXmpExtension(). mPDF writes every registered schema into the packet's one
 * pdfaExtension:schemas, which PDF/A requires for any namespace it does not define, and each extension's values beside it.
 *
 * @see https://pdfa.org/resource/technical-note-tn-0009-xmp-extension-schemas-in-pdfa-1/
 */
interface XmpExtensionInterface
{

	/**
	 * The extension schema that declares the properties
	 *
	 * Every property is written as an external Text property.
	 *
	 * @return mixed[] 'schema' (a name for people), 'namespaceURI', 'prefix', and 'properties': each property's description by its name
	 */
	public function getXmpExtensionSchema();

	/**
	 * The properties that describe the document
	 *
	 * @return string[] Each property's value by its name, every name declared by getXmpExtensionSchema()
	 */
	public function getXmpProperties();

}
