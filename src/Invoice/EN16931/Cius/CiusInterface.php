<?php

namespace Mpdf\Invoice\EN16931\Cius;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\Totals;

/**
 * A Core Invoice Usage Specification: a country's or network's narrowing of EN 16931, such as Germany's XRechnung
 *
 * It adds rules, may name a guideline of its own and default what it requires, and says which profiles it takes. A
 * writer applies one on top of EN 16931, EN16931 itself when given none, so the writer names no country.
 */
interface CiusInterface
{

	/**
	 * The name refusals give it beside EN 16931, e.g. XRechnung; null for EN 16931 alone
	 *
	 * @return string|null
	 */
	public function getName();

	/**
	 * The Factur-X / ZUGFeRD profiles the specification takes, e.g. [FacturX::EN16931]; null for any
	 *
	 * The profiles are Factur-X's conformance levels, the subsets of EN 16931 a Factur-X writer chooses between; EN
	 * 16931 itself has none.
	 *
	 * @return string[]|null
	 */
	public function getProfiles();

	/**
	 * The guideline ID (BT-24) the invoice names in place of the profile's, or null to keep the profile's
	 *
	 * @return string|null
	 */
	public function getGuideline();

	/**
	 * The business process (BT-23) the invoice names, which the specification may default
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string|null
	 */
	public function getBusinessProcess(Invoice $invoice);

	/**
	 * Whether the VAT breakdown gives the rate (BT-119) of a category, which EN 16931 leaves out for O
	 *
	 * The breakdown alone: EN 16931 forbids a rate for O on lines, allowances and charges (BR-O-05, BR-O-06, BR-O-07),
	 * whatever the CIUS, so the writer never asks there. In the breakdown BR-48 lets O go without one, and XRechnung's
	 * BR-DE-14 requires it.
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public function givesRate($category);

	/**
	 * The specification's rules the invoice breaks, beside EN 16931's, each as a message naming the rule
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\Totals $totals The invoice's totals, worked out once for every rule
	 *
	 * @return string[]
	 */
	public function broken(Invoice $invoice, Totals $totals);

}
