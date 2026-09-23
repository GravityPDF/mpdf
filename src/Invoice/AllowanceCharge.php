<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * An allowance (a discount or credit) or a charge (shipping, packaging, a fee or surcharge), on a whole document or
 * on one of its lines
 *
 *     AllowanceCharge::allowance(50, 'Loyalty discount')
 *     AllowanceCharge::percentAllowance(10, 938.97, 'Early booking')
 *     AllowanceCharge::charge(9.95, 'Shipping')->setReasonCode('FC')
 *
 * On a document it needs the VAT it bears, set with setVat(), as it changes the base VAT is charged on. On a line it
 * takes the line's VAT.
 */
class AllowanceCharge
{

	use Strict;

	/**
	 * @var bool
	 */
	private $charge;

	/**
	 * @var float
	 */
	private $amount;

	/**
	 * @var float|null
	 */
	private $percent;

	/**
	 * @var float|null
	 */
	private $basis;

	/**
	 * @var string|null
	 */
	private $reason;

	/**
	 * @var string|null
	 */
	private $reasonCode;

	/**
	 * @var string|null
	 */
	private $vatCategory;

	/**
	 * @var float|null
	 */
	private $vatRate;

	/**
	 * @param bool $charge
	 * @param float $amount
	 * @param string|null $reason
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function __construct($charge, $amount, $reason)
	{
		if ($amount < 0) {
			throw new MpdfException('An allowance or charge is a positive amount; an allowance is taken off, a charge added');
		}

		$this->charge = $charge;
		$this->amount = round($amount, 2);
		$this->reason = $reason;
	}

	/**
	 * A discount or credit taken off
	 *
	 * @param float $amount
	 * @param string|null $reason e.g. "Loyalty discount"; give a reason or a reason code
	 *
	 * @return self
	 */
	public static function allowance($amount, $reason = null)
	{
		return new self(false, $amount, $reason);
	}

	/**
	 * A charge added, such as shipping, packaging, a fee or a surcharge
	 *
	 * @param float $amount
	 * @param string|null $reason e.g. "Shipping"; give a reason or a reason code
	 *
	 * @return self
	 */
	public static function charge($amount, $reason = null)
	{
		return new self(true, $amount, $reason);
	}

	/**
	 * A discount of a percentage of an amount, e.g. 10% of the lines
	 *
	 * @param float $percent
	 * @param float $basis The amount the percentage is of
	 * @param string|null $reason
	 *
	 * @return self
	 */
	public static function percentAllowance($percent, $basis, $reason = null)
	{
		return self::allowance(round($basis * $percent / 100, 2), $reason)->setPercentage($percent, $basis);
	}

	/**
	 * A charge of a percentage of an amount, e.g. a 3% card surcharge
	 *
	 * @param float $percent
	 * @param float $basis The amount the percentage is of
	 * @param string|null $reason
	 *
	 * @return self
	 */
	public static function percentCharge($percent, $basis, $reason = null)
	{
		return self::charge(round($basis * $percent / 100, 2), $reason)->setPercentage($percent, $basis);
	}

	/**
	 * @param float $percent
	 * @param float $basis
	 *
	 * @return $this
	 */
	private function setPercentage($percent, $basis)
	{
		$this->percent = (float) $percent;
		$this->basis = round($basis, 2);

		return $this;
	}

	/**
	 * @param string $code UNTDID 5189 for an allowance (e.g. 95 discount), UNTDID 7161 for a charge (e.g. FC freight)
	 *
	 * @return $this
	 */
	public function setReasonCode($code)
	{
		$this->reasonCode = (string) $code;

		return $this;
	}

	/**
	 * The VAT an allowance or charge on a whole document bears, which it takes off or adds to that category's base
	 *
	 * @param float $rate
	 * @param string $category
	 *
	 * @return $this
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function setVat($rate, $category = 'S')
	{
		LineItem::checkVat($category, $rate);

		$this->vatCategory = $category;
		$this->vatRate = (float) $rate;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isCharge()
	{
		return $this->charge;
	}

	/**
	 * @return float
	 */
	public function getAmount()
	{
		return $this->amount;
	}

	/**
	 * The amount as it counts towards a total: taken off for an allowance, added for a charge
	 *
	 * @return float
	 */
	public function getSignedAmount()
	{
		return $this->charge ? $this->amount : -$this->amount;
	}

	/**
	 * @return float|null
	 */
	public function getPercent()
	{
		return $this->percent;
	}

	/**
	 * @return float|null
	 */
	public function getBasis()
	{
		return $this->basis;
	}

	/**
	 * @return string|null
	 */
	public function getReason()
	{
		return $this->reason;
	}

	/**
	 * @return string|null
	 */
	public function getReasonCode()
	{
		return $this->reasonCode;
	}

	/**
	 * @return string|null
	 */
	public function getVatCategory()
	{
		return $this->vatCategory;
	}

	/**
	 * @return float|null
	 */
	public function getVatRate()
	{
		return $this->vatRate;
	}

}
