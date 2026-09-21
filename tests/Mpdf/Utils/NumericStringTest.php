<?php

namespace Mpdf\Utils;

/**
 * Numbers as PDF content writes them
 */
class NumericStringTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A number is rounded to the places asked for, and written with no more than it needs
	 *
	 * @dataProvider decimals
	 *
	 * @param float  $value
	 * @param int    $places
	 * @param string $expected
	 */
	public function testADecimalIsWrittenWithNoMorePlacesThanItNeeds($value, $places, $expected)
	{
		$this->assertSame($expected, NumericString::decimal($value, $places));
	}

	/**
	 * @return array[] Each value, the places it is rounded to, and how it is written
	 */
	public function decimals()
	{
		return [
			'a whole number' => [2, 5, '2'],
			'a large whole number' => [PHP_INT_MAX, 5, (string) PHP_INT_MAX],
			'a whole float' => [2.0, 5, '2'],
			'trailing zeros' => [1.5, 5, '1.5'],
			'rounded' => [0.123456, 5, '0.12346'],
			'rounded to fewer places' => [0.123456, 3, '0.123'],
			'rounded up to a whole number' => [0.9999999, 5, '1'],
			'negative' => [-12.25, 5, '-12.25'],
			'a negative rounded to 0' => [-0.000001, 5, '0'],
			'a multiple of ten' => [10, 5, '10'],
			'no places' => [2.6, 0, '3'],
			'a multiple of ten to no places' => [30, 0, '30'],
		];
	}
}
