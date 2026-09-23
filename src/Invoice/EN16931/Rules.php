<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\LineItem;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\Arrays;

/**
 * The EN 16931 business rules an invoice's content can break, checked before it is written so an invalid e-invoice is
 * refused rather than sent. The rules a model cannot break by construction, such as the arithmetic of its totals, are
 * not repeated here.
 */
class Rules
{

	use Strict;

	/**
	 * The categories that need the reason they charge no VAT, and the rule that says so
	 *
	 * @var string[]
	 */
	private static $exempt = ['E' => 'BR-E-10', 'AE' => 'BR-AE-10', 'K' => 'BR-IC-10', 'G' => 'BR-G-10', 'O' => 'BR-O-10'];

	/**
	 * The categories that may give no such reason, and the rule that says so
	 *
	 * @var string[]
	 */
	private static $notExempt = ['S' => 'BR-S-10', 'Z' => 'BR-Z-10', 'L' => 'BR-AF-10', 'M' => 'BR-AG-10'];

	/**
	 * Refuse an invoice that breaks any of the rules, naming each it breaks
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function check(Invoice $invoice)
	{
		$broken = self::broken($invoice);
		if ($broken) {
			throw new MpdfException("The invoice breaks EN 16931:\n- " . implode("\n- ", $broken));
		}
	}

	/**
	 * The rules the invoice breaks, each as a message naming it
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string[]
	 */
	public static function broken(Invoice $invoice)
	{
		$seller = $invoice->getSeller();
		$buyer = $invoice->getBuyer();
		$sellerTaxed = $seller->getVatId() !== null || $seller->getTaxNumber() !== null;
		$broken = [];

		if ($seller->getVatId() === null && $seller->getLegalId() === null) {
			$broken[] = 'BR-CO-26: the seller needs a VAT identifier or legal registration, so the buyer can identify it';
		}

		$categories = self::categories($invoice);

		foreach (['S', 'Z', 'E', 'L', 'M'] as $category) {
			if (isset($categories[$category]) && !$sellerTaxed) {
				$broken[] = sprintf('BR-%s-2: VAT category %s needs the seller\'s VAT identifier or tax number', self::ruleCode($category), $category);
			}
		}

		if (isset($categories['AE'])) {
			if (!$sellerTaxed) {
				$broken[] = 'BR-AE-2: a reverse charge (AE) needs the seller\'s VAT identifier or tax number';
			}
			if ($buyer->getVatId() === null && $buyer->getLegalId() === null) {
				$broken[] = 'BR-AE-2: a reverse charge (AE) needs the buyer\'s VAT identifier or legal registration';
			}
		}

		if (isset($categories['K'])) {
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

		if (isset($categories['G']) && $seller->getVatId() === null) {
			$broken[] = 'BR-G-2: an export (G) needs the seller\'s VAT identifier';
		}

		if (isset($categories[LineItem::NOT_SUBJECT_TO_VAT])) {
			if (count($categories) > 1) {
				$broken[] = 'BR-O-11: an invoice not subject to VAT (O) can have no other VAT category';
			}
			if ($seller->getVatId() !== null || $buyer->getVatId() !== null) {
				$broken[] = 'BR-O-2: an invoice not subject to VAT (O) can name no VAT identifier for the seller or the buyer';
			}
		}

		foreach (self::$exempt as $category => $rule) {
			if (isset($categories[$category]) && $invoice->getExemptionReason($category) === null) {
				$broken[] = sprintf('%s: VAT category %s needs the reason it charges no VAT; call setExemptionReason(\'%s\', ...)', $rule, $category, $category);
			}
		}

		foreach (self::$notExempt as $category => $rule) {
			if (isset($categories[$category]) && $invoice->getExemptionReason($category) !== null) {
				$broken[] = sprintf('%s: VAT category %s charges VAT, so can give no reason it is exempt', $rule, $category);
			}
		}

		if ($invoice->getDuePayableAmount() > 0 && $invoice->getDueDate() === null && $invoice->getPaymentTerms() === null) {
			$broken[] = 'BR-CO-25: an amount due needs a due date or payment terms';
		}

		$broken = array_merge($broken, self::unexplained($invoice->getAllowanceCharges(), 'BR-33', 'BR-38', 'on the invoice'));
		foreach ($invoice->getLines() as $line) {
			$broken = array_merge($broken, self::unexplained($line->getAllowanceCharges(), 'BR-42', 'BR-44', 'on "' . $line->getName() . '"'));
		}

		return $broken;
	}

	/**
	 * The VAT categories the invoice's lines, allowances and charges use
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return bool[] By category
	 */
	private static function categories(Invoice $invoice)
	{
		$categories = [];
		foreach ($invoice->getVatBreakdown() as $group) {
			$categories[$group['category']] = true;
		}

		return $categories;
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

	/**
	 * The name EN 16931 gives a category's rules: AF and AG for L and M, the category itself otherwise
	 *
	 * @param string $category
	 *
	 * @return string
	 */
	private static function ruleCode($category)
	{
		return Arrays::get(['L' => 'AF', 'M' => 'AG'], $category, $category);
	}

}
