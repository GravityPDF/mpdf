<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Romania writes numbers, amounts, rates and dates: 1.021,11 lei, 5,5 % and 23.09.2026
 */
class RomaniaPreset extends AbstractPreset
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
		return '.';
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
		return ['RON' => "%s\xc2\xa0lei"];
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return "%s\xc2\xa0%%";
	}

}
