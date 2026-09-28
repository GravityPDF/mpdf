<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Poland writes numbers, amounts, rates and dates: 1021,11 zł but 10 211,11 zł, 5,5% and 23.09.2026
 */
class PolandPreset extends AbstractPreset
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
	protected $dateFormat = 'd.m.Y';

	/**
	 * @var string[]
	 */
	protected $monthNames = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['PLN' => '%s' . self::NBSP . 'zł'];

}
