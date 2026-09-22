<?php

namespace Mpdf\Invoice\Preset;

/**
 * How the United States writes numbers and dates, and its dollars: $1,021.11 and 09/23/2026
 */
class UnitedStatesPreset extends DefaultPreset
{

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return 'm/d/Y';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['USD' => '$%s'];
	}

}
