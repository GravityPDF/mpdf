<?php

namespace Mpdf\Invoice\Preset;

/**
 * How China writes numbers, amounts, rates and dates: ¥1,021.11, 5.5% and 2026-09-23
 */
class ChinaPreset extends AbstractPreset
{

	/**
	 * @var string
	 */
	protected $dateFormat = 'Y-m-d';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['CNY' => '¥%s'];

}
