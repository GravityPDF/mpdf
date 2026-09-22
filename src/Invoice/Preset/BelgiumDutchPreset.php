<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Dutch-speaking Belgium writes numbers, amounts, rates and dates: € 1.021,11, 5,5% and 23/09/2026
 */
class BelgiumDutchPreset extends AbstractPreset
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
		return 'd/m/Y';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['EUR' => "€\xc2\xa0%s"];
	}

}
