<?php

namespace Mpdf;

/**
 * min-width and max-width on a block hold its width, so max-width with margin: auto centres it, as in a browser.
 * The page is A4 with 15mm margins, so the page area is 180mm wide from x = 15mm.
 */
class BlockMinMaxWidthTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A block of each kind with its limits, and the left edge and width in mm of the box a browser draws
	 *
	 * @return array[]
	 */
	public function constrainedBlocks()
	{
		return [
			'max-width with margin auto' => ['<div style="%s max-width: 100mm; margin: 0 auto">aa</div>', 55, 100],
			'width over max-width' => ['<div style="%s width: 150mm; max-width: 100mm">aa</div>', 15, 100],
			'width under min-width' => ['<div style="%s width: 50mm; min-width: 80mm">aa</div>', 15, 80],
			'max-width as a percentage' => ['<div style="%s max-width: 50%%">aa</div>', 15, 90],
			'margin-left auto alone' => ['<div style="%s max-width: 100mm; margin-left: auto">aa</div>', 95, 100],
			'rtl block' => ['<div style="%s max-width: 100mm; direction: rtl">aa</div>', 95, 100],
			'float' => ['<div style="%s float: left; max-width: 100mm">aa</div><div style="clear: both"></div>', 15, 100],
			'nested containers' => ['<div style="width: 120mm"><div style="%s max-width: 50%%; margin: 0 auto">aa</div></div>', 45, 60],
			'padding and borders' => ['<div style="%s max-width: 100mm; margin: 0 auto; padding: 5mm; border: 1mm solid #000">aa</div>', 49, 112],
			'min-width as a percentage' => ['<div style="width: 120mm"><div style="%s width: 30mm; min-width: 50%%">aa</div></div>', 15, 60],
			'min-width over max-width' => ['<div style="%s max-width: 50mm; min-width: 80mm; margin: 0 auto">aa</div>', 65, 80],
		];
	}

	/**
	 * The box of a block is held within its min-width and max-width, and its margins place the narrowed box
	 *
	 * @dataProvider constrainedBlocks
	 *
	 * @param string $html
	 * @param float $left
	 * @param float $width
	 */
	public function testTheBoxIsHeldWithinItsLimits($html, $left, $width)
	{
		$this->assertBox($left, $width, sprintf($html, 'background-color: #ccc;'));
	}

	/**
	 * A block in legacy mode ignores min-width and max-width, as mPDF v7 did, so it keeps its width or fills its
	 * container
	 *
	 * @return array[]
	 */
	public function legacyBlocks()
	{
		return [
			'max-width with margin auto' => ['<div style="%s max-width: 100mm; margin: 0 auto">aa</div>', 15, 180],
			'width over max-width' => ['<div style="%s width: 150mm; max-width: 100mm">aa</div>', 15, 150],
			'width under min-width' => ['<div style="%s width: 50mm; min-width: 80mm">aa</div>', 15, 50],
			'float' => ['<div style="%s float: left; max-width: 100mm">aa</div><div style="clear: both"></div>', 15, 180],
			'positioned block' => ['<div style="%s position: absolute; left: 10mm; top: 10mm; width: 150mm; max-width: 100mm">aa</div>', 10, 150],
		];
	}

	/**
	 * Legacy mode ignores the two properties, as before
	 *
	 * @dataProvider legacyBlocks
	 *
	 * @param string $html
	 * @param float $left
	 * @param float $width
	 */
	public function testLegacyModeIgnoresTheLimits($html, $left, $width)
	{
		$this->assertBox($left, $width, sprintf($html, 'background-color: #ccc;'), ['cssMode' => CssMode::LEGACY]);
	}

	/**
	 * A positioned block takes the same clamp: on a set width, on the width its left and right leave, and on the
	 * width it shrinks to fit its content. An absolutely positioned block is placed on the whole 210mm page.
	 *
	 * @return array[]
	 */
	public function positionedBlocks()
	{
		return [
			'width over max-width' => ['<div style="%s position: absolute; left: 10mm; top: 10mm; width: 150mm; max-width: 100mm">aa</div>', 10, 100],
			'left and right with margin auto' => ['<div style="%s position: absolute; left: 0; right: 0; top: 10mm; max-width: 100mm; margin: 0 auto">aa</div>', 55, 100],
			'shrink-to-fit over max-width' => ['<div style="%s position: absolute; left: 10mm; top: 10mm; max-width: 50mm">' . str_repeat('aa ', 16) . '</div>', 10, 50],
			'shrink-to-fit under min-width' => ['<div style="%s position: absolute; left: 10mm; top: 10mm; min-width: 80mm">aa</div>', 10, 80],
		];
	}

	/**
	 * The box of a positioned block is held within its limits
	 *
	 * @dataProvider positionedBlocks
	 *
	 * @param string $html
	 * @param float $left
	 * @param float $width
	 */
	public function testAPositionedBlockIsHeldWithinItsLimits($html, $left, $width)
	{
		$this->assertBox($left, $width, sprintf($html, 'background-color: #ccc;'));
	}

	/**
	 * A block with a max-width wider than its container, or a min-width it already meets, is laid out as if neither
	 * were set
	 */
	public function testALimitTheWidthAlreadyMeetsChangesNothing()
	{
		$plain = $this->render('<div style="background-color: #ccc; margin: 0 auto">aa</div>');
		$limited = $this->render('<div style="background-color: #ccc; margin: 0 auto; max-width: 500mm; min-width: 10mm">aa</div>');

		$this->assertSame($this->pages($plain), $this->pages($limited));
	}

	/**
	 * A keyword such as max-width: none or min-width: auto puts no limit on the block
	 */
	public function testKeywordsPutNoLimit()
	{
		$this->assertBox(15, 180, '<div style="background-color: #ccc; max-width: none; min-width: auto">aa</div>');
	}

	/**
	 * Asserts that the first filled rectangle on the first page, the block's background, starts at $left and is
	 * $width wide, in mm
	 *
	 * @param float $left
	 * @param float $width
	 * @param string $html
	 * @param array $config
	 */
	private function assertBox($left, $width, $html, $config = [])
	{
		$box = $this->firstBox($this->render($html, $config));

		$this->assertEqualsWithDelta($left, $box[0], 0.01, 'The left edge of the box');
		$this->assertEqualsWithDelta($width, $box[1], 0.01, 'The width of the box');
	}

	/**
	 * The left edge and width, in mm, of the first filled rectangle on the first page
	 *
	 * @param string $pdf
	 *
	 * @return float[]
	 */
	private function firstBox($pdf)
	{
		$pages = $this->pages($pdf);
		$this->assertSame(1, preg_match('/(-?[\d.]+) -?[\d.]+ (-?[\d.]+) -?[\d.]+ re\b/', $pages[0], $rect), 'A rectangle should be drawn');

		return [$rect[1] / Mpdf::SCALE, $rect[2] / Mpdf::SCALE];
	}
}
