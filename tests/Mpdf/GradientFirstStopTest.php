<?php

namespace Mpdf;

/**
 * The first argument of a gradient is a colour stop when it starts with a colour, however its position is written,
 * and a direction otherwise. A first stop at a unitless 0, such as "red 0", used to be read as the angle and dropped.
 */
class GradientFirstStopTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const RED = '1.000 0.000 0.000';

	const BLUE = '0.000 0.000 1.000';

	/**
	 * The gradient registered for a block with the given background
	 *
	 * @param string $background
	 * @param string $cssMode
	 *
	 * @return array
	 */
	private function gradient($background, $cssMode = CssMode::STANDARD)
	{
		$mpdf = new Mpdf(['cssMode' => $cssMode]);
		$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; background: ' . $background . '"></div>');
		$mpdf->Output('', 'S');

		$this->assertCount(1, $mpdf->gradients);

		return reset($mpdf->gradients);
	}

	/**
	 * The colours of the first stops given to the shading function, as many as expected
	 *
	 * @param array $gradient
	 * @param int $count
	 *
	 * @return string[]
	 */
	private function leadingColours(array $gradient, $count)
	{
		return array_column(array_slice($gradient['stops'], 0, $count), 'col');
	}

	/**
	 * A first stop at a unitless 0 keeps its colour and starts the gradient, in linear and radial gradients alike
	 *
	 * @dataProvider stopAtZeroProvider
	 *
	 * @param string $background
	 * @param int $type
	 * @param bool $repeats Whether the stops are drawn again beyond the last one
	 */
	public function testFirstStopAtZeroIsKept($background, $type, $repeats)
	{
		$gradient = $this->gradient($background);

		$this->assertSame($type, $gradient['type']);
		$this->assertSame([self::RED, self::BLUE], $this->leadingColours($gradient, 2));
		$this->assertSame(0, $gradient['stops'][0]['offset']);

		if ($repeats) {
			$this->assertSame(self::RED, $gradient['stops'][2]['col'], 'drawn again after the last stop');
		} else {
			$this->assertCount(2, $gradient['stops']);
		}
	}

	/**
	 * Gradients whose first stop sits at 0, written unitless, as a percentage or after a shape
	 *
	 * @return array
	 */
	public function stopAtZeroProvider()
	{
		return [
			'repeating radial, unitless 0 and px' => ['repeating-radial-gradient(red 0, blue 10px)', Gradient::TYPE_RADIAL, true],
			'repeating radial, percentages' => ['repeating-radial-gradient(red 0%, blue 10%)', Gradient::TYPE_RADIAL, true],
			'repeating radial after a shape' => ['repeating-radial-gradient(circle, red 0, blue 10px)', Gradient::TYPE_RADIAL, true],
			'radial' => ['radial-gradient(red 0, blue)', Gradient::TYPE_RADIAL, false],
			'radial, rgb()' => ['radial-gradient(rgb(255, 0, 0) 0, blue)', Gradient::TYPE_RADIAL, false],
			'linear' => ['linear-gradient(red 0, blue)', Gradient::TYPE_LINEAR, false],
			'linear, rgb()' => ['linear-gradient(rgb(255, 0, 0) 0, blue)', Gradient::TYPE_LINEAR, false],
			'linear, hex' => ['linear-gradient(#f00 0, #00f)', Gradient::TYPE_LINEAR, false],
			'repeating linear' => ['repeating-linear-gradient(red 0, blue 10px)', Gradient::TYPE_LINEAR, true],
		];
	}

	/**
	 * A linear gradient whose first stop sits at 0 runs down the box, as one with no direction does
	 */
	public function testLinearGradientStartingWithAStopRunsDown()
	{
		$gradient = $this->gradient('linear-gradient(red 0, blue)');

		$this->assertEqualsWithDelta([0.5, 1, 0.5, 0], array_map('floatval', array_slice($gradient['coords'], 0, 4)), 0.0001);
	}

	/**
	 * A bare 0 before the stops is still the angle, pointing up, and every stop after it is kept
	 */
	public function testBareZeroBeforeTheStopsIsTheAngle()
	{
		$gradient = $this->gradient('linear-gradient(0, red, blue)');

		$this->assertSame([self::RED, self::BLUE], $this->leadingColours($gradient, 2));
		$this->assertCount(2, $gradient['stops']);
		$this->assertEqualsWithDelta([0, 0, 0, 1], array_map('floatval', array_slice($gradient['coords'], 0, 4)), 0.0001);
	}

	/**
	 * A bare 0 before the stops of a radial gradient is a position, and every stop after it is kept
	 */
	public function testBareZeroBeforeTheStopsOfARadialGradientIsAPosition()
	{
		$gradient = $this->gradient('radial-gradient(0, red, blue)');

		$this->assertSame([self::RED, self::BLUE], $this->leadingColours($gradient, 2));
		$this->assertCount(2, $gradient['stops']);
	}

	/**
	 * A first stop at 0 is kept in legacy CSS mode too, as its colour was lost rather than drawn differently
	 *
	 * @dataProvider legacyProvider
	 *
	 * @param string $background
	 */
	public function testFirstStopAtZeroIsKeptInLegacyMode($background)
	{
		$gradient = $this->gradient($background, CssMode::LEGACY);

		$this->assertSame([self::RED, self::BLUE], $this->leadingColours($gradient, 2));
	}

	/**
	 * A linear and a radial gradient whose first stop sits at a unitless 0
	 *
	 * @return array
	 */
	public function legacyProvider()
	{
		return [
			'linear' => ['linear-gradient(red 0, blue)'],
			'repeating radial' => ['repeating-radial-gradient(red 0, blue 10px)'],
		];
	}

}
