<?php

namespace Mpdf\Invoice\Preset;

/**
 * How France writes numbers, amounts, rates and dates: 1 021,11 €, 5,5 % and 23/09/2026
 */
class FrancePreset extends AbstractPreset
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
	 * @var string
	 */
	protected $dateFormat = 'd/m/Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['EUR' => '%s' . self::NBSP . '€'];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
