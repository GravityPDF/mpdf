<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\EN16931\Cius\CiusInterface;
use Mpdf\Invoice\Totals;
use Mpdf\Invoice\VatCategory;
use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * Some of the EN 16931 business rules an invoice's content can break, and those of the CIUS given, checked before it is
 * written so the mistakes they catch are refused rather than sent. The rules a model cannot break by construction, such
 * as the arithmetic of its totals, are not repeated here.
 *
 * Passing them does not make an invoice valid or compliant: validate the XML, and check what applies to the invoice.
 */
class Rules
{

	use Strict;

	/**
	 * Refuse an invoice that breaks any of the EN 16931 rules, or of the CIUS given, naming each it breaks
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function check(Invoice $invoice, CiusInterface $cius)
	{
		$broken = self::broken($invoice, $cius);
		if ($broken) {
			$name = $cius->getName();
			throw new MpdfException(sprintf("The invoice breaks %s:\n- %s", $name === null ? 'EN 16931' : 'EN 16931 and ' . $name, implode("\n- ", $broken)));
		}
	}

	/**
	 * The EN 16931 rules the invoice breaks, and those of the CIUS given, each as a message naming it
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius
	 *
	 * @return string[]
	 */
	public static function broken(Invoice $invoice, CiusInterface $cius)
	{
		$totals = $invoice->getTotals();

		return array_merge(self::brokenInEN16931($invoice, $totals), $cius->broken($invoice, $totals));
	}

	/**
	 * The EN 16931 rules the invoice breaks
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\Totals $totals
	 *
	 * @return string[]
	 */
	private static function brokenInEN16931(Invoice $invoice, Totals $totals)
	{
		$seller = $invoice->getSeller();
		$buyer = $invoice->getBuyer();
		$sellerTaxed = $seller->getVatId() !== null || $seller->getTaxNumber() !== null;
		$broken = [];

		if ($seller->getVatId() === null && $seller->getLegalId() === null) {
			$broken[] = 'BR-CO-26: the seller needs a VAT identifier or legal registration, so the buyer can identify it';
		}

		$categories = array_flip(array_column($totals->getVatBreakdown(), 'category'));

		foreach (array_keys($categories) as $category) {
			if (VatCategory::needsSellerTaxRegistration($category) && !$sellerTaxed) {
				$broken[] = sprintf('BR-%s-2: VAT category %s needs the seller\'s VAT identifier or tax number', VatCategory::getRuleCode($category), $category);
			}
		}

		if (isset($categories[VatCategory::REVERSE_CHARGE])) {
			if (!$sellerTaxed) {
				$broken[] = 'BR-AE-2: a reverse charge (AE) needs the seller\'s VAT identifier or tax number';
			}
			if ($buyer->getVatId() === null && $buyer->getLegalId() === null) {
				$broken[] = 'BR-AE-2: a reverse charge (AE) needs the buyer\'s VAT identifier or legal registration';
			}
		}

		if (isset($categories[VatCategory::INTRA_COMMUNITY])) {
			if ($seller->getVatId() === null || $buyer->getVatId() === null) {
				$broken[] = 'BR-IC-2: an intra-community supply (K) needs the VAT identifiers of the seller and the buyer';
			}
			if ($invoice->getDeliveryDate() === null) {
				$broken[] = 'BR-IC-11: an intra-community supply (K) needs its delivery date; call setDeliveryDate()';
			}
			if ($invoice->getDeliverTo() === null) {
				$broken[] = 'BR-IC-12: an intra-community supply (K) needs the country it was delivered to; call setDeliverTo()';
			}
		}

		if (isset($categories[VatCategory::EXPORT]) && $seller->getVatId() === null) {
			$broken[] = 'BR-G-2: an export (G) needs the seller\'s VAT identifier';
		}

		if (isset($categories[VatCategory::NOT_SUBJECT_TO_VAT])) {
			if (count($categories) > 1) {
				$broken[] = 'BR-O-11: an invoice not subject to VAT (O) can have no other VAT category';
			}
			if ($seller->getVatId() !== null || $buyer->getVatId() !== null) {
				$broken[] = 'BR-O-2: an invoice not subject to VAT (O) can name no VAT identifier for the seller or the buyer';
			}
		}

		foreach (array_keys($categories) as $category) {
			$exempt = VatCategory::isExempt($category);
			$reason = $invoice->getExemptionReason($category);
			if ($exempt && $reason === null) {
				$broken[] = sprintf('BR-%s-10: VAT category %s needs the reason it charges no VAT; call setExemptionReason(\'%s\', ...)', VatCategory::getRuleCode($category), $category, $category);
			} elseif (!$exempt && $reason !== null) {
				$broken[] = sprintf('BR-%s-10: VAT category %s charges VAT, so can give no reason it is exempt', VatCategory::getRuleCode($category), $category);
			}
		}

		if ($totals->getDuePayableAmount() > 0 && $invoice->getDueDate() === null && $invoice->getPaymentTerms() === null) {
			$broken[] = 'BR-CO-25: an amount due needs a due date or payment terms';
		}

		$broken = array_merge($broken, self::unexplained($invoice->getAllowanceCharges(), 'BR-33', 'BR-38', 'on the invoice'));
		foreach ($invoice->getLines() as $line) {
			$broken = array_merge($broken, self::unexplained($line->getAllowanceCharges(), 'BR-42', 'BR-44', 'on "' . $line->getName() . '"'));
		}

		return $broken;
	}

	/**
	 * The rules broken by allowances or charges that give neither a reason nor a reason code
	 *
	 * @param \Mpdf\Invoice\AllowanceCharge[] $allowanceCharges
	 * @param string $allowanceRule
	 * @param string $chargeRule
	 * @param string $where
	 *
	 * @return string[]
	 */
	private static function unexplained(array $allowanceCharges, $allowanceRule, $chargeRule, $where)
	{
		$broken = [];
		foreach ($allowanceCharges as $allowanceCharge) {
			if ($allowanceCharge->getReason() === null && $allowanceCharge->getReasonCode() === null) {
				$charge = $allowanceCharge->isCharge();
				$broken[] = sprintf('%s: a %s %s needs a reason or a reason code', $charge ? $chargeRule : $allowanceRule, $charge ? 'charge' : 'allowance', $where);
			}
		}

		return array_unique($broken);
	}

}
