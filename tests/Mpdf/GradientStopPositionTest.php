<?php

namespace Mpdf;

/**
 * A gradient's colour stops and position are read whole, a percentage with a fraction such as 12.5% and a zero length
 * such as 0px included. A stop placed before an earlier one moves up to it, as CSS Images does.
 */
class GradientStopPositionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const RED = '1.000 0.000 0.000';

	const GREEN = '0.000 0.502 0.000';

	const BLUE = '0.000 0.000 1.000';

	const YELLOW = '1.000 1.000 0.000';

	/**
	 * 10px across the 60mm box, as a fraction of its width
	 */
	const TEN_PX = 0.0440972;

	/**
	 * The gradient registered for a 60 × 30 mm block with the given background
	 *
	 * @param string $background
	 * @param string $cssMode
	 *
	 * @return array
	 */
	private function gradient($background, $cssMode)
	{
		$mpdf = new Mpdf(['cssMode' => $cssMode]);
		$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; background: ' . $background . '"></div>');
		$mpdf->Output('', 'S');

		$this->assertCount(1, $mpdf->gradients);

		return reset($mpdf->gradients);
	}

	/**
	 * Each stop given to the shading function reaches it with the colour and offset CSS places it at, in both CSS modes
	 *
	 * @dataProvider stopProvider
	 *
	 * @param string $background
	 * @param array[] $stops Each stop as [colour, offset]
	 * @param string $cssMode
	 */
	public function testStopsSitWhereCssPlacesThem($background, array $stops, $cssMode)
	{
		$gradient = $this->gradient($background, $cssMode);

		$actual = array_map(function ($stop) {
			return [$stop['col'], (float) $stop['offset']];
		}, $gradient['stops']);

		$this->assertEqualsWithDelta($stops, $actual, 0.0001);
	}

	/**
	 * Stops at fractional percentages, at zero lengths, and before an earlier stop
	 *
	 * @return array
	 */
	public function stopProvider()
	{
		$cases = [];

		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
			$cases += [
				$cssMode . ', 12.5%' => [
					'linear-gradient(to right, red 12.5%, blue 50%)',
					[[self::RED, 0], [self::RED, 0.125], [self::BLUE, 0.5], [self::BLUE, 1]],
					$cssMode,
				],
				$cssMode . ', last at 0px' => [
					'linear-gradient(to right, red 50%, blue 0px)',
					[[self::RED, 0], [self::RED, 0.5], [self::BLUE, 0.5], [self::BLUE, 1]],
					$cssMode,
				],
				$cssMode . ', last at 0%' => [
					'linear-gradient(to right, red 50%, blue 0%)',
					[[self::RED, 0], [self::RED, 0.5], [self::BLUE, 0.5], [self::BLUE, 1]],
					$cssMode,
				],
				$cssMode . ', middle stop before an earlier one' => [
					'linear-gradient(to right, red, green 60%, blue 20%, yellow)',
					[[self::RED, 0], [self::GREEN, 0.6], [self::BLUE, 0.6], [self::YELLOW, 1]],
					$cssMode,
				],
				$cssMode . ', radial 12.5% then 0px' => [
					'radial-gradient(red 12.5%, blue 0px)',
					[[self::RED, 0], [self::RED, 0.125], [self::BLUE, 0.125], [self::BLUE, 1]],
					$cssMode,
				],
				$cssMode . ', stops in order are untouched' => [
					'linear-gradient(to right, red 20%, green, blue 80%)',
					[[self::RED, 0], [self::RED, 0.2], [self::GREEN, 0.5], [self::BLUE, 0.8], [self::BLUE, 1]],
					$cssMode,
				],
			];
		}

		return $cases;
	}

	/**
	 * A repeating gradient whose stops all sit at one position, written there or moved up to it, has a period of zero
	 * length. It fills the box with the average of its colours, as CSS Images draws it, rather than repeating without
	 * end
	 *
	 * @dataProvider zeroPeriodProvider
	 *
	 * @param string $background
	 * @param string $cssMode
	 */
	public function testRepeatingGradientWithZeroPeriodIsTheAverageColour($background, $cssMode)
	{
		$gradient = $this->gradient($background, $cssMode);

		$this->assertSame([
			['col' => '0.500 0.000 0.500', 'opacity' => 1.0, 'offset' => 0],
			['col' => '0.500 0.000 0.500', 'opacity' => 1.0, 'offset' => 1],
		], $gradient['stops']);
	}

	/**
	 * Repeating gradients from red to blue whose stops all sit at one position
	 *
	 * @return array
	 */
	public function zeroPeriodProvider()
	{
		$cases = [];

		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
			$cases += [
				$cssMode . ', linear 0px' => ['repeating-linear-gradient(red 0px, blue 0px)', $cssMode],
				$cssMode . ', linear 0%' => ['repeating-linear-gradient(red 0%, blue 0%)', $cssMode],
				$cssMode . ', radial 0px' => ['repeating-radial-gradient(red 0px, blue 0px)', $cssMode],
				$cssMode . ', last stop moved up to the first' => ['repeating-linear-gradient(red 50%, blue 0%)', $cssMode],
			];
		}

		return $cases;
	}

	/**
	 * A position written as a fractional percentage or a zero length puts the gradient where it says, in both CSS modes
	 *
	 * @dataProvider positionProvider
	 *
	 * @param string $background
	 * @param float[] $coords [x0, y0, x1, y1] for a linear gradient, [fx, fy, cx, cy] for a radial one, y running up
	 * @param string $cssMode
	 */
	public function testPositionIsRead($background, array $coords, $cssMode)
	{
		$gradient = $this->gradient($background, $cssMode);

		$this->assertEqualsWithDelta($coords, array_map('floatval', array_slice($gradient['coords'], 0, 4)), 0.0001);
	}

	/**
	 * Radial centres and linear start points at fractional percentages and zero lengths
	 *
	 * @return array
	 */
	public function positionProvider()
	{
		$cases = [];

		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
			$cases += [
				$cssMode . ', radial at 12.5% 50%' => ['radial-gradient(at 12.5% 50%, red, blue)', [0.125, 0.5, 0.125, 0.5], $cssMode],
				$cssMode . ', radial at 0px 0px' => ['radial-gradient(at 0px 0px, red, blue)', [0, 1, 0, 1], $cssMode],
				$cssMode . ', radial 0px 0px' => ['radial-gradient(0px 0px, red, blue)', [0, 1, 0, 1], $cssMode],
				// A circle is drawn in a square as wide as the box, whose top edge sits at half its height
				$cssMode . ', radial 12.5% 50%, circle' => ['radial-gradient(12.5% 50%, circle, red, blue)', [0.125, 0.25, 0.125, 0.25], $cssMode],
				$cssMode . ', radial 0px 0px, circle' => ['radial-gradient(0px 0px, circle, red, blue)', [0, 0.5, 0, 0.5], $cssMode],
				$cssMode . ', linear from 12.5% 50%' => ['-moz-linear-gradient(12.5% 50%, red, blue)', [0.125, 0.5, 0.875, 0.5], $cssMode],
				// Off an axis, the axis is drawn in a square as wide as the box, whose top edge sits at half its height
				$cssMode . ', linear from 10px 0px' => ['linear-gradient(10px 0px, red, blue)', [self::TEN_PX, 0.5, 1 - self::TEN_PX, 0], $cssMode],
			];
		}

		return $cases;
	}

}
