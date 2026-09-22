<?php

namespace Mpdf\Invoice\PdfA3;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * The EN 16931 invoice XML that makes a PDF/A-3 document a Factur-X / ZUGFeRD e-invoice, and the XMP that declares it
 */
class FacturX
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
	private static $xmpNamespace = 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';

	/**
	 * Conformance level by the guideline ID in ExchangedDocumentContext
	 *
	 * @var string[]
	 */
	private static $guidelines = [
		'urn:factur-x.eu:1p0:minimum' => self::MINIMUM,
		'urn:factur-x.eu:1p0:basicwl' => self::BASIC_WL,
		'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic' => self::BASIC,
		'urn:cen.eu:en16931:2017' => self::EN16931,
		'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended' => self::EXTENDED,
	];

	/**
	 * @var string
	 */
	private $xml;

	/**
	 * @var string
	 */
	private $conformanceLevel;

	/**
	 * @param string $xml The Cross Industry Invoice XML
	 * @param string|null $conformanceLevel MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED or XRECHNUNG; read from the invoice when null
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function __construct($xml, $conformanceLevel = null)
	{
		if (!is_string($xml) || trim($xml) === '') {
			throw new MpdfException('A Factur-X invoice needs its XML');
		}

		if ($conformanceLevel === null) {
			$conformanceLevel = $this->readConformanceLevel($xml);
		}

		$this->xml = $xml;
		$this->conformanceLevel = self::checkLevel($conformanceLevel, array_merge(array_values(self::$guidelines), [self::XRECHNUNG]));
	}

	/**
	 * The guideline ID an invoice of a conformance level names in ExchangedDocumentContext
	 *
	 * @param string $conformanceLevel Any level but XRECHNUNG, whose guideline ID names the XRechnung version
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function getGuideline($conformanceLevel)
	{
		return array_search(self::checkLevel($conformanceLevel, self::$guidelines), self::$guidelines, true);
	}

	/**
	 * A conformance level in the case Factur-X writes it, provided it is one of those given
	 *
	 * @param string $conformanceLevel
	 * @param string[] $levels
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private static function checkLevel($conformanceLevel, array $levels)
	{
		$conformanceLevel = strtoupper($conformanceLevel);
		if (!in_array($conformanceLevel, $levels, true)) {
			throw new MpdfException(sprintf('Factur-X conformance level "%s" is not one of %s', $conformanceLevel, implode(', ', $levels)));
		}

		return $conformanceLevel;
	}

	/**
	 * The name Factur-X requires the XML to be embedded under
	 *
	 * @return string
	 */
	private function getFilename()
	{
		return $this->conformanceLevel === self::XRECHNUNG ? 'xrechnung.xml' : 'factur-x.xml';
	}

	/**
	 * The invoice as an entry of Mpdf::$associatedFiles
	 *
	 * MINIMUM and BASIC WL carry too little to replace the printed invoice, so Factur-X relates them as Data, not Alternative.
	 *
	 * @return mixed[]
	 */
	public function getAssociatedFile()
	{
		return [
			'name' => $this->getFilename(),
			'content' => $this->xml,
			'mime' => 'text/xml',
			'description' => 'Factur-X invoice',
			'AFRelationship' => in_array($this->conformanceLevel, [self::MINIMUM, self::BASIC_WL], true) ? 'Data' : 'Alternative',
		];
	}

	/**
	 * The fx properties for the XMP packet, with the extension schema PDF/A requires for any namespace it does not define
	 *
	 * @param string $about The rdf:about every other description in the packet uses
	 *
	 * @return string
	 */
	public function getXmpRdf($about)
	{
		$properties = [
			'DocumentFileName' => 'The name of the embedded XML document',
			'DocumentType' => 'The type of the hybrid document in capital letters, e.g. INVOICE or ORDER',
			'Version' => 'The actual version of the standard applying to the embedded XML document',
			'ConformanceLevel' => 'The conformance level of the embedded XML document',
		];

		$m = '   <rdf:Description rdf:about="' . $about . '" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#" xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">' . "\n";
		$m .= '    <pdfaExtension:schemas>' . "\n";
		$m .= '     <rdf:Bag>' . "\n";
		$m .= '      <rdf:li rdf:parseType="Resource">' . "\n";
		$m .= '       <pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema>' . "\n";
		$m .= '       <pdfaSchema:namespaceURI>' . self::$xmpNamespace . '</pdfaSchema:namespaceURI>' . "\n";
		$m .= '       <pdfaSchema:prefix>fx</pdfaSchema:prefix>' . "\n";
		$m .= '       <pdfaSchema:property>' . "\n";
		$m .= '        <rdf:Seq>' . "\n";
		foreach ($properties as $name => $description) {
			$m .= '         <rdf:li rdf:parseType="Resource">' . "\n";
			$m .= '          <pdfaProperty:name>' . $name . '</pdfaProperty:name>' . "\n";
			$m .= '          <pdfaProperty:valueType>Text</pdfaProperty:valueType>' . "\n";
			$m .= '          <pdfaProperty:category>external</pdfaProperty:category>' . "\n";
			$m .= '          <pdfaProperty:description>' . $description . '</pdfaProperty:description>' . "\n";
			$m .= '         </rdf:li>' . "\n";
		}
		$m .= '        </rdf:Seq>' . "\n";
		$m .= '       </pdfaSchema:property>' . "\n";
		$m .= '      </rdf:li>' . "\n";
		$m .= '     </rdf:Bag>' . "\n";
		$m .= '    </pdfaExtension:schemas>' . "\n";
		$m .= '   </rdf:Description>' . "\n";

		$m .= '   <rdf:Description rdf:about="' . $about . '" xmlns:fx="' . self::$xmpNamespace . '">' . "\n";
		$m .= '    <fx:DocumentType>INVOICE</fx:DocumentType>' . "\n";
		$m .= '    <fx:DocumentFileName>' . $this->getFilename() . '</fx:DocumentFileName>' . "\n";
		$m .= '    <fx:Version>1.0</fx:Version>' . "\n";
		$m .= '    <fx:ConformanceLevel>' . $this->conformanceLevel . '</fx:ConformanceLevel>' . "\n";
		$m .= '   </rdf:Description>' . "\n";

		return $m;
	}

	/**
	 * The conformance level named by the invoice's guideline ID
	 *
	 * @param string $xml
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function readConformanceLevel($xml)
	{
		if (!preg_match('/GuidelineSpecifiedDocumentContextParameter>\s*<(?:[\w.-]+:)?ID>\s*([^<\s]+)\s*</', $xml, $match)) {
			throw new MpdfException('The invoice XML names no guideline in ExchangedDocumentContext; pass the Factur-X conformance level');
		}

		$guideline = $match[1];
		if (isset(self::$guidelines[$guideline])) {
			return self::$guidelines[$guideline];
		}

		if (strpos($guideline, 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung') === 0) {
			return self::XRECHNUNG;
		}

		throw new MpdfException(sprintf('Guideline "%s" is not a Factur-X profile; pass the Factur-X conformance level', $guideline));
	}

}
