<?php

namespace Mpdf\Image\Svg;

/**
 * SVG path data and basic shapes, as the moves, lines and cubics a PDF path draws
 */
class PathTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Lines absolute and relative, horizontal and vertical, and the pairs after a move drawn as lines.
	 * A close returns the current point to where the subpath started, which a relative move then
	 * counts from.
	 */
	public function testLinesAndMovesAreReadAbsoluteAndRelative()
	{
		$this->assertSame(
			[['M', 10.0, 20.0], ['L', 30.0, 40.0], ['L', 40.0, 40.0], ['L', 40.0, 35.0], ['L', 0.0, 35.0], ['L', 0.0, 0.0], ['Z'], ['M', 15.0, 25.0], ['L', 16.0, 26.0], ['L', 17.0, 28.0]],
			Path::parse('M10 20 L30 40 h10 v-5 H0 V0 z m5 5 1 1 l1,2')->segments
		);
	}

	/**
	 * Numbers need no space between them where a sign or a second point starts the next, and a path is
	 * drawn up to its first error: here a pair left short
	 */
	public function testCompactNumbersAreReadAndAPathStopsAtItsFirstError()
	{
		$this->assertSame([['M', 0.5, 0.5], ['L', -1.0, -10.0]], Path::parse('M.5.5-1-1e1 2')->segments);
		$this->assertSame([['M', 0.0, 0.0]], Path::parse('M0 0 L10 x')->segments);
		$this->assertSame([], Path::parse('10 10 L20 20')->segments, 'numbers before any command');
	}

	/**
	 * Commas may lie between any two tokens, one at a time, and whitespace anywhere: a second comma, or a
	 * character that is neither a command nor part of a number, is an error
	 */
	public function testCommasAndWhitespaceSeparateTokensAndAnythingElseIsAnError()
	{
		$this->assertSame([['M', 1.0, 2.0], ['L', 3.0, 4.0]], Path::parse("\n\t M 1 , 2 L,3,4 ")->segments);
		$this->assertSame([['M', 1.0, 2.0]], Path::parse('M1,2 L3,,4')->segments, 'two commas');
		$this->assertSame([['M', 1.0, 2.0]], Path::parse('M1 2 L3 # 4')->segments, 'a stray character');
		$this->assertSame([['M', 1.0, 2.0]], Path::parse('M1 2 L3-')->segments, 'a sign with no number');
	}

	/**
	 * An arc's flags are a character each, so may run into each other and into the number after them,
	 * which may start with its decimal point; a flag that is not 0 or 1 is an error
	 */
	public function testArcFlagsAreReadAsACharacterOfANumber()
	{
		$this->assertEqualsWithDelta([0, -50, 100, 0], Path::parse('M0 0a50 50 0 01.1e3 0')->bounds(), 1e-6);
		$this->assertEqualsWithDelta([0, -50, 100, 0], Path::parse('M0 0 A50 50 0 0,1,100,0')->bounds(), 1e-6);
		$this->assertSame([['M', 0.0, 0.0]], Path::parse('M0 0 A50 50 0 2 1 100 0')->segments);
		$this->assertSame([['M', 0.0, 0.0]], Path::parse('M0 0 A50 50 0 01e2 0')->segments, 'a flag followed by an exponent alone');
	}

	/**
	 * A quadratic is raised to the cubic of the same curve, its control point two thirds of the way from
	 * each end, and T reflects the last quadratic's control point; S reflects the last cubic's second
	 */
	public function testQuadraticsAreRaisedToCubicsAndSmoothCurvesReflect()
	{
		$this->assertEqualsWithDelta(
			[['M', 0, 0], ['C', 20, 40, 50, 40, 90, 0], ['C', 130, -40, 160, -40, 180, 0]],
			Path::parse('M0 0 Q30 60 90 0 T180 0')->segments,
			1e-9
		);
		$this->assertSame(
			[['M', 0.0, 0.0], ['C', 0.0, 10.0, 20.0, 10.0, 20.0, 0.0], ['C', 20.0, -10.0, 40.0, -10.0, 40.0, 0.0]],
			Path::parse('M0 0 C0 10 20 10 20 0 s20 -10 20 0')->segments
		);
	}

	/**
	 * A half circle with the sweep flag set turns through negative y, as SVG's y runs down: two cubics,
	 * the first ending at the top. Its flags need nothing between them and the next number.
	 *
	 * @dataProvider halfCircles
	 *
	 * @param string $data The path data
	 */
	public function testAnArcIsACubicPerQuarterTurn($data)
	{
		$path = Path::parse($data);

		$this->assertCount(3, $path->segments);
		$this->assertEqualsWithDelta([50, -50], array_slice($path->segments[1], 5), 1e-9);
		$this->assertEqualsWithDelta([100, 0], array_slice($path->segments[2], 5), 1e-9);
		$this->assertEqualsWithDelta([0, -50, 100, 0], $path->bounds(), 1e-6);
	}

	/**
	 * @return string[][] The same half circle, written two ways
	 */
	public function halfCircles()
	{
		return [
			'spaced' => ['M0 0 A50 50 0 0 1 100 0'],
			'packed' => ['M0 0a50 50 0 01100 0'],
		];
	}

	/**
	 * An arc of radius 0 is a line, one ending where it starts is nothing, and radii too short to reach
	 * are scaled up until they do
	 */
	public function testAnArcThatCannotBeDrawnAsGivenIsALineNothingOrScaledUp()
	{
		$this->assertSame([['M', 0.0, 0.0], ['L', 100.0, 0.0]], Path::parse('M0 0 A0 50 0 0 1 100 0')->segments);
		$this->assertSame([['M', 0.0, 0.0]], Path::parse('M0 0 A50 50 0 0 1 0 0')->segments);
		$this->assertEqualsWithDelta([0, -50, 100, 0], Path::parse('M0 0 A10 10 0 0 1 100 0')->bounds(), 1e-6);
	}

	/**
	 * A curve's bounds are its extremes, not its control points
	 */
	public function testBoundsFollowACurveNotItsControlPoints()
	{
		$this->assertEqualsWithDelta([0, 0, 100, 75], Path::parse('M0 0 C0 100 100 100 100 0')->bounds(), 1e-9);
		$this->assertEqualsWithDelta([10, 20, 110, 70], Path::ellipse(60, 45, 50, 25)->bounds(), 1e-9);
		$this->assertNull(Path::parse('')->bounds());
	}

	/**
	 * A path is written one operator a line; one of nothing but moves draws nothing
	 */
	public function testContentIsOneOperatorALine()
	{
		$this->assertSame("10 20 m\n30 40 l\n1.5 2 3 4 5 6 c\nh\n", Path::parse('M10 20 L30 40 C1.5 2 3 4 5 6 Z')->content());
		$this->assertSame('', Path::parse('M10 20 M30 40')->content());
	}

	/**
	 * A rectangle's corners are quarter ellipses of its radii, and polygon points short of a pair drop
	 * the last
	 */
	public function testRectanglesAndPolygons()
	{
		$this->assertSame("0 0 m\n10 0 l\n10 5 l\n0 5 l\nh\n", Path::rectangle(0, 0, 10, 5, 0, 0)->content());

		$rounded = Path::rectangle(0, 0, 100, 50, 10, 5)->segments;
		$this->assertSame(['M', 10, 0], $rounded[0]);
		$this->assertSame(['L', 90, 0], $rounded[1]);
		$this->assertEqualsWithDelta(['C', 95.52285, 0, 100, 2.23858, 100, 5], $rounded[2], 1e-5);

		$this->assertSame("0 0 m\n10 0 l\n10 10 l\nh\n", Path::points([0, 0, 10, 0, 10, 10, 5], true)->content());
		$this->assertSame("0 0 m\n10 0 l\n", Path::points([0, 0, 10, 0], false)->content());
	}
}
