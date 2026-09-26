<?php

namespace Mpdf\Invoice;

use Mpdf\Strict;

/**
 * A trade document's totals as they stand, worked out once: a writer that needs several takes them together rather than
 * adding the lines up again for each
 *
 * They are not updated when the document changes; ask the document for them again.
 */
class Totals
{

	use Strict;

	/**
	 * @var float
	 */
	private $lineTotal;

	/**
	 * @var float
	 */
	private $allowanceTotal;

	/**
	 * @var float
	 */
	private $chargeTotal;

	/**
	 * @var float
	 */
	private $taxBasisTotal;

	/**
	 * @var array[]
	 */
	private $vatBreakdown;

	/**
	 * @var float
	 */
	private $taxTotal;

	/**
	 * @var float
	 */
	private $grandTotal;

	/**
	 * @var float
	 */
	private $duePayableAmount;

	/**
	 * @param \Mpdf\Invoice\LineItem[] $lines
	 * @param \Mpdf\Invoice\AllowanceCharge[] $allowanceCharges The document's own, not its lines'
	 * @param float $prepaidAmount
	 */
	public function __construct(array $lines, array $allowanceCharges, $prepaidAmount)
	{
		$lineTotal = 0;
		$allowanceTotal = 0;
		$chargeTotal = 0;
		$taxTotal = 0;
		$groups = [];
		foreach ($lines as $line) {
			$net = $line->getNetAmount();
			$lineTotal += $net;
			$this->addToGroup($groups, $line->getVatCategory(), $line->getVatRate(), $net);
		}

		foreach ($allowanceCharges as $allowanceCharge) {
			if ($allowanceCharge->isCharge()) {
				$chargeTotal += $allowanceCharge->getAmount();
			} else {
				$allowanceTotal += $allowanceCharge->getAmount();
			}
			$this->addToGroup($groups, $allowanceCharge->getVatCategory(), $allowanceCharge->getVatRate(), $allowanceCharge->getSignedAmount());
		}

		foreach ($groups as $key => $group) {
			$groups[$key]['basis'] = round($group['basis'], 2);
			$groups[$key]['amount'] = round($groups[$key]['basis'] * $group['rate'] / 100, 2);
			$taxTotal += $groups[$key]['amount'];
		}

		$this->lineTotal = round($lineTotal, 2);
		$this->allowanceTotal = round($allowanceTotal, 2);
		$this->chargeTotal = round($chargeTotal, 2);
		$this->taxBasisTotal = round($this->lineTotal - $this->allowanceTotal + $this->chargeTotal, 2);
		$this->vatBreakdown = array_values($groups);
		$this->taxTotal = round($taxTotal, 2);
		$this->grandTotal = round($this->taxBasisTotal + $this->taxTotal, 2);
		$this->duePayableAmount = round($this->grandTotal - $prepaidAmount, 2);
	}

	/**
	 * The sum of the lines' net amounts
	 *
	 * @return float
	 */
	public function getLineTotal()
	{
		return $this->lineTotal;
	}

	/**
	 * The sum of the document's allowances, before VAT
	 *
	 * @return float
	 */
	public function getAllowanceTotal()
	{
		return $this->allowanceTotal;
	}

	/**
	 * The sum of the document's charges, before VAT
	 *
	 * @return float
	 */
	public function getChargeTotal()
	{
		return $this->chargeTotal;
	}

	/**
	 * The total VAT is charged on: the lines, less the document's allowances, plus its charges
	 *
	 * @return float
	 */
	public function getTaxBasisTotal()
	{
		return $this->taxBasisTotal;
	}

	/**
	 * The lines, allowances and charges grouped by VAT category and rate, with the VAT each group owes
	 *
	 * @return array[] Each with category, rate, basis and amount
	 */
	public function getVatBreakdown()
	{
		return $this->vatBreakdown;
	}

	/**
	 * The VAT owed across every category
	 *
	 * @return float
	 */
	public function getTaxTotal()
	{
		return $this->taxTotal;
	}

	/**
	 * The total with VAT
	 *
	 * @return float
	 */
	public function getGrandTotal()
	{
		return $this->grandTotal;
	}

	/**
	 * The total with VAT, less what was prepaid
	 *
	 * @return float
	 */
	public function getDuePayableAmount()
	{
		return $this->duePayableAmount;
	}

	/**
	 * Add an amount to the VAT breakdown group of its category and rate
	 *
	 * @param array[] $groups
	 * @param string $category
	 * @param float $rate
	 * @param float $amount
	 */
	private function addToGroup(array &$groups, $category, $rate, $amount)
	{
		$key = $category . ':' . $rate;
		if (!isset($groups[$key])) {
			$groups[$key] = ['category' => $category, 'rate' => $rate, 'basis' => 0];
		}
		$groups[$key]['basis'] += $amount;
	}

}
