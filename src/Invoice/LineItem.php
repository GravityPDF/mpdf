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
	 * The UNTDID 5305 VAT category codes EN 16931 accepts
	 *
	 * @var string[]
	 */
	private static $vatCategories = ['S', 'Z', 'E', 'AE', 'K', 'G', 'O', 'L', 'M'];

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
	 * @param string $name
	 * @param float $quantity
	 * @param float $unitPrice The net price of one unit, before VAT
	 * @param float $vatRate The VAT rate in percent, e.g. 20; 0 for categories that charge none
	 * @param string $vatCategory S (standard), Z (zero rated), E (exempt), AE (reverse charge), K (intra-community),
	 *                            G (export), O (not subject to VAT), L or M (Canary Islands, Ceuta and Melilla)
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function __construct($name, $quantity, $unitPrice, $vatRate, $vatCategory = 'S')
	{
		if (!in_array($vatCategory, self::$vatCategories, true)) {
			throw new MpdfException(sprintf('VAT category "%s" is not one of %s', $vatCategory, implode(', ', self::$vatCategories)));
		}

		$this->name = $name;
		$this->quantity = (float) $quantity;
		$this->unitPrice = (float) $unitPrice;
		$this->vatRate = (float) $vatRate;
		$this->vatCategory = $vatCategory;
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
	 * The quantity times the unit price, rounded to the cent
	 *
	 * @return float
	 */
	public function getNetAmount()
	{
		return round($this->quantity * $this->unitPrice, 2);
	}

}
