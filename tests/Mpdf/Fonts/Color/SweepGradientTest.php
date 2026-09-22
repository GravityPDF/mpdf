<?php

namespace Mpdf\Fonts\Color;

/**
 * SweepGradient's wedges, about the centre of a box from -100,-100 to 100,100
 */
class SweepGradientTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const BOX = [-100, -100, 100, 100];

	/**
	 * Stops over the whole circle are wedges no wider than MAX_WEDGE, their offsets rising with the
	 * angle, each reaching past every corner of the box
	 */
	public function testAWholeTurnIsWedgesWhoseOffsetsRiseWithTheAngle()
	{
		$triangles = SweepGradient::triangles(0, 0, 0, 360, [0, 1], self::BOX);

		$this->assertCount(90, $triangles);
		$this->assertSame([0.0, 1 / 90], [$triangles[0][1][2], $triangles[0][2][2]]);
		$this->assertEqualsWithDelta(1, $triangles[89][2][2], 1e-9);

		foreach ($triangles as $triangle) {
			$this->assertSame($triangle[1][2] + ($triangle[2][2] - $triangle[1][2]) / 2, $triangle[0][2], 'the centre halfway');
			// The middle of the far edge lies beyond a corner
			$this->assertGreaterThan(hypot(100, 100), hypot(($triangle[1][0] + $triangle[2][0]) / 2, ($triangle[1][1] + $triangle[2][1]) / 2));
		}
	}

	/**
	 * Before offset 0's angle the offset is held at 0, and past offset 1's at 1
	 */
	public function testBeyondItsTwoAnglesTheOffsetIsHeld()
	{
		$triangles = SweepGradient::triangles(0, 0, 90, 180, [0, 1], self::BOX);

		$this->assertEqualsWithDelta(0, self::offsetAt($triangles, 45), 1e-9);
		$this->assertEqualsWithDelta(0.5, self::offsetAt($triangles, 135), 1e-9);
		$this->assertEqualsWithDelta(1, self::offsetAt($triangles, 270), 1e-9);
	}

	/**
	 * Every stop's angle is a wedge's edge, so a hard edge between two stops falls between two wedges
	 */
	public function testEachStopsAngleIsAWedgesEdge()
	{
		$edges = [];
		foreach (SweepGradient::triangles(0, 0, 0, 360, [0, 0.3333, 1], self::BOX) as $triangle) {
			$edges[] = round(self::angleOf($triangle[1]), 4);
		}

		$this->assertContains(round(0.3333 * 360, 4), $edges);
	}

	/**
	 * Offsets 0 and 1 at one angle are a step from one to the other there
	 */
	public function testOffsetsZeroAndOneAtOneAngleAreAStep()
	{
		$triangles = SweepGradient::triangles(0, 0, 90, 90, [0, 1], self::BOX);

		$this->assertEqualsWithDelta(0, self::offsetAt($triangles, 89), 1e-9);
		$this->assertEqualsWithDelta(1, self::offsetAt($triangles, 91), 1e-9);
	}

	/**
	 * @param array[] $triangles As SweepGradient::triangles() gives them, about 0,0
	 * @param float   $angle     In degrees, inside a wedge rather than on its edge
	 *
	 * @return float|null The offset at the angle, along the far edge of the wedge it lies in
	 */
	private static function offsetAt(array $triangles, $angle)
	{
		foreach ($triangles as $triangle) {
			$from = self::angleOf($triangle[1]);
			$to = self::angleOf($triangle[2]);
			$to = $to < $from ? $to + 360 : $to;
			if ($angle > $from && $angle < $to) {
				return $triangle[1][2] + ($triangle[2][2] - $triangle[1][2]) * ($angle - $from) / ($to - $from);
			}
		}

		return null;
	}

	/**
	 * @param float[] $corner [x, y, offset]
	 *
	 * @return float Its angle about 0,0, from 0 to 360 degrees
	 */
	private static function angleOf(array $corner)
	{
		$angle = rad2deg(atan2($corner[1], $corner[0]));

		return $angle < 0 ? $angle + 360 : $angle;
	}
}
