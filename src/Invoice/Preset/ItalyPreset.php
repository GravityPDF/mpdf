<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Italy writes numbers, amounts, rates and dates: 1.021,11 €, 5,5% and 23/09/2026
 */
class ItalyPreset extends AbstractPreset
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
	protected $currencyFormats = ['EUR' => '%s' . self::NBSP . '€'];

}
