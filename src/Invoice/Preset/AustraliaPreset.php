<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Australia writes numbers, amounts, rates and dates: $1,021.11, 5.5% and 23/09/2026
 */
class AustraliaPreset extends AbstractPreset
{

	/**
	 * @var string
	 */
	protected $dateFormat = 'd/m/Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['AUD' => '$%s'];

}
