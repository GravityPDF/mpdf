<?php

namespace Mpdf;

/**
 * A unitless 0 beside another word of a gradient's first argument, as in "10px 0", is a coordinate of the position,
 * not the angle 0.
 */
class GradientPositionZeroTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * 10px across the 60mm box, as a fraction of its width
	 */
	const TEN_PX = 0.0440972;

	/**
	 * The coordinates registered for the shading function of a 60 × 30 mm block with the given background
	 *
	 * @param string $background
	 * @param string $cssMode
	 *
	 * @return float[] [x0, y0, x1, y1] for a linear gradient, [fx, fy, cx, cy] for a radial one, with y running up
	 */
	private function coords($background, $cssMode)
	{
		$mpdf = new Mpdf(['cssMode' => $cssMode]);
		$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; background: ' . $background . '"></div>');
		$mpdf->Output('', 'S');

		$this->assertCount(1, $mpdf->gradients);

		$gradient = reset($mpdf->gradients);

		return array_map('floatval', array_slice($gradient['coords'], 0, 4));
	}

	/**
	 * A radial gradient's position whose y is a unitless 0 centres the gradient on the top edge, in both CSS modes
	 *
	 * @dataProvider radialProvider
	 *
	 * @param string $background
	 * @param float[] $centre The centre as [x, y] across the box, y running up
	 * @param string $cssMode
	 */
	public function testRadialPositionWithZeroIsAPoint($background, array $centre, $cssMode)
	{
		$coords = $this->coords($background, $cssMode);

		$this->assertEqualsWithDelta([$centre[0], $centre[1], $centre[0], $centre[1]], $coords, 0.0001);
	}

	/**
	 * Radial gradients positioned with a unitless 0, written with "at" and in the prefixed form
	 *
	 * @return array
	 */
	public function radialProvider()
	{
		$cases = [];

		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
			$cases += [
				$cssMode . ', at 10px 0' => ['radial-gradient(at 10px 0, red, blue)', [self::TEN_PX, 1], $cssMode],
				$cssMode . ', -moz- 10px 0' => ['-moz-radial-gradient(10px 0, red, blue)', [self::TEN_PX, 1], $cssMode],
				$cssMode . ', at left 0' => ['radial-gradient(at left 0, red, blue)', [0, 1], $cssMode],
				$cssMode . ', at 0 100%' => ['radial-gradient(at 0 100%, red, blue)', [0, 0], $cssMode],
				// A circle is drawn in a square as wide as the box, whose top edge sits at half its height
				$cssMode . ', circle at 10px 0' => ['radial-gradient(circle at 10px 0, red, blue)', [self::TEN_PX, 0.5], $cssMode],
				$cssMode . ', bare 0 stays centred' => ['radial-gradient(0, red, blue)', [0.5, 0.5], $cssMode],
			];
		}

		return $cases;
	}

	/**
	 * A prefixed linear gradient that starts from a point whose y is a unitless 0 runs from that point on the top edge
	 * to the point opposite it through the centre, and is not turned to the angle 0
	 *
	 * @dataProvider cssModeProvider
	 *
	 * @param string $cssMode
	 */
	public function testLinearPointWithZeroIsNotAnAngle($cssMode)
	{
		$coords = $this->coords('-moz-linear-gradient(10px 0, red, blue)', $cssMode);

		// Off an axis, the axis is drawn in a square as wide as the box, whose top edge sits at half its height
		$this->assertEqualsWithDelta([self::TEN_PX, 0.5, 1 - self::TEN_PX, 0], $coords, 0.0001);
	}

	/**
	 * Both CSS modes
	 *
	 * @return array
	 */
	public function cssModeProvider()
	{
		return [
			'standard' => [CssMode::STANDARD],
			'legacy' => [CssMode::LEGACY],
		];
	}

}
