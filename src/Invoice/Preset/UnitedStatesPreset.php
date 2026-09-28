<?php

namespace Mpdf\Invoice\Preset;

/**
 * How the United States writes numbers, amounts, rates and dates: $1,021.11, 5.5% and 09/23/2026
 */
class UnitedStatesPreset extends AbstractPreset
{

	/**
	 * @var string
	 */
	protected $dateFormat = 'm/d/Y';

	/**
	 * @var string
	 */
	protected $longDateFormat = '{month} j, Y';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['USD' => '$%s'];

}
