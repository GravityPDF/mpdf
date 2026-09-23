<?php

namespace Mpdf\Invoice\Preset;

use Mpdf\Strict;

/**
 * A preset whose settings are properties: a country's preset sets its date and currency formats, and whatever else it
 * writes differently from the English-speaking defaults of a decimal point, commas between groups of three digits and
 * 5.5%
 */
abstract class AbstractPreset implements PresetInterface
{

	use Strict;

	/**
	 * The no-break space most conventions put between groups of digits or before a symbol
	 */
	const NBSP = "\xc2\xa0";

	/**
	 * @var string
	 */
	protected $decimalPoint = '.';

	/**
	 * @var string
	 */
	protected $thousandsSeparator = ',';

	/**
	 * @var int[]
	 */
	protected $groupingSizes = [3];

	/**
	 * @var int
	 */
	protected $minimumGroupingDigits = 1;

	/**
	 * @var string
	 */
	protected $dateFormat;

	/**
	 * @var string[]
	 */
	protected $currencyFormats = [];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s%%';

	/**
	 * @return string
	 */
	public function getDecimalPoint()
	{
		return $this->decimalPoint;
	}

	/**
	 * @return string
	 */
	public function getThousandsSeparator()
	{
		return $this->thousandsSeparator;
	}

	/**
	 * @return int[]
	 */
	public function getGroupingSizes()
	{
		return $this->groupingSizes;
	}

	/**
	 * @return int
	 */
	public function getMinimumGroupingDigits()
	{
		return $this->minimumGroupingDigits;
	}

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return $this->dateFormat;
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return $this->currencyFormats;
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return $this->percentFormat;
	}

}
