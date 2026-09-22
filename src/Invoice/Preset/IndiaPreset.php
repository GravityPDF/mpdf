<?php

namespace Mpdf\Invoice\Preset;

/**
 * How India writes numbers, amounts, rates and dates: ₹12,34,567.25, 5.5% and 23/09/2026, grouping lakhs and crores in
 * pairs
 */
class IndiaPreset extends AbstractPreset
{

	/**
	 * @return int[]
	 */
	public function getGroupingSizes()
	{
		return [3, 2];
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
		return ['INR' => '₹%s'];
	}

}
