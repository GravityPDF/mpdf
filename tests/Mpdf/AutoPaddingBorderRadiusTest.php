<?php

namespace Mpdf;

/**
 * With autoPadding, a rounded block is padded so its text clears the curve of each corner. A percentage radius is
 * resolved against the block's border box: the horizontal one against its width, and the vertical one against its
 * width too, since its height is not known when the padding is set.
 */
class AutoPaddingBorderRadiusTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/**
	 * How far right of and below an unrounded block's text the first line of a block styled with the given CSS is
	 * drawn, in millimetres
	 *
	 * @param string $css
	 * @param string $wrapper Style of a block around it, or '' for none
	 * @param array $config
	 *
	 * @return float[] [x, y]
	 */
	private function textOffset($css, $wrapper = '', $config = [])
	{
		$plain = $this->firstLine('', $wrapper, $config);
		$rounded = $this->firstLine($css, $wrapper, $config);

		return [$rounded[0] - $plain[0], $rounded[1] - $plain[1]];
	}

	/**
	 * Where the first line of a block is drawn, with autoPadding on unless $config turns it off
	 *
	 * @param string $css
	 * @param string $wrapper
	 * @param array $config
	 *
	 * @return float[] [left, top]
	 */
	private function firstLine($css, $wrapper, $config)
	{
		$block = '<div style="background: #ccc; ' . $css . '">Text</div>';
		$html = $wrapper === '' ? $block : '<div style="' . $wrapper . '">' . $block . '</div>';

		// Read raw: firstLineTops() and spans() round to 0.1 mm
		$box = $this->drawDocument($html, $config + ['autoPadding' => true])->drawnBoxes[0];

		return [$box[1], $box[3]];
	}

	/**
	 * Assert that two blocks draw their text at the same place, and that it is moved in from an unrounded block's
	 *
	 * @param string $css
	 * @param string $sameAs
	 * @param string $wrapper
	 * @param array $config
	 */
	private function assertPaddedAs($css, $sameAs, $wrapper = '', $config = [])
	{
		$expected = $this->textOffset($sameAs, $wrapper, $config);

		$this->assertGreaterThan(0.1, min($expected));
		$this->assertEqualsWithDelta($expected, $this->textOffset($css, $wrapper, $config), 0.001);
	}

	/**
	 * The radius on a block 40 mm wide, and how far in from the left and down from the top its text is drawn: where
	 * the curve runs at 45 degrees, 29% of a circular radius in each direction
	 *
	 * @return array
	 */
	public static function offsetProvider()
	{
		return [
			'50%' => ['border-radius: 50%', 5.858, 5.858],
			'10%' => ['border-radius: 10%', 1.172, 1.172],
			'5mm' => ['border-radius: 5mm', 1.464, 1.464],
			'a flat corner is padded mostly down' => ['border-radius: 20mm / 4mm', 0.388, 3.215],
			'padding that already clears the curve is kept' => ['border-radius: 20mm; padding: 7mm', 7, 7],
			'padding clear across is only added to down' => ['border-radius: 20mm / 4mm; padding: 2mm', 2, 2.256],
		];
	}

	/**
	 * The text of a block with autoPadding is drawn clear of the corner's curve, with as little padding across and
	 * down together as puts the corner of its content on the curve
	 *
	 * @dataProvider offsetProvider
	 *
	 * @param string $css
	 * @param float $x
	 * @param float $y
	 */
	public function testTextIsDrawnClearOfTheCorner($css, $x, $y)
	{
		$this->assertEqualsWithDelta([$x, $y], $this->textOffset('width: 40mm; ' . $css), 0.001);
	}

	/**
	 * A block rounded alike on the left and right is padded alike on both, however much the corners before one added:
	 * its right-aligned text is as far in from the right as left-aligned text is from the left
	 */
	public function testRightIsPaddedAsTheLeft()
	{
		list($left) = $this->textOffset('border-radius: 20mm', 'width: 50mm');
		list($right) = $this->textOffset('border-radius: 20mm', 'width: 50mm; text-align: right');

		$this->assertGreaterThan(0.1, $left);
		$this->assertEqualsWithDelta(-$left, $right, 0.001);
	}

	/**
	 * A percentage radius and the length it comes to on a block 40 mm wide
	 *
	 * @return array
	 */
	public static function equivalentProvider()
	{
		return [
			'50% is 20 mm, the vertical taken of the width too' => ['border-radius: 50%', 'border-radius: 20mm'],
			'10% is 4 mm' => ['border-radius: 10%', 'border-radius: 4mm'],
			'50% / 10% is 20 mm / 4 mm' => ['border-radius: 50% / 10%', 'border-radius: 20mm / 4mm'],
			'one corner' => ['border-top-left-radius: 25% 10%', 'border-top-left-radius: 10mm 4mm'],
		];
	}

	/**
	 * A percentage radius pads a block as the length it comes to against the block's width does
	 *
	 * @dataProvider equivalentProvider
	 *
	 * @param string $percent
	 * @param string $length
	 */
	public function testPercentagePadsAsItsLength($percent, $length)
	{
		$this->assertPaddedAs('width: 40mm; ' . $percent, 'width: 40mm; ' . $length);
	}

	/**
	 * A block with no width resolves its percentage against the width it takes from the block around it
	 */
	public function testBlockWithoutWidthUsesTheWidthItTakes()
	{
		$this->assertPaddedAs('border-radius: 10%', 'border-radius: 5mm', 'width: 50mm');
	}

	/**
	 * The border box a percentage is of includes the block's own padding: 50% of a 20 mm block with 10 mm of padding
	 * each side is 20 mm
	 */
	public function testBorderBoxIncludesPadding()
	{
		$this->assertPaddedAs('width: 20mm; padding: 0 10mm; border-radius: 50%', 'width: 20mm; padding: 0 10mm; border-radius: 20mm');
	}

	/**
	 * The legacy cssMode pads a percentage radius the same way
	 */
	public function testLegacyModePadsTheSameWay()
	{
		$this->assertPaddedAs('width: 40mm; border-radius: 50%', 'width: 40mm; border-radius: 20mm', '', ['cssMode' => CssMode::LEGACY]);
	}

	/**
	 * Without autoPadding a radius adds no padding
	 */
	public function testNoPaddingWithoutAutoPadding()
	{
		list($x, $y) = $this->textOffset('width: 40mm; border-radius: 50%', '', ['autoPadding' => false]);

		$this->assertEqualsWithDelta(0, $x, 0.001);
		$this->assertEqualsWithDelta(0, $y, 0.001);
	}
}
