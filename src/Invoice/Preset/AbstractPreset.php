<?php

namespace Mpdf\Invoice\Preset;

use Mpdf\Strict;

/**
 * What most countries share: a decimal point, commas between groups of three digits, and 5.5%. A country's preset
 * extends it with its dates and currency, and overrides whatever else it writes another way.
 */
abstract class AbstractPreset implements PresetInterface
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
	 * @return int[]
	 */
	public function getGroupingSizes()
	{
		return [3];
	}

	/**
	 * @return int
	 */
	public function getMinimumGroupingDigits()
	{
		return 1;
	}

	/**
	 * @return string
	 */
	public function getPercentFormat()
	{
		return '%s%%';
	}

}
