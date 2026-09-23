<?php

namespace Mpdf\Invoice\Preset;

/**
 * How Dutch-speaking Belgium writes numbers, amounts, rates and dates: as the Netherlands does, but with slashes in its
 * dates, 23/09/2026
 */
class BelgiumDutchPreset extends NetherlandsPreset
{

	/**
	 * @var string
	 */
	protected $dateFormat = 'd/m/Y';

}
