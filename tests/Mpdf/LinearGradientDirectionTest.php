<?php

namespace Mpdf;

/**
 * The axis a linear-gradient() background is drawn along. Gradient() registers it as [x0, y0, x1, y1] across the box,
 * with y running up, so "to bottom" starts at y = 1 and ends at y = 0. Standard CSS mode reads the direction as CSS
 * Images gives it, and legacy CSS mode as mPDF v7 did.
 */
class LinearGradientDirectionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The axis drawn for a block with the given background
	 *
	 * @param string $background
	 * @param string $cssMode
	 * @param string $property The property the gradient is given in
	 *
	 * @return float[]
	 */
	private function axis($background, $cssMode = CssMode::STANDARD, $property = 'background')
	{
		$mpdf = new Mpdf(['cssMode' => $cssMode]);
		$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; ' . $property . ': ' . $background . '"></div>');
		$mpdf->Output('', 'S');

		$this->assertCount(1, $mpdf->gradients);

		$gradient = reset($mpdf->gradients);

		return array_map('floatval', array_slice($gradient['coords'], 0, 4));
	}

	/**
	 * A side keyword or an angle runs the axis the way CSS Images gives
	 *
	 * @dataProvider axisProvider
	 *
	 * @param string $background
	 * @param float[] $expected
	 */
	public function testAxis($background, $expected)
	{
		$this->assertEqualsWithDelta($expected, $this->axis($background), 0.0001);
	}

	/**
	 * Backgrounds with a side or corner keyword or a right angle, with the axis each gives
	 *
	 * @return array
	 */
	public function axisProvider()
	{
		$down = [0.5, 1, 0.5, 0];
		$up = [0.5, 0, 0.5, 1];
		$right = [0, 0.5, 1, 0.5];
		$left = [1, 0.5, 0, 0.5];

		return [
			'no direction' => ['linear-gradient(red, blue)', $down],
			'to bottom' => ['linear-gradient(to bottom, red, blue)', $down],
			'to top' => ['linear-gradient(to top, red, blue)', $up],
			'to right' => ['linear-gradient(to right, red, blue)', $right],
			'to left' => ['linear-gradient(to left, red, blue)', $left],
			'repeating, to bottom' => ['repeating-linear-gradient(to bottom, red, blue 5mm)', $down],

			// Corner to corner across the box, so the other two corners are halfway along
			'to bottom right' => ['linear-gradient(to bottom right, red, blue)', [0, 1, 1, 0]],
			'to top left' => ['linear-gradient(to top left, red, blue)', [1, 0, 0, 1]],
			'to right top' => ['linear-gradient(to right top, red, blue)', [0, 0, 1, 1]],
			'to bottom left' => ['linear-gradient(to bottom left, red, blue)', [1, 1, 0, 0]],

			'0deg points up' => ['linear-gradient(0deg, red, blue)', [0, 0, 0, 1]],
			'unitless 0 points up' => ['linear-gradient(0, red, blue)', [0, 0, 0, 1]],
			'90deg points right' => ['linear-gradient(90deg, red, blue)', [0, 0, 1, 0]],
			'180deg points down' => ['linear-gradient(180deg, red, blue)', [0, 1, 0, 0]],
			'270deg points left' => ['linear-gradient(270deg, red, blue)', [1, 0, 0, 0]],
			'-90deg points left' => ['linear-gradient(-90deg, red, blue)', [1, 0, 0, 0]],
			'450deg points right' => ['linear-gradient(450deg, red, blue)', [0, 0, 1, 0]],
			'0.5turn points down' => ['linear-gradient(0.5turn, red, blue)', [0, 1, 0, 0]],
			'100grad points right' => ['linear-gradient(100grad, red, blue)', [0, 0, 1, 0]],
			'3.14159rad points down' => ['linear-gradient(3.14159265rad, red, blue)', [0, 1, 0, 0]],

			'-moz- keyword names the start' => ['-moz-linear-gradient(left, red, blue)', $right],
			'-moz- top' => ['-moz-linear-gradient(top, red, blue)', $down],
			'-webkit- keyword names the start' => ['-webkit-linear-gradient(left, red, blue)', $right],
			'-moz- 90deg points up' => ['-moz-linear-gradient(90deg, red, blue)', [0, 0, 0, 1]],
			'-webkit- 0deg points right' => ['-webkit-linear-gradient(0deg, red, blue)', [0, 0, 1, 0]],
		];
	}

	/**
	 * In legacy CSS mode a side keyword or an angle runs the axis as mPDF v7 drew it, prefixed or not
	 *
	 * @dataProvider legacyAxisProvider
	 *
	 * @param string $background
	 * @param float[] $expected
	 */
	public function testLegacyAxis($background, $expected)
	{
		$this->assertEqualsWithDelta($expected, $this->axis($background, CssMode::LEGACY), 0.0001);
	}

	/**
	 * Backgrounds with a side or corner keyword or a right angle, with the axis mPDF v7 gave each
	 *
	 * @return array
	 */
	public function legacyAxisProvider()
	{
		$down = [0.5, 1, 0.5, 0];
		$up = [0.5, 0, 0.5, 1];
		$right = [0, 0.5, 1, 0.5];
		$left = [1, 0.5, 0, 0.5];

		return [
			'no direction' => ['linear-gradient(red, blue)', $down],
			'to bottom runs up' => ['linear-gradient(to bottom, red, blue)', $up],
			'to top runs down' => ['linear-gradient(to top, red, blue)', $down],
			'to right' => ['linear-gradient(to right, red, blue)', $right],
			'to left' => ['linear-gradient(to left, red, blue)', $left],
			'repeating, to bottom runs up' => ['repeating-linear-gradient(to bottom, red, blue 5mm)', $up],
			'left runs to the left' => ['linear-gradient(left, red, blue)', $left],
			'top runs down' => ['linear-gradient(top, red, blue)', $down],

			// A corner sets only the start, and the angle is squared to the box
			'to bottom right' => ['linear-gradient(to bottom right, red, blue)', [0, 0, 1, 0.5]],
			'to top left' => ['linear-gradient(to top left, red, blue)', [1, 0.5, 0, 0]],

			'0deg points right' => ['linear-gradient(0deg, red, blue)', [0, 0, 1, 0]],
			'unitless 0 points right' => ['linear-gradient(0, red, blue)', [0, 0, 1, 0]],
			'90deg points up' => ['linear-gradient(90deg, red, blue)', [0, 0, 0, 1]],
			'180deg points left' => ['linear-gradient(180deg, red, blue)', [1, 0, 0, 0]],
			'270deg points down' => ['linear-gradient(270deg, red, blue)', [0, 1, 0, 0]],
			'-90deg points down' => ['linear-gradient(-90deg, red, blue)', [0, 1, 0, 0]],
			'450deg points up' => ['linear-gradient(450deg, red, blue)', [0, 0, 0, 1]],
			'100grad points up' => ['linear-gradient(100grad, red, blue)', [0, 0, 0, 1]],
			'turn is not read' => ['linear-gradient(0.5turn, red, blue)', [0, 0, 1, 0]],

			'-moz- left runs to the left' => ['-moz-linear-gradient(left, red, blue)', $left],
			'-webkit- left runs to the left' => ['-webkit-linear-gradient(left, red, blue)', $left],
			'-o- right runs to the right' => ['-o-linear-gradient(right, red, blue)', $right],
			'-moz- 90deg points up' => ['-moz-linear-gradient(90deg, red, blue)', [0, 0, 0, 1]],
			'-webkit- 0deg points right' => ['-webkit-linear-gradient(0deg, red, blue)', [0, 0, 1, 0]],
		];
	}

	/**
	 * In legacy CSS mode "left" runs the gradient to the left, prefixed or not, in the background shorthand and in
	 * background-image
	 *
	 * @dataProvider prefixProvider
	 *
	 * @param string $property
	 * @param string $prefix
	 */
	public function testLegacyLeftRunsToTheLeftWhateverThePrefix($property, $prefix)
	{
		$this->assertEqualsWithDelta(
			[1, 0.5, 0, 0.5],
			$this->axis($prefix . 'linear-gradient(left, red, blue)', CssMode::LEGACY, $property),
			0.0001
		);
	}

	/**
	 * Each property a gradient is given in, with no prefix and with each prefix the gradient parser tells apart
	 *
	 * @return array
	 */
	public function prefixProvider()
	{
		$cases = [];
		foreach (['background', 'background-image'] as $property) {
			foreach (['', '-moz-', '-webkit-', '-o-'] as $prefix) {
				$cases[$property . ', ' . ($prefix ?: 'no prefix')] = [$property, $prefix];
			}
		}

		return $cases;
	}

	/**
	 * In legacy CSS mode a fractional angle is cut to whole degrees, as mPDF v7 cut it, without the deprecation PHP 8.1
	 * raises for an implicit conversion from float to int or any other error
	 */
	public function testLegacyFractionalAngleIsCutToWholeDegreesWithoutDeprecation()
	{
		$errors = [];
		set_error_handler(function ($errno, $errstr) use (&$errors) {
			$errors[] = $errstr;

			return true;
		});

		try {
			$fractional = $this->axis('linear-gradient(22.9deg, red, blue)', CssMode::LEGACY);
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $errors);
		$this->assertEqualsWithDelta($this->axis('linear-gradient(22deg, red, blue)', CssMode::LEGACY), $fractional, 0.0001);
	}

	/**
	 * An angle between the sides runs the axis into the right quarter
	 *
	 * @dataProvider diagonalProvider
	 *
	 * @param string $background
	 * @param int $dx The sign the axis runs across the box
	 * @param int $dy The sign the axis runs up the box
	 * @param string $cssMode
	 */
	public function testDiagonalAxis($background, $dx, $dy, $cssMode = CssMode::STANDARD)
	{
		list($x0, $y0, $x1, $y1) = $this->axis($background, $cssMode);

		$this->assertSame($dx, $this->sign($x1 - $x0), 'across');
		$this->assertSame($dy, $this->sign($y1 - $y0), 'up');
	}

	/**
	 * -1, 0 or 1 for a negative, zero or positive difference
	 *
	 * @param float $difference
	 *
	 * @return int
	 */
	private function sign($difference)
	{
		if (abs($difference) < 0.0001) {
			return 0;
		}

		return $difference > 0 ? 1 : -1;
	}

	/**
	 * Backgrounds whose axis runs between the sides of the box, with the signs it runs in
	 *
	 * @return array
	 */
	public function diagonalProvider()
	{
		return [
			'45deg' => ['linear-gradient(45deg, red, blue)', 1, 1],
			'135deg' => ['linear-gradient(135deg, red, blue)', 1, -1],
			'22.5deg' => ['linear-gradient(22.5deg, red, blue)', 1, 1],
			'-moz- left top' => ['-moz-linear-gradient(left top, red, blue)', 1, -1],

			'legacy 45deg' => ['linear-gradient(45deg, red, blue)', 1, 1, CssMode::LEGACY],
			'legacy 135deg' => ['linear-gradient(135deg, red, blue)', -1, 1, CssMode::LEGACY],
			'legacy 22.5deg' => ['linear-gradient(22.5deg, red, blue)', 1, 1, CssMode::LEGACY],
			'legacy -moz- left top' => ['-moz-linear-gradient(left top, red, blue)', -1, -1, CssMode::LEGACY],
		];
	}

}
