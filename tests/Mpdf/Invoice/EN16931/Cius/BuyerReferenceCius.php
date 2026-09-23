<?php

namespace Mpdf\Invoice\EN16931\Cius;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\FacturX;
use Mpdf\Invoice\Totals;

/**
 * A user's own specification, built on the interface alone: it takes EN 16931 only, requires the buyer's reference, and
 * names a guideline Factur-X does not know, a business process of its own and a rate for every category
 */
class BuyerReferenceCius implements CiusInterface
{

	const GUIDELINE = 'urn:cen.eu:en16931:2017#compliant#urn:example.org:buyer-reference:1.0';

	const BUSINESS_PROCESS = 'urn:example.org:buyer-reference:billing';

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'the buyer reference rules';
	}

	/**
	 * @return string[]
	 */
	public function getProfiles()
	{
		return [FacturX::EN16931];
	}

	/**
	 * @return string
	 */
	public function getGuideline()
	{
		return self::GUIDELINE;
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	public function getBusinessProcess(Invoice $invoice)
	{
		return self::BUSINESS_PROCESS;
	}

	/**
	 * @param string $category
	 *
	 * @return bool
	 */
	public function givesRate($category)
	{
		return true;
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\Totals $totals
	 *
	 * @return string[]
	 */
	public function broken(Invoice $invoice, Totals $totals)
	{
		return $invoice->getBuyerReference() === null ? ['EX-1: the invoice needs the buyer\'s reference'] : [];
	}

}
