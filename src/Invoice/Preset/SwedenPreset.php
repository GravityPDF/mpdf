<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Sweden writes numbers, amounts, rates and dates: 1 021,11 kr, 5,5 % and 2026-09-23, with no-break spaces
 */
class SwedenPreset extends AbstractPreset
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
		return 'Y-m-d';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['SEK' => "%s\xc2\xa0kr"];
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return "%s\xc2\xa0%%";
	}

}
