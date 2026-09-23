<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Japan writes numbers, amounts, rates and dates: ¥1,021, 5.5% and 2026/09/23, the yen having no minor unit
 */
class JapanPreset extends AbstractPreset
{

	/**
	 * @var string
	 */
	protected $dateFormat = 'Y/m/d';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['JPY' => '¥%s'];

}
