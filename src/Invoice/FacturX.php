<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Pdf\DocumentProfile;
use Mpdf\Strict;

/**
 * The EN 16931 invoice XML that makes a PDF/A-3 document a Factur-X / ZUGFeRD e-invoice, and the XMP that declares it
 *
 * What a version of the specification decides, from its guideline IDs to the name the XML is embedded under, comes from a
 * protected method, so a later version or a close relative is a subclass that overrides the ones that changed. The
 * constructor calls them, so they must not rely on state a subclass sets after calling it.
 *
 * @see https://fnfe-mpe.org/factur-x/factur-x_en/ Factur-X, the specification shared with ZUGFeRD
 * @see https://www.ferd-net.de/en/downloads/publications ZUGFeRD
 * @see https://www.iso.org/standard/57229.html ISO 19005-3 (PDF/A-3)
 */
class FacturX implements EmbeddedInvoiceInterface
{

	use Strict;

	const MINIMUM = 'MINIMUM';

	const BASIC_WL = 'BASIC WL';

	const BASIC = 'BASIC';

	const EN16931 = 'EN 16931';

	const EXTENDED = 'EXTENDED';

	const XRECHNUNG = 'XRECHNUNG';

	/**
	 * @var string
	 */
	protected $xml;

	/**
	 * @var string
	 */
	protected $conformanceLevel;

	/**
	 * @param string $xml The Cross Industry Invoice XML
	 * @param string|null $conformanceLevel One of the levels getGuidelines() names, e.g. FacturX::EN16931; read from the invoice when null
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function __construct($xml, $conformanceLevel = null)
	{
		if (!is_string($xml) || trim($xml) === '') {
			throw new MpdfException(sprintf('The %s invoice XML must be a non-empty string.', $this->getSpecification()));
		}

		$conformanceLevel = strtoupper($conformanceLevel === null ? $this->readLevel($xml) : $conformanceLevel);
		if (!in_array($conformanceLevel, $this->getGuidelines(), true)) {
			throw new MpdfException(sprintf('%s conformance level "%s" is not valid. %s', $this->getSpecification(), $conformanceLevel, $this->passTheLevel()));
		}

		$this->xml = $xml;
		$this->conformanceLevel = $conformanceLevel;
	}

	/**
	 * The name of the specification, for messages and the file's description
	 *
	 * @return string
	 */
	protected function getSpecification()
	{
		return 'Factur-X';
	}

	/**
	 * Conformance level by the guideline ID in ExchangedDocumentContext, a trailing * matching any ID that starts with the rest
	 *
	 * An exact ID wins over a prefix. Any XRechnung version reads as XRECHNUNG.
	 *
	 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
	 * @see https://xeinkauf.de/xrechnung/
	 *
	 * @return string[]
	 */
	protected function getGuidelines()
	{
		return [
			'urn:factur-x.eu:1p0:minimum' => self::MINIMUM,
			'urn:factur-x.eu:1p0:basicwl' => self::BASIC_WL,
			'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic' => self::BASIC,
			'urn:cen.eu:en16931:2017' => self::EN16931,
			'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended' => self::EXTENDED,
			'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung*' => self::XRECHNUNG,
		];
	}

	/**
	 * The conformance level the invoice's guideline ID names
	 *
	 * Called by the constructor, before any state of a subclass's own is set.
	 *
	 * @param string $xml
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	protected function readLevel($xml)
	{
		if (!preg_match('/GuidelineSpecifiedDocumentContextParameter>\s*<(?:[\w.-]+:)?ID>\s*([^<\s]+)\s*</', $xml, $match)) {
			throw new MpdfException('The invoice XML does not name a guideline (the ID in GuidelineSpecifiedDocumentContextParameter), so its conformance level is unknown. ' . $this->passTheLevel());
		}

		$guideline = $match[1];
		$guidelines = $this->getGuidelines();
		if (isset($guidelines[$guideline])) {
			return $guidelines[$guideline];
		}

		foreach ($guidelines as $id => $level) {
			if (substr($id, -1) === '*' && strpos($guideline, substr($id, 0, -1)) === 0) {
				return $level;
			}
		}

		throw new MpdfException(sprintf('Guideline "%s" is not a %s guideline, so the conformance level is unknown. %s', $guideline, $this->getSpecification(), $this->passTheLevel()));
	}

	/**
	 * The name the specification requires the XML to be embedded under
	 *
	 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
	 *
	 * @return string
	 */
	protected function getFilename()
	{
		return $this->conformanceLevel === self::XRECHNUNG ? 'xrechnung.xml' : 'factur-x.xml';
	}

