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
	 * @var string
	 */
	protected $longDateFormat = 'Y年{month}j日';

	/**
	 * @var string[]
	 */
	protected $monthNames = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['JPY' => '¥%s'];

}
