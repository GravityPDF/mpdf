<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class TransformTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var Transform
	 */
	private $transform;

	/**
	 * Reads lengths at a 10mm font size, so that an em is 10mm
	 */
	protected function set_up()
	{
		parent::set_up();
		$this->transform = new Transform(new SizeConverter(96, 11, new Mpdf(['mode' => 'c']), new NullLogger()), 10);
	}

	/**
	 * Each function, a list of them and transform-origin move the corners of a 40mm by 20mm box at (10, 30) where a
	 * browser draws them
	 *
	 * @dataProvider transforms
	 */
	public function testMatrix($transform, $origin, $topLeft, $bottomRight)
	{
		$m = $this->transform->matrix($transform, $origin, 10, 30, 40, 20);

		$this->assertPoint($topLeft, Transform::apply($m, 10, 30));
		$this->assertPoint($bottomRight, Transform::apply($m, 50, 50));
	}

	/**
	 * Transforms with where they take the top left and bottom right corners of the box
	 *
	 * @return array
	 */
	public function transforms()
	{
		return [
			'translate' => ['translate(5mm, -2mm)', '', [15, 28], [55, 48]],
			'translate percentages of the box' => ['translate(50%, 50%)', '', [30, 40], [70, 60]],
			'translateX' => ['translateX(1em)', '', [20, 30], [60, 50]],
			'translateY' => ['translateY(1em)', '', [10, 40], [50, 60]],
			'scale about the centre' => ['scale(2)', '', [-10, 20], [70, 60]],
			'scale percentage' => ['scale(50%, 200%)', '', [20, 20], [40, 60]],
			'scaleX' => ['scaleX(0.5)', '', [20, 30], [40, 50]],
			'scaleY' => ['scaleY(0)', '', [10, 40], [50, 40]],
			'rotate a quarter turn clockwise about the centre' => ['rotate(90deg)', '', [40, 20], [20, 60]],
			'rotate in turns' => ['rotate(0.5turn)', '', [50, 50], [10, 30]],
			'rotate in radians' => ['rotate(' . M_PI . 'rad)', '', [50, 50], [10, 30]],
			'rotate in grads' => ['rotate(-100grad)', '', [20, 60], [40, 20]],
			'skewX' => ['skewX(45deg)', '', [0, 30], [60, 50]],
			'skewY' => ['skewY(45deg)', '', [10, 10], [50, 70]],
			'skew' => ['skew(45deg, 0)', '', [0, 30], [60, 50]],
			'a list applies the last function first' => ['translate(10mm) rotate(90deg)', '', [50, 20], [30, 60]],
			'the list the other way round' => ['rotate(90deg) translate(10mm)', '', [40, 30], [20, 70]],
			'origin at a corner' => ['rotate(90deg)', 'left top', [10, 30], [-10, 70]],
			'origin keywords either way round' => ['rotate(90deg)', 'top left', [10, 30], [-10, 70]],
			'one keyword centres the other axis' => ['scale(2)', 'bottom', [-10, 10], [70, 50]],
			'origin lengths and percentages' => ['scale(2)', '0 100%', [10, 10], [90, 50]],
			'origin in em' => ['scale(2)', '1em 0', [0, 30], [80, 70]],
			'a z origin is left out' => ['scale(2)', 'left top 5mm', [10, 30], [90, 70]],
		];
	}

	/**
	 * None, and a list CSS rejects whole, draw the box as it is
	 *
	 * @dataProvider rejected
	 */
	public function testNoMatrix($transform)
	{
		$this->assertNull($this->transform->matrix($transform, '', 10, 30, 40, 20));
	}

	/**
	 * Transform values that leave the box untransformed
	 *
	 * @return array
	 */
	public function rejected()
	{
		return [
			'none' => ['none'],
			'empty' => [''],
			'an unknown function' => ['wobble(2)'],
			'an unknown function in a list' => ['rotate(10deg) wobble(2)'],
			'not a function' => ['rotate'],
			'text around the functions' => ['rotate(10deg) and more'],
			'an angle with no unit' => ['rotate(10)'],
			'a scale that is a length' => ['scale(2mm)'],
			'too many arguments' => ['translate(1mm, 2mm, 3mm)'],
		];
	}

	/**
	 * A unitless zero is an angle
	 */
	public function testUnitlessZeroAngle()
	{
		$this->assertPoint([10, 30], Transform::apply($this->transform->matrix('rotate(0)', '', 10, 30, 40, 20), 10, 30));
	}

	/**
	 * Assert two points are the same, to a thousandth of a millimetre
	 *
	 * @param float[] $expected
	 * @param float[] $actual
	 */
	private function assertPoint(array $expected, array $actual)
	{
		$this->assertEqualsWithDelta($expected[0], $actual[0], 0.001, 'x');
		$this->assertEqualsWithDelta($expected[1], $actual[1], 0.001, 'y');
	}
}
