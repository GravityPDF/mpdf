<?php

namespace Mpdf\Image;

use Mpdf\Mpdf;

/**
 * The outermost <svg> takes its width and height, and fits its viewBox into them as preserveAspectRatio says, even
 * when the two are not the same shape (#792). A pixel is 0.75pt, so a 200 by 100 SVG is 150 by 75pt
 */
class SvgOuterViewBoxTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The picture's bounding box is the width and height, placed about the viewBox as preserveAspectRatio says
	 *
	 * @dataProvider boxProvider
	 *
	 * @param string  $attributes
	 * @param float[] $box        x, y, width and height, in points
	 */
	public function testTheBoxIsTheWidthAndHeight($attributes, $box)
	{
		$picture = $this->draw($attributes, '<rect width="10" height="10" />');

		$this->assertEqualsWithDelta($box, [$picture['x'], $picture['y'], $picture['w'], $picture['h']], 0.05);
	}

	/**
	 * <svg> attributes and the bounding box of the picture they make. In a 200 by 100 box a 10 by 10 viewBox is
	 * 10px a unit to meet and 20px a unit to slice
	 *
	 * @return array
	 */
	public function boxProvider()
	{
		$wide = 'width="200" height="100" viewBox="0 0 10 10"';

		return [
			'wide, xMidYMid meet by default' => [$wide, [-37.5, 0, 150, -75]],
			'wide, xMinYMin meet' => [$wide . ' preserveAspectRatio="xMinYMin meet"', [0, 0, 150, -75]],
			'wide, xMaxYMax meet' => [$wide . ' preserveAspectRatio="xMaxYMax"', [-75, 0, 150, -75]],
			'wide, xMidYMid slice' => [$wide . ' preserveAspectRatio="xMidYMid slice"', [0, -37.5, 150, -75]],
			'wide, xMinYMax slice' => [$wide . ' preserveAspectRatio="xMinYMax slice"', [0, -75, 150, -75]],
			'wide, none' => [$wide . ' preserveAspectRatio="none"', [0, 0, 150, -75]],
			'tall, xMidYMid meet' => ['width="100" height="200" viewBox="0 0 10 10"', [0, 37.5, 75, -150]],
			'tall, xMidYMax meet' => ['width="100" height="200" viewBox="0 0 10 10" preserveAspectRatio="xMidYMax"', [0, 75, 75, -150]],
			'viewBox origin' => ['width="200" height="100" viewBox="5 5 10 10" preserveAspectRatio="xMinYMin"', [37.5, -37.5, 150, -75]],
			'width alone' => ['width="200" viewBox="0 0 20 10"', [0, 0, 150, -75]],
			'height alone' => ['height="100" viewBox="0 0 20 10"', [0, 0, 150, -75]],
			'the viewBox\'s shape' => ['width="200" height="100" viewBox="0 0 20 10"', [0, 0, 150, -75]],
		];
	}

	/**
	 * The viewBox is drawn at the scale that fits it: 10px a unit to meet, 20px to slice
	 *
	 * @dataProvider scaleProvider
	 *
	 * @param string $preserveAspectRatio
	 * @param string $side                 the 10 unit square's side, in points
	 */
	public function testTheViewBoxIsScaledToFit($preserveAspectRatio, $side)
	{
		$picture = $this->draw('width="200" height="100" viewBox="0 0 10 10" preserveAspectRatio="' . $preserveAspectRatio . '"', '<rect width="10" height="10" />');

		$this->assertMatchesRegularExpression('/ ' . $side . ' 0\.000 l /', $picture['data']);
	}

	/**
	 * preserveAspectRatio values and the side of a 10 unit square they draw
	 *
	 * @return array
	 */
	public function scaleProvider()
	{
		return [
			'meet' => ['xMidYMid meet', '75\.0\d\d'],
			'slice' => ['xMidYMid slice', '150\.0\d\d'],
		];
	}

	/**
	 * preserveAspectRatio="none" draws at the scale along x and stretches what it draws to the scale along y
	 */
	public function testNoneStretchesToTheHeight()
	{
		$picture = $this->draw('width="200" height="100" viewBox="0 0 10 10" preserveAspectRatio="none"', '<rect width="10" height="10" />');

		$this->assertStringStartsWith('1 0 0 0.50000 0 0 cm ', $picture['data']);
		$this->assertMatchesRegularExpression('/ 150\.0\d\d 0\.000 l /', $picture['data']);
	}

	/**
	 * A percentage is still of the viewBox, not of the box it is fitted into
	 */
	public function testPercentagesAreOfTheViewBox()
	{
		$picture = $this->draw('width="200" height="100" viewBox="0 0 10 10"', '<rect width="50%" height="10" />');

		$this->assertMatchesRegularExpression('/ 37\.5\d\d 0\.000 l /', $picture['data']);
	}

	/**
	 * Draw an SVG with the attributes, holding the elements, as an image
	 *
	 * @param string $attributes
	 * @param string $elements
	 *
	 * @return array the picture's form object
	 */
	private function draw($attributes, $elements)
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" ' . $attributes . '>' . $elements . '</svg>';

		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" />');

		return reset($mpdf->formobjects);
	}

}
