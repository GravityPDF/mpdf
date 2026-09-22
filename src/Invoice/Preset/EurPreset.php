<?php

namespace Mpdf\Invoice\Preset;

/**
 * The German convention, shared by much of the euro area: 1.021,11 € and 23.09.2026. Countries that write euros
 * otherwise, such as France, extend DefaultPreset with their own.
 */
class EurPreset extends DefaultPreset
{

	/**
	 * @return string
	 */
	public function getDecimalPoint()
	{
		return ',';
	}

	/**
	 * @return string
	 */
	public function getThousandsSeparator()
	{
		return '.';
	}

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return 'd.m.Y';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['EUR' => "%s\xc2\xa0€"];
	}

}
