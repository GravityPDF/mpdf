<?php

namespace Mpdf\Invoice\Preset;

use Mpdf\Strict;

/**
 * A preset whose settings are properties: a country's preset sets its date and currency formats, and whatever else it
 * writes differently from the English-speaking defaults of a decimal point, commas between groups of three digits,
 * 5.5%, English month names and 1 January 2020 written out
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
	 * @var string
	 */
	protected $longDateFormat = 'j {month} Y';

	/**
	 * @var string[]
	 */
	protected $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

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
	 * @return string
	 */
	public function getLongDateFormat()
	{
		return $this->longDateFormat;
	}

	/**
	 * @return string[]
	 */
	public function getMonthNames()
	{
		return $this->monthNames;
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