	/**
	 * How the XML relates to the printed invoice
	 *
	 * MINIMUM and BASIC WL carry too little to replace the printed invoice, so Factur-X relates them as Data, not Alternative.
	 *
	 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
	 *
	 * @return string
	 */
	protected function getRelationship()
	{
		return in_array($this->conformanceLevel, [self::MINIMUM, self::BASIC_WL], true) ? 'Data' : 'Alternative';
	}

	/**
	 * The namespace of the XMP properties
	 *
	 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
	 *
	 * @return string
	 */
	protected function getXmpNamespaceURI()
	{
		return 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';
	}

	/**
	 * The prefix the XMP properties are written with
	 *
	 * @return string
	 */
	protected function getXmpPrefix()
	{
		return 'fx';
	}

	/**
	 * @return mixed[]
	 */
	public function getAssociatedFile()
	{
		return [
			'name' => $this->getFilename(),
			'content' => $this->xml,
			'mime' => 'text/xml',
			'description' => $this->getSpecification() . ' invoice',
			'AFRelationship' => $this->getRelationship(),
		];
	}

	/**
	 * Factur-X is defined only for PDF/A-3
	 *
	 * @see https://www.iso.org/standard/57229.html
	 *
	 * @param \Mpdf\Pdf\DocumentProfile $document
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function checkDocument(DocumentProfile $document)
	{
		if (!$document->conformsTo(DocumentProfile::PDFA, '3')) {
			$label = $document->getLabel(DocumentProfile::PDFA);
			throw new MpdfException(sprintf('SetEmbeddedInvoice() needs a PDF/A-3 document for %s, but this one is %s. Set PDFA to true and PDFAversion to 3-B or 3-U in the constructor configuration.', get_class($this), $label === null ? 'not PDF/A' : $label));
		}
	}

	/**
	 * The PDF/A extension schema that declares the invoice's XMP properties
	 *
	 * @see https://fnfe-mpe.org/factur-x/factur-x_en/ The fx schema
	 *
	 * @return mixed[]
	 */
	public function getXmpExtensionSchema()
	{
		return [
			'schema' => $this->getSpecification() . ' PDFA Extension Schema',
			'namespaceURI' => $this->getXmpNamespaceURI(),
			'prefix' => $this->getXmpPrefix(),
			'properties' => [
				'DocumentFileName' => 'The name of the embedded XML document',
				'DocumentType' => 'The type of the hybrid document in capital letters, e.g. INVOICE or ORDER',
				'Version' => 'The actual version of the standard applying to the embedded XML document',
				'ConformanceLevel' => 'The conformance level of the embedded XML document',
			],
		];
	}

	/**
	 * The XMP properties that describe the embedded invoice, by name
	 *
	 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
	 *
	 * @return string[]
	 */
	public function getXmpProperties()
	{
		return [
			'DocumentType' => 'INVOICE',
			'DocumentFileName' => $this->getFilename(),
			'Version' => '1.0',
			'ConformanceLevel' => $this->conformanceLevel,
		];
	}

	/**
	 * Where to give the conformance level the invoice does not name
	 *
	 * @return string
	 */
	private function passTheLevel()
	{
		return sprintf('Pass one of %s as the second argument to the %s constructor.', implode(', ', array_unique($this->getGuidelines())), get_class($this));
	}

}
