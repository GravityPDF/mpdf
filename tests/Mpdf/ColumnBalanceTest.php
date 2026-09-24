<?php

namespace Mpdf;

/**
 * Balancing columns at their end keeps everything from the last break down in the last column
 */
class ColumnBalanceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * Blocks with a background whose last child is a block, so the background's closing rectangle starts at the
	 * foot of the content
	 *
	 * @return string[][]
	 */
	public function blocksEndingInAChild()
	{
		$text = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 4);

		return [
			'text then a div' => ['<div style="background-color: #ddd">' . $text . '<div>Last line</div></div>'],
			'paragraphs then a div' => [
				'<div style="background-color: #ddd">'
				. str_repeat('<p style="margin: 0; text-indent: 5mm">' . $text . '</p>', 2)
				. '<div>Last line</div></div>',
			],
		];
	}

	/**
	 * @dataProvider blocksEndingInAChild
	 *
	 * @param string $block
	 */
	public function testBalancingRaisesNoWarning($block)
	{
		$this->assertDrawsSilently(function (Mpdf $mpdf) use ($block) {
			$mpdf->WriteHTML($this->balanced($block));
		});
	}

	/**
	 * @dataProvider blocksEndingInAChild
	 *
	 * @param string $block
	 */
	public function testNoRectangleLandsRightOfTheLastColumn($block)
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML($this->balanced($block));
		$right = ($mpdf->w - $mpdf->rMargin) * Mpdf::SCALE;
		$pages = $this->pages($this->output($mpdf));

		preg_match_all('/(-?\d+\.\d+) -?\d+\.\d+ (-?\d+\.\d+) -?\d+\.\d+ re/', $pages[0], $rectangles, PREG_SET_ORDER);

		$this->assertNotEmpty($rectangles);
		foreach ($rectangles as $rectangle) {
			$this->assertLessThanOrEqual($right + 0.01, $rectangle[1] + $rectangle[2], $rectangle[0]);
		}
	}

	/**
	 * $block in two columns that are balanced when they close
	 *
	 * @param string $block
	 *
	 * @return string
	 */
	private function balanced($block)
	{
		return '<columns column-count="2" />' . $block . '<columns column-count="0" />';
	}
}
