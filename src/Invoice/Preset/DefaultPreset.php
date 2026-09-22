<?php

namespace Mpdf\Invoice\Preset;

use Mpdf\Strict;

/**
 * The convention a Formatter uses unless given a country's: 1,021.11 EUR, 20% and 2026-09-23. A country's preset
 * extends it with only what the country writes another way.
 */
class DefaultPreset implements PresetInterface
{

	use Strict;

	/**
	 * @return string
	 */
	public function getDecimalPoint()
	{
		return '.';
	}

	/**
	 * @return string
	 */
	public function getThousandsSeparator()
	{
		return ',';
	}

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return 'Y-m-d';
	}

	/**
	 * @return string[]
	 */
	public function getCurrencyFormats()
	{
		return [];
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return '%s%%';
	}

}
