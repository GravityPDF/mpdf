<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Sweden writes numbers, amounts, rates and dates: 1 021,11 kr, 5,5 % and 2026-09-23
 */
class SwedenPreset extends AbstractPreset
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
	protected $monthNames = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['SEK' => '%s' . self::NBSP . 'kr'];

	/**
	 * @var string
	 */
	protected $percentFormat = '%s' . self::NBSP . '%%';

}
