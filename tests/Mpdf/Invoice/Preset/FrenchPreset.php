<?php

namespace Mpdf\Invoice\Preset;

/**
 * The French convention, a preset of the kind the preset docblocks suggest writing: 1 021,11 €, 5,5 % and 23/09/2026,
 * with no-break spaces
 */
class FrenchPreset extends DefaultPreset
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
		return "\xc2\xa0";
	}

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return 'd/m/Y';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return ['EUR' => "%s\xc2\xa0€"];
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return "%s\xc2\xa0%%";
	}

}
