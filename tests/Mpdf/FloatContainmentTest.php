<?php

namespace Mpdf;

/**
 * Only a block that starts a block formatting context contains its floats, as in CSS: the body, a float, a
 * positioned block, and a block with `overflow` other than visible or `display: flow-root`. A float sticks out of
 * any other parent, and the blocks after that parent flow beside it. Legacy mode has every ancestor grow to contain
 * the float, as mPDF always did.
 *
 * Positions are read from the content streams: the page's left margin is at 15mm and its content 180mm wide. The
 * float in each document is 40mm wide and 30mm tall, so a block beside it starts at 55mm.
 */
class FloatContainmentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	const FLOAT = '<div style="float: left; width: 40mm; height: 30mm">aa</div>';

	/**
	 * The first page of a document
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string Its content stream
	 */
	private function firstPage($html, $config = [])
	{
		$pages = $this->pages($this->render($html, $config));
		$this->assertCount(1, $pages);

		return $pages[0];
	}

	/**
	 * The rectangle filled across the whole content width: the parent's background
	 *
	 * @param string $page
	 *
	 * @return array Its x, top, w and h
	 */
	private function parentBox($page)
	{
		foreach ($this->filledBoxes($page) as $box) {
			if (abs($box['w'] - 180) < 0.01) {
				return $box;
			}
		}

		$this->fail('The parent should paint its background');
	}

	/**
	 * $text is drawn on the line the float starts on, beside the float
	 *
	 * @param string $text
	 * @param string $page
	 */
	private function assertBesideTheFloat($text, $page)
	{
		$aa = $this->positionOf('aa', $page);
		$beside = $this->positionOf($text, $page);
		$this->assertEqualsWithDelta(55, $beside['x'], 0.01, "'$text' should start beside the float");
		$this->assertEqualsWithDelta($aa['y'], $beside['y'], 0.01, "'$text' should be on the float's first line");
	}

	/**
	 * $text is drawn at the left margin below the float
	 *
	 * @param string $text
	 * @param string $page
	 */
	private function assertBelowTheFloat($text, $page)
	{
		$aa = $this->positionOf('aa', $page);
		$below = $this->positionOf($text, $page);
		$this->assertEqualsWithDelta(15, $below['x'], 0.01, "'$text' should start at the left margin");
		$this->assertGreaterThan($aa['y'] + 30, $below['y'], "'$text' should be below the float");
	}

	/**
	 * A parent in normal flow does not contain its float: it is as tall as its own content, nothing here, and the
	 * paragraph after it flows beside the float
	 */
	public function testAParentDoesNotContainItsFloat()
	{
		$page = $this->firstPage('<div style="background: #0f0">' . self::FLOAT . '</div><p>zz</p>');

		$this->assertEqualsWithDelta(0, $this->parentBox($page)['h'], 0.01);
		$this->assertBesideTheFloat('zz', $page);
	}

	/**
	 * The ways a block starts a block formatting context
	 *
	 * @return string[][]
	 */
	public function containingStyleProvider()
	{
		return [
			'overflow: hidden' => ['overflow: hidden'],
			'overflow: auto' => ['overflow: auto'],
			'display: flow-root' => ['display: flow-root'],
		];
	}

	/**
	 * A parent that starts a block formatting context contains its float: it grows to the float's bottom, and the
	 * paragraph after it goes below
	 *
	 * @dataProvider containingStyleProvider
	 *
	 * @param string $style
	 */
	public function testAParentThatStartsABlockFormattingContextContainsItsFloat($style)
	{
		$page = $this->firstPage('<div style="background: #0f0; ' . $style . '">' . self::FLOAT . '</div><p>zz</p>');

		$this->assertEqualsWithDelta(30, $this->parentBox($page)['h'], 0.01);
		$this->assertBelowTheFloat('zz', $page);
	}

	/**
	 * A float contains the floats inside it
	 */
	public function testAFloatContainsItsFloat()
	{
		$page = $this->firstPage('<div style="float: left; width: 80mm; background: #0f0">' . self::FLOAT . '</div><p>zz</p>');

		$outer = $this->filledBoxes($page)[0];
		$this->assertEqualsWithDelta(80, $outer['w'], 0.01);
		$this->assertEqualsWithDelta(30, $outer['h'], 0.01);

		$zz = $this->positionOf('zz', $page);
		$this->assertEqualsWithDelta(95, $zz['x'], 0.01);
		$this->assertEqualsWithDelta($this->positionOf('aa', $page)['y'], $zz['y'], 0.01);
	}

	/**
	 * A positioned block contains its float
	 */
	public function testAPositionedBlockContainsItsFloat()
	{
		$page = $this->firstPage('<div style="position: absolute; top: 50mm; left: 20mm; width: 100mm; background: #ff0">' . self::FLOAT . '</div><p>body</p>');

		$positioned = null;
		foreach ($this->filledBoxes($page) as $box) {
			if (abs($box['w'] - 100) < 0.01) {
				$positioned = $box;
			}
		}
		$this->assertNotNull($positioned, 'The positioned block should paint its background');
		$this->assertEqualsWithDelta(30, $positioned['h'], 0.01);
	}

	/**
	 * A `clear` inside the parent moves below the float, so the parent ends below it as a clearfix does
	 */
	public function testAClearInsideTheParentMakesItContainItsFloat()
	{
		$page = $this->firstPage('<div style="background: #0f0">' . self::FLOAT . '<div style="clear: both"></div></div><p>zz</p>');

		$this->assertEqualsWithDelta(30, $this->parentBox($page)['h'], 0.01);
		$this->assertBelowTheFloat('zz', $page);
	}

	/**
	 * The float counts as a sibling of the parent it sticks out of: a block after the parent is pushed beside it, and
	 * the blocks inside that block are not pushed again
	 */
	public function testABlockAfterTheParentAndItsChildFlowBesideTheFloatOnce()
	{
		$page = $this->firstPage('<div>' . self::FLOAT . '</div><div style="background: #f00"><p>zz</p></div>');

		$this->assertBesideTheFloat('zz', $page);
	}

	/**
	 * A block that starts a block formatting context is a new context for `clear` too: a `clear` inside it does not
	 * move below a float outside it
	 */
	public function testAClearInsideABlockFormattingContextIgnoresFloatsOutsideIt()
	{
		$page = $this->firstPage(self::FLOAT . '<div style="overflow: hidden"><div style="clear: both">in</div></div>');

		$this->assertBesideTheFloat('in', $page);
	}

	/**
	 * Text after the parent flows beside the float on to the page the float ends on
	 */
	public function testTextAfterTheParentFlowsBesideItsFloatAcrossAPageBreak()
	{
		$pages = $this->pages($this->render(str_repeat('<p>Filler</p>', 50) . '<div><div style="float: left; width: 40mm; background: #0f0">' . str_repeat('word<br>', 20) . '</div></div><p>' . str_repeat('Lorem ipsum dolor sit amet. ', 12) . '</p>'));
		$this->assertCount(3, $pages);

		$float = $this->filledBoxes($pages[1])[0];
		$lorem = $this->positionOf('Lorem', $pages[1]);
		$this->assertEqualsWithDelta(55, $lorem['x'], 0.01);
		$this->assertGreaterThan($float['top'], $lorem['y']);
		$this->assertLessThan($float['top'] + $float['h'], $lorem['y']);
		$this->assertEqualsWithDelta(16, $this->filledBoxes($pages[2])[0]['top'], 0.01);
	}

	/**
	 * Legacy mode has every ancestor contain the float, as before, so the parent grows and the paragraph goes below
	 */
	public function testLegacyModeHasEveryParentContainItsFloat()
	{
		$page = $this->firstPage('<div style="background: #0f0">' . self::FLOAT . '</div><p>zz</p>', ['cssMode' => CssMode::LEGACY]);

		$this->assertEqualsWithDelta(30, $this->parentBox($page)['h'], 0.01);
		$this->assertBelowTheFloat('zz', $page);
	}
}
