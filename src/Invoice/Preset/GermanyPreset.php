<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Germany writes numbers, amounts, rates and dates: 1.021,11 €, 5,5 % and 23.09.2026
 */
class GermanyPreset extends AbstractPreset
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
	protected $dateFormat = 'd.m.Y';

	/**
	 * @var string
	 */
	protected $longDateFormat = 'j. {month} Y';

	/**
	 * @var string[]
	 */
	protected $monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['EUR' => '%s' . self::NBSP . '€'];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
