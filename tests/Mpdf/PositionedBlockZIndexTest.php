<?php

namespace Mpdf;

use Mpdf\CssMode;

/**
 * In standard cssMode a positioned block's background, borders and content are stacked together by its z-index,
 * then by the order of the document, with auto and 0 together. Below 0 the block goes under the page's content,
 * over the page's own background.
 */
class PositionedBlockZIndexTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The fill colours the tests use, by the operands that set them
	 */
	private static $colours = [
		'0.000 1.000 0.000' => 'green',
		'1.000 0.000 0.000' => 'red',
		'0.000 0.000 1.000' => 'blue',
		'1.000 1.000 0.000' => 'yellow',
		'0.800 0.800 1.000' => 'lilac',
	];

	/**
	 * Two 40mm squares that overlap: a green one marked x, then a red one marked y
	 *
	 * @param string $green The declarations the green square adds
	 * @param string $red The declarations the red square adds
	 *
	 * @return string
	 */
	private function squares($green, $red = '')
	{
		$square = 'position: absolute; width: 40mm; height: 40mm; ';

		return '<div style="' . $square . 'top: 50mm; left: 50mm; background: #0f0; ' . $green . '">x</div>'
			. '<div style="' . $square . 'top: 60mm; left: 60mm; background: #f00; ' . $red . '">y</div>';
	}

	/**
	 * What each page paints, in order: the fills by colour and the text the tests write
	 *
	 * @param string $html
	 * @param string $cssMode
	 *
	 * @return string[][]
	 */
	private function paintOrder($html, $cssMode = CssMode::STANDARD)
	{
		$order = [];
		foreach ($this->pageContents($this->render($html, ['cssMode' => $cssMode])) as $stream) {
			preg_match_all('/(\d\.\d{3} \d\.\d{3} \d\.\d{3}) rg|\(([^)]*)\) Tj/', $stream, $m, PREG_SET_ORDER);
			$page = [];
			foreach ($m as $match) {
				if (isset($match[2])) {
					$page[] = $match[2];
				} elseif (isset(self::$colours[$match[1]])) {
					$page[] = self::$colours[$match[1]];
				}
			}
			$order[] = $page;
		}

		return $order;
	}

	/**
	 * The green square's z-index decides whether it and its text go over the red square that follows it
	 *
	 * @dataProvider zIndexProvider
	 *
	 * @param string $zIndex
	 * @param string[] $expected
	 */
	public function testZIndexStacksTheWholeBlock($zIndex, $expected)
	{
		$this->assertSame([$expected], $this->paintOrder(
			'<p style="background: #ff0">In flow</p>' . $this->squares('z-index: ' . $zIndex)
		));
	}

	/**
	 * z-index values for the green square, with what the page paints
	 *
	 * @return array
	 */
	public function zIndexProvider()
	{
		return [
			'5 goes over the red square' => ['5', ['yellow', 'In flow', 'red', 'y', 'green', 'x']],
			'0 is in document order' => ['0', ['yellow', 'In flow', 'green', 'x', 'red', 'y']],
			'auto is in document order' => ['auto', ['yellow', 'In flow', 'green', 'x', 'red', 'y']],
			'-1 goes under the content' => ['-1', ['green', 'x', 'yellow', 'In flow', 'red', 'y']],
		];
	}

	/**
	 * Blocks with the same z-index are stacked in document order, and a higher one goes over a lower one written
	 * after it
	 */
	public function testEqualZIndexKeepsDocumentOrder()
	{
		$this->assertSame([['green', 'x', 'red', 'y']], $this->paintOrder($this->squares('z-index: 5', 'z-index: 5')));
		$this->assertSame([['red', 'y', 'green', 'x']], $this->paintOrder($this->squares('z-index: 6', 'z-index: 5')));
		$this->assertSame([['red', 'y', 'green', 'x']], $this->paintOrder($this->squares('z-index: -1', 'z-index: -2')));
	}

	/**
	 * Below 0 a block goes over the page's own background, the body's
	 */
	public function testNegativeZIndexGoesOverThePageBackground()
	{
		$this->assertSame([['lilac', 'green', 'x', 'yellow', 'In flow']], $this->paintOrder(
			'<body style="background: #ccf"><p style="background: #ff0">In flow</p>'
			. '<div style="position: absolute; top: 10mm; left: 10mm; width: 40mm; height: 40mm; background: #0f0; z-index: -1">x</div></body>'
		));
	}

	/**
	 * A block that is not positioned keeps the layer its z-index puts it in, an mPDF extension, and stacks among the
	 * positioned blocks by it
	 */
	public function testInFlowZIndexStacksWithPositionedBlocks()
	{
		$inFlow = '<div style="background: #00f; z-index: 2">In flow</div>';
		$square = '<div style="position: absolute; top: 10mm; left: 10mm; width: 40mm; height: 40mm; background: #0f0; z-index: %d">x</div>';

		$this->assertSame([['blue', 'In flow', 'green', 'x']], $this->paintOrder($inFlow . sprintf($square, 3)));
		$this->assertSame([['green', 'x', 'blue', 'In flow']], $this->paintOrder($inFlow . sprintf($square, 1)));
	}

	/**
	 * A z-index on the content of a positioned block opens no layer of its own, so the content stays with its block
	 */
	public function testContentStaysWithItsBlock()
	{
		$this->assertSame([['green', 'blue', 'x', 'inner', 'red', 'y']], $this->paintOrder(
			'<div style="position: absolute; top: 50mm; left: 50mm; width: 40mm; height: 40mm; background: #0f0">x'
			. '<div style="background: #00f; z-index: 9">inner</div></div>'
			. '<div style="position: absolute; top: 60mm; left: 60mm; width: 40mm; height: 40mm; background: #f00; z-index: 1">y</div>'
		));
	}

	/**
	 * Positioned blocks are stacked without optional content, which PDF/A-1 and PDF/X-1a refuse
	 */
	public function testStacksWithoutOptionalContent()
	{
		$pdf = $this->render($this->squares('z-index: 5'));

		$this->assertStringNotContainsString('/OCG', $pdf);
	}

	/**
	 * The legacy cssMode leaves the background in document order and puts the content alone in a layer
	 */
	public function testLegacyStacksTheContentAlone()
	{
		$this->assertSame([['green', 'red', 'y', 'x']], $this->paintOrder($this->squares('z-index: 5'), CssMode::LEGACY));
	}
}
