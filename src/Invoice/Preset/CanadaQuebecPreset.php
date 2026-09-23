<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Quebec and the rest of French-speaking Canada writes numbers, amounts, rates and dates: 1 021,11 $, 5,5 % and
 * 2026-09-23
 */
class CanadaQuebecPreset extends AbstractPreset
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
	protected $dateFormat = 'Y-m-d';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['CAD' => '%s' . self::NBSP . '$'];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
