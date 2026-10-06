<?php

namespace Mpdf;

/**
 * A float with no width is as wide as its content, as CSS shrink-to-fit sizes it, so that what follows flows beside
 * it. It is laid out once to measure the content and again at that width (FloatShrinkToFit). Legacy mode gives it
 * the whole width left on the line, as mPDF always did.
 *
 * Positions are read from the content streams: the page's left margin is at 15mm and its content 180mm wide.
 */
class FloatShrinkToFitTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

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
	 * The one rectangle a page fills
	 *
	 * @param string $page
	 *
	 * @return array Its x, top, w and h
	 */
	private function onlyBox($page)
	{
		$boxes = $this->filledBoxes($page);
		$this->assertCount(1, $boxes);

		return $boxes[0];
	}

	/**
	 * A left float holding one short word is as wide as that word, and the paragraph after it starts beside it on
	 * the same line
	 */
	public function testALeftFloatWithNoWidthIsAsWideAsItsWord()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0">aa</div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(15, $float['x'], 0.01);
		$this->assertGreaterThan(2, $float['w']);
		$this->assertLessThan(6, $float['w']);

		$aa = $this->positionOf('aa', $page);
		$bb = $this->positionOf('bb', $page);
		$this->assertEqualsWithDelta(15 + $float['w'], $bb['x'], 0.01);
		$this->assertEqualsWithDelta($aa['y'], $bb['y'], 0.01);
	}

	/**
	 * A right float holding one short word is as wide as that word and sits against the right margin, with the
	 * paragraph after it starting at the left margin on the same line
	 */
	public function testARightFloatWithNoWidthIsAsWideAsItsWord()
	{
		$page = $this->firstPage('<div style="float: right; background: #0f0">aa</div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(195, $float['x'] + $float['w'], 0.01);
		$this->assertGreaterThan(2, $float['w']);
		$this->assertLessThan(6, $float['w']);

		$aa = $this->positionOf('aa', $page);
		$bb = $this->positionOf('bb', $page);
		$this->assertEqualsWithDelta($float['x'], $aa['x'], 0.01);
		$this->assertEqualsWithDelta(15, $bb['x'], 0.01);
		$this->assertEqualsWithDelta($aa['y'], $bb['y'], 0.01);
	}

	/**
	 * The sides a float can take
	 *
	 * @return string[][]
	 */
	public function sideProvider()
	{
		return [
			'left' => ['left'],
			'right' => ['right'],
		];
	}

	/**
	 * A float holding a paragraph that wraps wants more than the line has, so it keeps the whole width and the
	 * paragraph after it goes below
	 *
	 * @dataProvider sideProvider
	 *
	 * @param string $side
	 */
	public function testAFloatWithNoWidthHoldingAParagraphTakesTheWholeWidth($side)
	{
		$page = $this->firstPage('<div style="float: ' . $side . '; background: #0f0">' . str_repeat('Lorem ipsum dolor sit amet. ', 20) . '</div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(15, $float['x'], 0.01);
		$this->assertEqualsWithDelta(180, $float['w'], 0.01);

		$bb = $this->positionOf('bb', $page);
		$this->assertEqualsWithDelta(15, $bb['x'], 0.01);
		$this->assertGreaterThan($float['top'] + $float['h'], $bb['y']);
	}

	/**
	 * A float with no width placed beside a float with one is as wide as its words, and the paragraph after both
	 * starts beside the second
	 */
	public function testAFloatWithNoWidthSitsBesideAFloatWithAWidth()
	{
		$page = $this->firstPage('<div style="float: left; width: 30mm; background: #f00">left</div><div style="float: left; background: #0f0">aa bb</div><p>cc</p>');

		$boxes = $this->filledBoxes($page);
		$this->assertCount(2, $boxes);
		list($second, $first) = $boxes;
		$this->assertEqualsWithDelta(30, $first['w'], 0.01);
		$this->assertEqualsWithDelta(45, $second['x'], 0.01);
		$this->assertGreaterThan(6, $second['w']);
		$this->assertLessThan(15, $second['w']);

		$cc = $this->positionOf('cc', $page);
		$this->assertEqualsWithDelta(45 + $second['w'], $cc['x'], 0.01);
		$this->assertEqualsWithDelta($this->positionOf('left', $page)['y'], $cc['y'], 0.01);
	}

	/**
	 * A block of set width inside the float is what the float is sized to, not the word it holds
	 */
	public function testAFloatWithNoWidthIsAsWideAsABlockOfSetWidthInsideIt()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0"><div style="width: 50mm; background: #f00">x</div></div><p>bb</p>');

		$boxes = $this->filledBoxes($page);
		$this->assertCount(2, $boxes);
		$this->assertEqualsWithDelta(50, $boxes[0]['w'], 0.1);
		$this->assertEqualsWithDelta(50, $boxes[1]['w'], 0.01);
		$this->assertEqualsWithDelta(15 + $boxes[0]['w'], $this->positionOf('bb', $page)['x'], 0.01);
	}

	/**
	 * A table inside the float is what the float is sized to
	 */
	public function testAFloatWithNoWidthIsAsWideAsTheTableInsideIt()
	{
		$page = $this->firstPage('<div style="float: right; background: #0f0"><table><tr><td>cell one</td><td>cell two</td></tr></table></div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(195, $float['x'] + $float['w'], 0.01);
		$this->assertGreaterThan(20, $float['w']);
		$this->assertLessThan(40, $float['w']);

		$bb = $this->positionOf('bb', $page);
		$this->assertEqualsWithDelta(15, $bb['x'], 0.01);
		$this->assertLessThan($float['top'] + $float['h'], $bb['y']);
	}

	/**
	 * A float that runs onto the next page is measured across both pages and is as wide as its word on each, with
	 * the paragraph after it beside it on the page it starts on
	 */
	public function testAFloatWithNoWidthCrossingAPageBreakIsAsWideAsItsWordOnBothPages()
	{
		$pages = $this->pages($this->render(str_repeat('<p>Filler</p>', 50) . '<div style="float: left; background: #0f0">' . str_repeat('word<br>', 20) . '</div><p>bb</p>'));
		$this->assertCount(3, $pages);

		$this->assertCount(0, $this->filledBoxes($pages[0]));
		$second = $this->onlyBox($pages[1]);
		$third = $this->onlyBox($pages[2]);
		$this->assertGreaterThan(5, $second['w']);
		$this->assertLessThan(12, $second['w']);
		$this->assertEqualsWithDelta($second['w'], $third['w'], 0.01);
		$this->assertEqualsWithDelta(16, $third['top'], 0.01);

		$bb = $this->positionOf('bb', $pages[1]);
		$this->assertEqualsWithDelta(15 + $second['w'], $bb['x'], 0.01);
		$this->assertGreaterThan($second['top'], $bb['y']);
		$this->assertLessThan($second['top'] + $second['h'], $bb['y']);
	}

	/**
	 * The measuring pass is unwound whole: the document is put back once, and the float is registered once
	 */
	public function testTheMeasuringPassIsUnwoundOnce()
	{
		$mpdf = new UnwindCountingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<div style="float: left">aa</div><p>bb</p>');

		$this->assertSame(1, $mpdf->unwinds);
		$this->assertCount(1, $mpdf->floatDivs);
		$this->assertSame(1, $mpdf->page);

		$mpdf->cleanup();
	}

	/**
	 * A float with a width is laid out once
	 */
	public function testAFloatWithAWidthIsNotMeasured()
	{
		$mpdf = new UnwindCountingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<div style="float: left; width: 40mm">aa</div><p>bb</p>');

		$this->assertSame(0, $mpdf->unwinds);

		$mpdf->cleanup();
	}

	/**
	 * A forced page break closes the open blocks without any tokens to come back to, so a float with no width it
	 * closes keeps the width it was measured at, and nothing is lost or warned about
	 */
	public function testAForcedPageBreakInsideAFloatWithNoWidthLeavesItAtTheWholeWidth()
	{
		$pages = $this->pages($this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<div style="float: left; background: #0f0">before<div style="page-break-before: always">after</div></div><p>bb</p>');
		}));

		$this->assertCount(2, $pages);
		$this->assertTextCount(1, 'before', $pages[0]);
		$this->assertTextCount(1, 'after', $pages[1]);
		$this->assertTextCount(1, 'bb', $pages[1]);
		$this->assertEqualsWithDelta(180, $this->onlyBox($pages[1])['w'], 0.01);
	}

	/**
	 * Legacy mode gives a float with no width the whole width left on the line, as before, so the paragraph after
	 * it goes below
	 */
	public function testLegacyModeGivesAFloatWithNoWidthTheWholeWidth()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0">aa</div><p>bb</p>', ['cssMode' => CssMode::LEGACY]);

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(180, $float['w'], 0.01);

		$bb = $this->positionOf('bb', $page);
		$this->assertEqualsWithDelta(15, $bb['x'], 0.01);
		$this->assertGreaterThan($float['top'] + $float['h'], $bb['y']);
	}
}
