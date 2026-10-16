<?php

namespace Mpdf\Image;

use Mpdf\Mpdf;

/**
 * An <svg> inside an SVG sets up a viewport of its own: moved by x and y, clipped to its width and height, with its
 * viewBox fitted into it (#785). The outer SVG is 80px square, and a pixel is 0.75pt
 */
class SvgNestedViewportTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The nested SVG's width, height and viewBox are its own, so the picture keeps the outer SVG's size
	 */
	public function testThePictureKeepsTheOuterSize()
	{
		$picture = $this->draw('<svg width="40" height="20" viewBox="0 0 10 10"><rect width="10" height="10" /></svg>');

		$this->assertEqualsWithDelta(60, $picture['w'], 0.1);
		$this->assertEqualsWithDelta(-60, $picture['h'], 0.1);
	}

	/**
	 * The viewport is moved by x and y and clipped to width and height, before anything in it is drawn
	 *
	 * @dataProvider viewportProvider
	 *
	 * @param string $attributes
	 * @param string $viewport
	 */
	public function testTheViewportIsMovedAndClipped($attributes, $viewport)
	{
		$picture = $this->draw('<svg ' . $attributes . '><rect width="80" height="80" /></svg>');

		$this->assertStringContainsString($viewport . ' q ', $picture['data']);
	}

	/**
	 * Nested <svg> attributes and the move and clip they start with. An outer SVG without a viewBox comes out a
	 * hair over 80px, through the rounding in its px to mm conversion, and percentages of it carry that
	 *
	 * @return array
	 */
	public function viewportProvider()
	{
		return [
			'width and height' => ['width="40" height="20"', ' q 1 0 0 1 0.000 0.000 cm 0 0 30.000 -15.000 re W n '],
			'x and y' => ['x="40" y="20" width="40" height="40"', ' q 1 0 0 1 30.000 -15.000 cm 0 0 30.000 -30.000 re W n '],
			'percentages of the outer SVG' => ['x="50%" width="25%" height="50%"', ' q 1 0 0 1 30.009 0.000 cm 0 0 15.005 -30.009 re W n '],
			'width and height default to 100%' => ['x="20"', ' q 1 0 0 1 15.000 0.000 cm 0 0 60.019 -60.019 re W n '],
		];
	}

	/**
	 * overflow="visible" leaves the viewport unclipped
	 */
	public function testVisibleOverflowIsNotClipped()
	{
		$picture = $this->draw('<svg x="40" width="40" height="40" overflow="visible"><rect width="80" height="80" /></svg>');

		$this->assertStringContainsString(' q 1 0 0 1 30.000 0.000 cm  q ', $picture['data']);
		$this->assertStringNotContainsString(' re W n ', $picture['data']);
	}

	/**
	 * The viewBox is scaled into the viewport and placed in it as preserveAspectRatio says
	 *
	 * @dataProvider viewBoxProvider
	 *
	 * @param string $attributes
	 * @param string $fit
	 */
	public function testTheViewBoxIsFittedIntoTheViewport($attributes, $fit)
	{
		$picture = $this->draw('<svg width="80" height="40" ' . $attributes . '><rect width="10" height="10" /></svg>');

		$this->assertStringContainsString(' re W n ' . $fit . ' q ', $picture['data']);
	}

	/**
	 * viewBox and preserveAspectRatio attributes on an 80 by 40 viewport, and the matrix that fits the one into the other
	 *
	 * @return array
	 */
	public function viewBoxProvider()
	{
		return [
			'xMidYMid meet by default' => ['viewBox="0 0 10 10"', '4.00000 0 0 4.00000 15.000 0.000 cm '],
			'xMinYMin meet' => ['viewBox="0 0 10 10" preserveAspectRatio="xMinYMin meet"', '4.00000 0 0 4.00000 0.000 0.000 cm '],
			'xMaxYMax meet' => ['viewBox="0 0 10 10" preserveAspectRatio="xMaxYMax"', '4.00000 0 0 4.00000 30.000 0.000 cm '],
			'xMidYMid slice' => ['viewBox="0 0 10 10" preserveAspectRatio="xMidYMid slice"', '8.00000 0 0 8.00000 0.000 15.000 cm '],
			'xMinYMax slice' => ['viewBox="0 0 10 10" preserveAspectRatio="xMinYMax slice"', '8.00000 0 0 8.00000 0.000 30.000 cm '],
			'none stretches' => ['viewBox="0 0 10 10" preserveAspectRatio="none"', '8.00000 0 0 4.00000 0.000 0.000 cm '],
			'viewBox origin' => ['viewBox="5 5 10 10" preserveAspectRatio="xMinYMin"', '4.00000 0 0 4.00000 -15.000 15.000 cm '],
		];
	}

	/**
	 * What follows the nested SVG is drawn in the outer SVG again, with percentages of the outer size
	 */
	public function testTheOuterSvgIsRestoredAfterTheNestedOne()
	{
		$picture = $this->draw(
			'<svg width="40" height="40" viewBox="0 0 10 10"><rect width="10" height="10" /></svg>'
			. '<rect width="50%" height="10" />'
		);

		$this->assertSame(substr_count($picture['data'], ' q '), substr_count($picture['data'], ' Q'));
		$this->assertMatchesRegularExpression('/ Q\s+ Q\s+ q .* 0\.000 0\.000 m 30\.0\d\d 0\.000 l /', $picture['data']);
	}

	/**
	 * A presentation attribute on the nested SVG reaches what is drawn in it, and stops at its end
	 */
	public function testPresentationAttributesAreInheritedInsideTheNestedSvg()
	{
		$picture = $this->draw(
			'<svg width="40" height="40" fill="red"><rect width="10" height="10" /></svg>'
			. '<rect width="10" height="10" />'
		);

		$this->assertMatchesRegularExpression('/W n\s+q 1\.000 0\.000 0\.000 rg .* Q\s+ Q\s+ q 0\.000 0\.000 0\.000 rg /s', $picture['data']);
	}

	/**
	 * A nested SVG inside a nested SVG is placed inside the viewport around it, and both close in turn
	 */
	public function testViewportsNest()
	{
		$picture = $this->draw(
			'<svg x="8" y="8" width="64" height="64" viewBox="0 0 8 8">'
			. '<svg x="2" y="2" width="4" height="4" viewBox="0 0 1 1"><rect width="1" height="1" /></svg>'
			. '</svg>'
		);

		$this->assertStringContainsString(
			' q 1 0 0 1 6.000 -6.000 cm 0 0 48.000 -48.000 re W n 8.00000 0 0 8.00000 0.000 0.000 cm '
			. ' q 1 0 0 1 1.500 -1.500 cm 0 0 3.000 -3.000 re W n 4.00000 0 0 4.00000 0.000 0.000 cm ',
			$picture['data']
		);
		$this->assertSame(substr_count($picture['data'], ' q '), substr_count($picture['data'], ' Q'));
	}

	/**
	 * Draw an 80px square SVG holding the elements, as an image
	 *
	 * @param string $elements
	 *
	 * @return array the picture's form object
	 */
	private function draw($elements)
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">' . $elements . '</svg>';

		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" />');

		return reset($mpdf->formobjects);
	}

}
