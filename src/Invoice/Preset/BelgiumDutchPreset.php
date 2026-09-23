<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Dutch-speaking Belgium writes numbers, amounts, rates and dates: € 1.021,11, 5,5% and 23/09/2026
 */
class BelgiumDutchPreset extends AbstractPreset
{

	/**
	 * @var string
	 */
	protected $decimalPoint = ',';

	/**
	 * @var string
	 */
	protected $thousandsSeparator = '.';

	/**
	 * @var string
	 */
	protected $dateFormat = 'd/m/Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['EUR' => '€' . self::NBSP . '%s'];

}
