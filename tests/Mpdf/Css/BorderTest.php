<?php

namespace Mpdf\Css;

/**
 * Which sides of a border draw anything
 */
class BorderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider providerDrawsSide
	 *
	 * @param array|null $side A side of a border, as Mpdf::border_details() reads it
	 * @param bool $expected
	 */
	public function testDrawsSide($side, $expected)
	{
		$this->assertSame($expected, Border::drawsSide($side));
	}

	/**
	 * @return array[] Sides of a border, and whether each draws anything
	 */
	public function providerDrawsSide()
	{
		$black = "\x03\x00\x00\x00";

		return [
			'solid, with a width and colour' => [['s' => 1, 'w' => 0.2, 'c' => $black, 'style' => 'solid'], true],
			'no style' => [['s' => 0, 'w' => 0.2, 'c' => $black, 'style' => ''], false],
			'hidden, with no width' => [['s' => 1, 'w' => 0, 'c' => $black, 'style' => 'hidden'], false],
			'transparent' => [['s' => 1, 'w' => 0.2, 'c' => false, 'style' => 'solid'], false],
			'transparent, packed with a table cell' => [['s' => 1, 'w' => 0.2, 'c' => "\0\0\0\0", 'style' => 'solid'], false],
			'no colour' => [['s' => 1, 'w' => 0.2, 'style' => 'solid'], false],
			'no side' => [null, false],
		];
	}
}
