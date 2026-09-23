<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Quebec and the rest of French-speaking Canada writes numbers, amounts, rates and dates: as France does, but in
 * dollars and with dates year first, 1 021,11 $ and 2026-09-23
 */
class CanadaQuebecPreset extends FrancePreset
{

	/**
	 * @var string
	 */
	protected $dateFormat = 'Y-m-d';

	/**
	 * @var string[]
	 */
	protected $currencyFormats = ['CAD' => '%s' . self::NBSP . '$'];

}
