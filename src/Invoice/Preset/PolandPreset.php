<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Poland writes numbers, amounts, rates and dates: 1021,11 zł but 10 211,11 zł, 5,5% and 23.09.2026, a four-digit
 * number being written whole
 */
class PolandPreset extends AbstractPreset
{

	/**
	 * @return string
	 */
	public function getDecimalPoint()
	{
		return ',';
	}

	/**
	 * @return string
	 */
	public function getThousandsSeparator()
	{
		return "\xc2\xa0";
	}

	/**
	 * @return int
	 */
	public function getMinimumGroupingDigits()
	{
		return 2;
	}

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return 'd.m.Y';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['PLN' => "%s\xc2\xa0zł"];
	}

}
