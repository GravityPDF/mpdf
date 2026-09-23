<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * A line of a trade document: what was supplied, how many, at what net price and under which VAT category
 */
class LineItem
{

	use Strict;

	/**
	 * The VAT category of a supply outside the scope of VAT, which has no rate
	 */
	const NOT_SUBJECT_TO_VAT = 'O';

	/**
	 * The UNTDID 5305 VAT category codes EN 16931 accepts
	 *
	 * @var string[]
	 */
	private static $vatCategories = ['S', 'Z', 'E', 'AE', 'K', 'G', 'O', 'L', 'M'];

	/**
	 * The categories that charge no VAT, whose rate EN 16931 requires to be 0
	 *
	 * @var string[]
	 */
	private static $zeroRatedCategories = ['Z', 'E', 'AE', 'K', 'G'];

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var float
	 */
	private $quantity;

	/**
	 * @var float
	 */
	private $unitPrice;

	/**
	 * @var float
	 */
	private $vatRate;

	/**
	 * @var string
	 */
	private $vatCategory;

	/**
	 * @var string
	 */
	private $unitCode = 'C62';

	/**
	 * @var string|null
	 */
	private $description;

	/**
	 * @var \Mpdf\Invoice\AllowanceCharge[]
	 */
	private $allowanceCharges = [];

	/**
	 * @param string $name
	 * @param float $quantity
	 * @param float $unitPrice The net price of one unit, before VAT; never negative, so give a credit as a negative
	 *                         quantity or an allowance
	 * @param float $vatRate The VAT rate in percent, e.g. 20; 0 for categories that charge none
	 * @param string $vatCategory S (standard), Z (zero rated), E (exempt), AE (reverse charge), K (intra-community),
	 *                            G (export), O (not subject to VAT), L or M (Canary Islands, Ceuta and Melilla)
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function __construct($name, $quantity, $unitPrice, $vatRate, $vatCategory = 'S')
	{
		self::checkVat($vatCategory, $vatRate);

		if ($unitPrice < 0) {
			throw new MpdfException(sprintf('The price of "%s" is negative; EN 16931 takes a negative quantity or an allowance instead', $name));
		}

		$this->name = $name;
		$this->quantity = (float) $quantity;
		$this->unitPrice = (float) $unitPrice;
		$this->vatRate = (float) $vatRate;
		$this->vatCategory = $vatCategory;
	}

	/**
	 * Refuse a VAT category EN 16931 does not know, or a rate its category cannot have: standard rated (S) above 0,
	 * the categories that charge no VAT at 0
	 *
	 * @param string $category
	 * @param float $rate
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function checkVat($category, $rate)
	{
		if (!in_array($category, self::$vatCategories, true)) {
			throw new MpdfException(sprintf('VAT category "%s" is not one of %s', $category, implode(', ', self::$vatCategories)));
		}

		if ($category === 'S' && $rate <= 0) {
			throw new MpdfException('A standard rated (S) VAT category needs a rate above 0; use Z for zero rated');
		}

		if ((in_array($category, self::$zeroRatedCategories, true) || $category === self::NOT_SUBJECT_TO_VAT) && $rate != 0) {
			throw new MpdfException(sprintf('VAT category %s charges no VAT, so its rate is 0', $category));
		}
	}

	/**
	 * A discount taken off or a charge added to this line alone, which counts towards its net amount
	 *
	 * @param \Mpdf\Invoice\AllowanceCharge $allowanceCharge
	 *
	 * @return $this
	 */
	public function addAllowanceCharge(AllowanceCharge $allowanceCharge)
	{
		$this->allowanceCharges[] = $allowanceCharge;

		return $this;
	}

	/**
	 * @param string $unitCode The UN/ECE Recommendation 20 unit, e.g. HUR for hours or KGM for kilograms; C62 (one) by default
	 *
	 * @return $this
	 */
	public function setUnitCode($unitCode)
	{
		$this->unitCode = $unitCode;

		return $this;
	}

	/**
	 * @param string $description
	 *
	 * @return $this
	 */
	public function setDescription($description)
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return float
	 */
	public function getQuantity()
	{
		return $this->quantity;
	}

	/**
	 * @return float
	 */
	public function getUnitPrice()
	{
		return $this->unitPrice;
	}

	/**
	 * @return float
	 */
	public function getVatRate()
	{
		return $this->vatRate;
	}

	/**
	 * @return string
	 */
	public function getVatCategory()
	{
		return $this->vatCategory;
	}

	/**
	 * @return string
	 */
	public function getUnitCode()
	{
		return $this->unitCode;
	}

	/**
	 * @return string|null
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @return \Mpdf\Invoice\AllowanceCharge[]
	 */
	public function getAllowanceCharges()
	{
		return $this->allowanceCharges;
	}

	/**
	 * The quantity times the unit price, rounded to the cent, less the line's allowances and plus its charges
	 *
	 * @return float
	 */
	public function getNetAmount()
	{
		$amount = round($this->quantity * $this->unitPrice, 2);
		foreach ($this->allowanceCharges as $allowanceCharge) {
			$amount += $allowanceCharge->getSignedAmount();
		}

		return round($amount, 2);
	}

}
