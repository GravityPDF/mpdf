<?php

namespace Mpdf\Invoice\Preset;

/**
 * How French-speaking Belgium writes numbers, amounts, rates and dates: as France does, but with a dot between groups
 * of digits, 1.021,11 €
 */
class BelgiumFrenchPreset extends FrancePreset
{

	/**
	 * @var string
	 */
	protected $thousandsSeparator = '.';

}
