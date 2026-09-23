<?php

namespace Mpdf\Invoice\Preset;

/**
 * How India writes numbers, amounts, rates and dates: ₹12,34,567.25, 5.5% and 23/09/2026, grouping lakhs and crores in
 * pairs
 */
class IndiaPreset extends AbstractPreset
{

	/**
	 * @var int[]
	 */
	protected $groupingSizes = [3, 2];

	/**
	 * @var string
	 */
	protected $dateFormat = 'd/m/Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['INR' => '₹%s'];

}
