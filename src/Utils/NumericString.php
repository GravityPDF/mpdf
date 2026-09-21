<?php

namespace Mpdf\Utils;

class NumericString
{

	public static function containsPercentChar($string)
	{
		return strstr($string, '%');
	}

	public static function removePercentChar($string)
	{
		return str_replace('%', '', $string);
	}

	/**
	 * A number as PDF content writes it: to at most $places decimal places and no more than it needs, so
	 * 1.50000 is 1.5 and 2.00000 is 2, and never as -0
	 *
	 * @param float $value
	 * @param int   $places
	 *
	 * @return string
	 */
	public static function decimal($value, $places)
	{
		// A whole number is written as it is, with no need to round it
		if (is_int($value)) {
			return (string) $value;
		}

		$number = sprintf('%.' . $places . 'F', $value);
		if ($places > 0) {
			$number = rtrim(rtrim($number, '0'), '.');
		}

		return $number === '-0' ? '0' : $number;
	}

}
