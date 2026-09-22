<?php

namespace Mpdf\Invoice\Preset;

/**
 * The United States convention: $1,021.11 and 09/23/2026
 */
class UsdPreset extends DefaultPreset
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
