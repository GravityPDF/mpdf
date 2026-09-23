<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Romania writes numbers, amounts, rates and dates: 1.021,11 lei, 5,5 % and 23.09.2026
 */
class RomaniaPreset extends AbstractPreset
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
	 * @var string[]
	 */
	protected $currencyFormats = ['RON' => '%s' . self::NBSP . 'lei'];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
