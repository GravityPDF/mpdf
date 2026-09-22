<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Australia writes numbers, amounts, rates and dates: $1,021.11, 5.5% and 23/09/2026
 */
class AustraliaPreset extends AbstractPreset
{

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
		return ['AUD' => '$%s'];
	}

}
