<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Portugal writes numbers, amounts, rates and dates: 1021,11 € but 10 211,11 €, 5,5% and 23/09/2026
 */
class PortugalPreset extends AbstractPreset
{

	/**
	 * @var string
	 */
	protected $decimalPoint = ',';

	/**
	 * @var string
	 */
	protected $thousandsSeparator = self::NBSP;

	/**
	 * @var int
	 */
	protected $minimumGroupingDigits = 2;

	/**
	 * @var string
	 */
	protected $dateFormat = 'd/m/Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['EUR' => '%s' . self::NBSP . '€'];

}
