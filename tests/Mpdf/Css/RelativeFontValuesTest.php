<?php

namespace Mpdf\Css;

/**
 * font-weight computed from the parent's weight, the weights drawn bold, and font-size: larger and smaller
 */
class RelativeFontValuesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider providerWeight
	 *
	 * @param string $value A font-weight value
	 * @param int $parentWeight
	 * @param int|float|null $expected
	 */
	public function testWeight($value, $parentWeight, $expected)
	{
		$this->assertSame($expected, RelativeFontValues::weight($value, $parentWeight));
	}

	/**
	 * @return array[] font-weight values, the parent's weight, and the weight each computes to, or null for a value
	 * that is not a weight
	 */
	public function providerWeight()
	{
		return [
			'normal' => ['normal', 900, 400],
			'bold' => ['bold', 100, 700],
			'keyword in capitals' => ['BOLD', 400, 700],
			'number' => ['600', 400, 600],
			'lowest number' => ['1', 400, 1],
			'highest number' => ['1000', 400, 1000],
			'number between the hundreds' => ['550', 400, 550],
			'fraction' => ['550.5', 400, 550.5],
			'zero' => ['0', 400, null],
			'above 1000' => ['1001', 400, null],
			'negative' => ['-100', 400, null],
			'number with a unit' => ['600px', 400, null],
			'unknown keyword' => ['heavy', 400, null],
			'empty' => ['', 400, null],

			'bolder from below 100' => ['bolder', 50, 400],
			'lighter from below 100' => ['lighter', 50, 50],
			'bolder from 100' => ['bolder', 100, 400],
			'lighter from 100' => ['lighter', 100, 100],
			'bolder from 349' => ['bolder', 349, 400],
			'lighter from 349' => ['lighter', 349, 100],
			'bolder from 350' => ['bolder', 350, 700],
			'lighter from 350' => ['lighter', 350, 100],
			'bolder from 400' => ['bolder', 400, 700],
			'lighter from 400' => ['lighter', 400, 100],
			'bolder from 549' => ['bolder', 549, 700],
			'lighter from 549' => ['lighter', 549, 100],
			'bolder from 550' => ['bolder', 550, 900],
			'lighter from 550' => ['lighter', 550, 400],
			'bolder from 700' => ['bolder', 700, 900],
			'lighter from 700' => ['lighter', 700, 400],
			'bolder from 749' => ['bolder', 749, 900],
			'lighter from 749' => ['lighter', 749, 400],
			'bolder from 750' => ['bolder', 750, 900],
			'lighter from 750' => ['lighter', 750, 700],
			'bolder from 899' => ['bolder', 899, 900],
			'lighter from 899' => ['lighter', 899, 700],
			'bolder from 900' => ['bolder', 900, 900],
			'lighter from 900' => ['lighter', 900, 700],
			'bolder from 1000' => ['bolder', 1000, 1000],
			'lighter from 1000' => ['lighter', 1000, 700],
		];
	}

	/**
	 * With a regular face at 400 and a bold one at 700, a weight above 500 is drawn bold
	 */
	public function testIsBold()
	{
		$bold = [];
		foreach ([1, 100, 300, 400, 500, 500.5, 501, 550, 600, 700, 800, 900, 1000] as $weight) {
			$bold[(string) $weight] = RelativeFontValues::isBold($weight);
		}

		$this->assertSame([
			'1' => false,
			'100' => false,
			'300' => false,
			'400' => false,
			'500' => false,
			'500.5' => true,
			'501' => true,
			'550' => true,
			'600' => true,
			'700' => true,
			'800' => true,
			'900' => true,
			'1000' => true,
		], $bold);
	}

	/**
	 * larger and smaller multiply and divide the parent's size by 1.2, in any case; other sizes have no ratio
	 */
	public function testSizeRatio()
	{
		$this->assertSame(1.2, RelativeFontValues::sizeRatio('larger'));
		$this->assertSame(1.2, RelativeFontValues::sizeRatio(' LARGER '));
		$this->assertEqualsWithDelta(1 / 1.2, RelativeFontValues::sizeRatio('smaller'), 1e-12);
		$this->assertNull(RelativeFontValues::sizeRatio('large'));
		$this->assertNull(RelativeFontValues::sizeRatio('120%'));
		$this->assertNull(RelativeFontValues::sizeRatio('1.2em'));
	}

	/**
	 * A weight that agrees with the B style is kept; one that does not becomes bold's 700 or normal's 400
	 */
	public function testWeightForStyle()
	{
		$this->assertSame(600, RelativeFontValues::weightForStyle(600, true));
		$this->assertSame(300, RelativeFontValues::weightForStyle(300, false));
		$this->assertSame(700, RelativeFontValues::weightForStyle(300, true));
		$this->assertSame(400, RelativeFontValues::weightForStyle(900, false));
	}

	/**
	 * A table with no font-weight of its own hands its cells the normal weight, and one with a weight hands that on
	 */
	public function testTableWeight()
	{
		$this->assertSame(400, RelativeFontValues::tableWeight([]));
		$this->assertSame(800.0, RelativeFontValues::tableWeight(['FONT-WEIGHT' => '800']));
	}
}
