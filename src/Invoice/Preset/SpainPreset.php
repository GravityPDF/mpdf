<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Spain writes numbers, amounts, rates and dates: 1021,11 € but 10.211,11 €, 5,5 % and 23/09/2026
 */
class SpainPreset extends AbstractPreset
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

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
