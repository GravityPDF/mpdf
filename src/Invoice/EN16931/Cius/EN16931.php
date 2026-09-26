<?php

namespace Mpdf\Invoice\EN16931\Cius;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\Totals;
use Mpdf\Invoice\VatCategory;
use Mpdf\Strict;

/**
 * EN 16931 with no specification on top, which a writer applies until given another
 *
 * A specification extends it and overrides only what it changes, giving its name and rules.
 */
class EN16931 implements CiusInterface
{

	use Strict;

	/**
	 * @return string|null
	 */
	public function getName()
	{
		return null;
	}

	/**
	 * @return string[]|null
	 */
	public function getProfiles()
	{
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getGuideline()
	{
		return null;
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string|null
	 */
	public function getBusinessProcess(Invoice $invoice)
	{
		return $invoice->getBusinessProcess();
	}

	/**
	 * Every category but O, which EN 16931's BR-48 lets go without a rate in the breakdown
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public function givesRate($category)
	{
		return VatCategory::hasRate($category);
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\Totals $totals The invoice's totals, worked out once for every rule
	 *
	 * @return string[]
	 */
	public function broken(Invoice $invoice, Totals $totals)
	{
		return [];
	}

	/**
	 * Whether the seller and the buyer both have an electronic address, which EN 16931 leaves optional
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return bool
	 */
	protected function bothReachable(Invoice $invoice)
	{
		return $invoice->getSeller()->getElectronicAddress() !== null && $invoice->getBuyer()->getElectronicAddress() !== null;
	}

}
