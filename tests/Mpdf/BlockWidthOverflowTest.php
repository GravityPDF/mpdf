<?php

namespace Mpdf;

/**
 * A block whose width or min-width is wider than its container sticks out of it, as in a browser, rather than being
 * held to the container. A positioned block with different left and right margins is placed by both of them. The page
 * is A4 with 15mm margins, so the page area is 180mm wide from x = 15mm.
 */
class BlockWidthOverflowTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * The style of a block of each kind inside a 50mm container at x = 15mm, and the left edge and width in mm of the
	 * box a browser draws for it
	 *
	 * @return array[]
	 */
	public function overflowingBlocks()
	{
		return [
			'min-width' => ['min-width: 80mm', 15, 80],
			'width' => ['width: 80mm', 15, 80],
			'width with margins' => ['width: 80mm; margin: 0 5mm', 20, 80],
			'width with padding' => ['width: 50mm; padding: 0 5mm', 15, 60],
			'width with margin auto' => ['width: 80mm; margin: 0 auto', 15, 80],
			'width with margin-left auto' => ['width: 80mm; margin-left: auto', 15, 80],
			'rtl block' => ['width: 80mm; direction: rtl', -15, 80],
			'left float' => ['float: left; width: 80mm', 15, 80],
			'right float' => ['float: right; min-width: 80mm', -15, 80],
		];
	}

	/**
	 * A block wider than its container keeps its width and sticks out of the container: to the right, to the left
	 * where the text runs right to left, and away from the side a float floats to
	 *
	 * @dataProvider overflowingBlocks
	 *
	 * @param string $style
	 * @param float $left
	 * @param float $width
	 */
	public function testABlockWiderThanItsContainerSticksOut($style, $left, $width)
	{
		$boxes = $this->boxes($this->render($this->inContainer($style)));

		$this->assertBox([15, 50], $boxes[0], 'The container');
		$this->assertBox([$left, $width], $boxes[1], 'The block');
	}

	/**
	 * Legacy mode holds a block to its container
	 *
	 * @dataProvider overflowingBlocks
	 *
	 * @param string $style
	 */
	public function testLegacyModeHoldsTheBlockToItsContainer($style)
	{
		$boxes = $this->boxes($this->render($this->inContainer($style), ['cssMode' => CssMode::LEGACY]));

		$this->assertGreaterThanOrEqual(15, round($boxes[1][0], 2), 'The left edge of the block');
		$this->assertLessThanOrEqual(65, round($boxes[1][0] + $boxes[1][1], 2), 'The right edge of the block');
	}

	/**
	 * A block wider than the page area runs past the page margin
	 */
	public function testABlockWiderThanThePageAreaRunsPastIt()
	{
		$boxes = $this->boxes($this->render('<div style="background-color: #ccc; width: 190mm">aa</div>'));

		$this->assertBox([15, 190], $boxes[0], 'The block');
	}

	/**
	 * Text in a block that sticks out wraps at the block's width, not at its container's
	 */
	public function testTextWrapsAtTheWidthOfTheBlock()
	{
		$words = str_repeat('aaaa ', 30);
		$narrow = $this->render($this->inContainer('width: 50mm', $words));
		$wide = $this->render($this->inContainer('width: 100mm', $words));

		$this->assertLessThan($this->boxes($narrow)[1][2], $this->boxes($wide)[1][2], 'The wider block should take fewer lines');
	}

	/**
	 * A positioned block made wider than the page by its width or by its min-width
	 *
	 * @return array[]
	 */
	public function overflowingPositionedBlocks()
	{
		return [
			'width' => ['width: 250mm'],
			'min-width' => ['min-width: 250mm'],
		];
	}

	/**
	 * A positioned block wider than its containing block keeps its width
	 *
	 * @dataProvider overflowingPositionedBlocks
	 *
	 * @param string $style
	 */
	public function testAPositionedBlockWiderThanThePageKeepsItsWidth($style)
	{
		$boxes = $this->boxes($this->render('<div style="background-color: #ccc; position: absolute; left: 10mm; top: 10mm; ' . $style . '">aa</div>'));

		$this->assertBox([10, 250], $boxes[0], 'The positioned block');
	}

	/**
	 * A positioned block on the 210mm page with margin-left: 5mm and margin-right: 30mm, and the left edge and width in
	 * mm of its box
	 *
	 * @return array[]
	 */
	public function positionedBlocksWithUnequalMargins()
	{
		return [
			'left, right and width, so right is ignored' => ['left: 10mm; right: 20mm; width: 100mm', 15, 100],
			'right and width, so left is worked out' => ['right: 20mm; width: 100mm', 60, 100],
			'left and right, so width is worked out' => ['left: 10mm; right: 20mm', 15, 145],
		];
	}

	/**
	 * A positioned block is placed and sized by its own left and right margins, not by its left margin twice
	 *
	 * @dataProvider positionedBlocksWithUnequalMargins
	 *
	 * @param string $position
	 * @param float $left
	 * @param float $width
	 */
	public function testAPositionedBlockIsPlacedByBothItsMargins($position, $left, $width)
	{
		$html = '<div style="background-color: #ccc; position: absolute; top: 10mm; margin-left: 5mm; margin-right: 30mm; '
			. $position . '">aa</div>';

		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
			$boxes = $this->boxes($this->render($html, ['cssMode' => $cssMode]));

			$this->assertBox([$left, $width], $boxes[0], 'The positioned block in ' . $cssMode . ' mode');
		}
	}

	/**
	 * A grey block in a light grey 50mm container, cleared so a floated block stays inside it
	 *
	 * @param string $style The block's style
	 * @param string $text The block's text
	 *
	 * @return string
	 */
	private function inContainer($style, $text = 'aa')
	{
		return '<div style="width: 50mm; background-color: #eee"><div style="background-color: #ccc; ' . $style . '">'
			. $text . '</div><div style="clear: both"></div></div>';
	}

	/**
	 * Asserts that a box starts at the expected left edge and has the expected width, in mm
	 *
	 * @param float[] $expected The left edge and width
	 * @param float[] $box A box from boxes()
	 * @param string $name What the box is
	 */
	private function assertBox(array $expected, array $box, $name)
	{
		$this->assertEqualsWithDelta($expected[0], $box[0], 0.01, 'The left edge of ' . $name);
		$this->assertEqualsWithDelta($expected[1], $box[1], 0.01, 'The width of ' . $name);
	}

	/**
	 * The rectangles drawn on the first page, as rectangles() gives them
	 *
	 * @param string $pdf
	 *
	 * @return array[]
	 */
	private function boxes($pdf)
	{
		$boxes = $this->rectangles($this->pages($pdf)[0]);
		$this->assertNotEmpty($boxes, 'A rectangle should be drawn');

		return $boxes;
	}
}
