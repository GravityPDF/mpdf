<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Czechia writes numbers, amounts, rates and dates: 1 021,11 Kč, 5,5 % and 23.09.2026, with no-break spaces
 */
class CzechiaPreset extends AbstractPreset
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
		return ['CZK' => "%s\xc2\xa0Kč"];
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return "%s\xc2\xa0%%";
	}

}
