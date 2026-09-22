<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Portugal writes numbers, amounts, rates and dates: 1021,11 € but 10 211,11 €, 5,5% and 23/09/2026, a four-digit
 * number being written whole
 */
class PortugalPreset extends AbstractPreset
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
		return 'd/m/Y';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['EUR' => "%s\xc2\xa0€"];
	}

}
