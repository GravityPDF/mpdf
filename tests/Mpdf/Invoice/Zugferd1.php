<?php

namespace Mpdf\Invoice;

/**
 * ZUGFeRD 1.0, the forerunner of Factur-X, which differs from it in its levels, file name and XMP namespace
 */
class Zugferd1 extends FacturX
{

	const COMFORT = 'COMFORT';

	/**
	 * @return string
	 */
	protected function getSpecification()
	{
		return 'ZUGFeRD';
	}

	/**
	 * @return string[]
	 */
	protected function getGuidelines()
	{
		return [
			'urn:ferd:CrossIndustryDocument:invoice:1p0:basic' => self::BASIC,
			'urn:ferd:CrossIndustryDocument:invoice:1p0:comfort' => self::COMFORT,
			'urn:ferd:CrossIndustryDocument:invoice:1p0:extended' => self::EXTENDED,
		];
	}

	/**
	 * @return string
	 */
	protected function getFilename()
	{
		return 'ZUGFeRD-invoice.xml';
	}

	/**
	 * @return string
	 */
	protected function getXmpNamespaceURI()
	{
		return 'urn:ferd:pdfa:CrossIndustryDocument:invoice:1p0#';
	}

	/**
	 * @return string
	 */
	protected function getXmpPrefix()
	{
		return 'zf';
	}

}
