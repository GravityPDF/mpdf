<?php

namespace Mpdf;

/**
 * A positioned block's top and bottom margins each count once when its top, height or bottom is solved from the
 * others (CSS 2.1 §10.6.4). The page is A4 with 16mm top and bottom margins: an absolute block is placed on the
 * whole 297mm page, a fixed one on the page area from 16mm to 281mm.
 */
class PositionedBlockVerticalMarginsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A block of each kind, the top edge and height in mm of the box a browser draws, and the config to draw it with.
	 * A margin counted twice is a plain bug, so legacy mode is fixed too.
	 *
	 * @return array[]
	 */
	public function positionedBlocks()
	{
		return [
			'fixed, top auto' => ['position: fixed; bottom: 10mm; height: 30mm; margin-top: 20mm; margin-bottom: 0', 241, 30],
			'absolute, top auto' => ['position: absolute; bottom: 10mm; height: 30mm; margin-top: 20mm; margin-bottom: 0', 257, 30],
			'fixed, top auto, both margins' => ['position: fixed; bottom: 10mm; height: 30mm; margin-top: 20mm; margin-bottom: 5mm', 236, 30],
			'fixed, bottom auto' => ['position: fixed; top: 20mm; height: 30mm; margin-top: 20mm; margin-bottom: 5mm', 56, 30],
			'fixed, top, height and bottom all set' => ['position: fixed; top: 20mm; height: 30mm; bottom: 30mm; margin-top: 20mm; margin-bottom: 5mm', 56, 30],
			'fixed, height auto' => ['position: fixed; top: 20mm; bottom: 30mm; margin-top: 20mm; margin-bottom: 5mm', 56, 190],
			'absolute, height auto' => ['position: absolute; top: 20mm; bottom: 30mm; margin-top: 20mm; margin-bottom: 5mm', 40, 222],
			'fixed, top auto, legacy mode' => ['position: fixed; bottom: 10mm; height: 30mm; margin-top: 20mm; margin-bottom: 0', 241, 30, ['cssMode' => CssMode::LEGACY]],
		];
	}

	/**
	 * The box is as tall as its height, or as the space its top, bottom and two margins leave, and sits where they put it
	 *
	 * @dataProvider positionedBlocks
	 *
	 * @param string $style
	 * @param float $top
	 * @param float $height
	 * @param array $config
	 */
	public function testEachMarginCountsOnce($style, $top, $height, $config = [])
	{
		$this->assertBox($top, $height, $style, $config);
	}

	/**
	 * A block with neither top nor bottom keeps its height whatever its margins
	 */
	public function testABlockAtItsStaticPositionKeepsItsHeight()
	{
		$this->assertBox(null, 30, 'position: fixed; height: 30mm; margin-top: 20mm; margin-bottom: 5mm');
	}

	/**
	 * Asserts that the first filled rectangle on the first page, the block's background, has its top edge at $top
	 * (unless null) and is $height tall, in mm from the top of the page
	 *
	 * @param float|null $top
	 * @param float $height
	 * @param string $style
	 * @param array $config
	 */
	private function assertBox($top, $height, $style, $config = [])
	{
		$pdf = $this->render('<div style="' . $style . '; left: 10mm; width: 50mm; background-color: #ccc">aa</div>', $config);
		$pages = $this->pages($pdf);
		$this->assertSame(1, preg_match('/-?[\d.]+ (-?[\d.]+) -?[\d.]+ (-?[\d.]+) re\b/', $pages[0], $rect), 'A rectangle should be drawn');

		if ($top !== null) {
			$this->assertEqualsWithDelta($top, 297 - $rect[1] / Mpdf::SCALE, 0.01, 'The top edge of the box');
		}
		$this->assertEqualsWithDelta($height, -$rect[2] / Mpdf::SCALE, 0.01, 'The height of the box');
	}
}
