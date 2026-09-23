<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Czechia writes numbers, amounts, rates and dates: 1 021,11 Kč, 5,5 % and 23.09.2026
 */
class CzechiaPreset extends AbstractPreset
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
	protected $dateFormat = 'd.m.Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['CZK' => '%s' . self::NBSP . 'Kč'];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
