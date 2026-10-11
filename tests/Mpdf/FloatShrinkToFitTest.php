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
	 * A JPEG, embedded as it is
	 */
	const IMAGE = __DIR__ . '/../data/img/bayeux2.jpg';

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
	 * A float with a width is laid out once, and so is a float with no width whose text wraps: its measuring pass is
	 * already its layout at the whole width
	 *
	 * @dataProvider laidOutOnceProvider
	 *
	 * @param string $html
	 */
	public function testAFloatThatKeepsItsWidthIsLaidOutOnce($html)
	{
		$mpdf = new UnwindCountingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		$this->assertSame(0, $mpdf->unwinds);
		$this->assertCount(1, $mpdf->floatDivs);

		$mpdf->cleanup();
	}

	/**
	 * Floats whose first layout is their layout
	 *
	 * @return string[][]
	 */
	public function laidOutOnceProvider()
	{
		return [
			'a width' => ['<div style="float: left; width: 40mm">aa</div><p>bb</p>'],
			'no width, wrapping' => ['<div style="float: left">' . str_repeat('Lorem ipsum dolor sit amet. ', 20) . '</div><p>bb</p>'],
		];
	}

	/**
	 * A float with a forced page break inside it is measured on both pages, and is as wide as its longer word on
	 * each, with the paragraph after it beside it on the second
	 */
	public function testAFloatWithNoWidthAndAForcedPageBreakInsideIsAsWideAsItsContentOnBothPages()
	{
		$html = '<div style="float: left; background: #0f0">before<div style="page-break-before: always">after</div></div><p>bb</p>';
		$this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		});

		$pages = $this->pages($this->render($html));
		$this->assertCount(2, $pages);
		$this->assertTextCount(1, 'before', $pages[0]);
		$this->assertTextCount(1, 'after', $pages[1]);
		$this->assertTextCount(1, 'bb', $pages[1]);

		$first = $this->onlyBox($pages[0]);
		$second = $this->onlyBox($pages[1]);
		$this->assertGreaterThan(5, $first['w']);
		$this->assertLessThan(15, $first['w']);
		$this->assertEqualsWithDelta($first['w'], $second['w'], 0.01);
		$this->assertEqualsWithDelta(15 + $second['w'], $this->positionOf('bb', $pages[1])['x'], 0.01);
	}

	/**
	 * The float with a forced page break inside it is laid out again once, from its start tag on the first page
	 */
	public function testAFloatWithAForcedPageBreakInsideIsUnwoundOnce()
	{
		$mpdf = new UnwindCountingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<div style="float: left">before<div style="page-break-before: always">after</div></div><p>bb</p>');

		$this->assertSame(1, $mpdf->unwinds);
		$this->assertSame(2, $mpdf->page);

		$mpdf->cleanup();
	}

	/**
	 * A float whose text wraps keeps the whole width available, as in a browser, rather than the width of its
	 * longest wrapped line: shrink-to-fit only comes in under the space available when max-content does
	 */
	public function testAFloatWithNoWidthWhoseTextWrapsKeepsTheWholeWidth()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0">' . str_repeat('Lorem ipsum dolor sit amet. ', 20) . '<br>short</div><p>bb</p>');

		$this->assertEqualsWithDelta(180, $this->onlyBox($page)['w'], 0.01);
	}

	/**
	 * A word wider than the line puts min-content past the space available. mPDF keeps the float within its
	 * container and breaks the word, where a browser would let the float overflow
	 */
	public function testAFloatWithNoWidthHoldingAWordWiderThanTheLineKeepsTheWholeWidth()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0">' . str_repeat('a', 150) . '</div><p>bb</p>');

		$this->assertEqualsWithDelta(180, $this->onlyBox($page)['w'], 0.01);
	}

	/**
	 * A float holding only a floated image is as wide as the image, and the paragraph after it starts beside it
	 *
	 * @dataProvider sideProvider
	 *
	 * @param string $side
	 */
	public function testAFloatWithNoWidthHoldingAFloatedImageIsAsWideAsTheImage($side)
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0"><img src="' . self::IMAGE . '" style="float: ' . $side . '; width: 30mm"></div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(30, $float['w'], 0.1);
		$this->assertEqualsWithDelta(15 + $float['w'], $this->positionOf('bb', $page)['x'], 0.01);
	}

	/**
	 * Text beside an image floated right makes the float as wide as both together, so the text stays beside the image
	 */
	public function testAFloatWithNoWidthIsAsWideAsItsTextAndARightFloatedImageBesideIt()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0"><img src="' . self::IMAGE . '" style="float: right; width: 30mm">aa</div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertGreaterThan(32, $float['w']);
		$this->assertLessThan(36, $float['w']);
		$this->assertEqualsWithDelta(15, $this->positionOf('aa', $page)['x'], 0.01);
		$this->assertEqualsWithDelta(15 + $float['w'], $this->positionOf('bb', $page)['x'], 0.01);
	}

	/**
	 * A float holding only empty blocks has no content to be as wide as, so it is as narrow as its padding
	 */
	public function testAFloatWithNoWidthHoldingOnlyEmptyBlocksIsAsNarrowAsItsPadding()
	{
		$page = $this->firstPage('<div style="float: left; padding: 2mm; background: #0f0"><div></div><div></div></div><p>bb</p>');

		$float = $this->onlyBox($page);
		$this->assertEqualsWithDelta(4, $float['w'], 0.1);
		$this->assertEqualsWithDelta(15 + $float['w'], $this->positionOf('bb', $page)['x'], 0.01);
	}

	/**
	 * Floats of set width with a margin on the side away from where they float, which mPDF gives over to the space
	 * left beside them, each in a float to the other side, with where the inner float should start
	 *
	 * @return array[]
	 */
	public function nestedFloatProvider()
	{
		return [
			'right float with margin-left' => ['left', 'float: right; margin-left: 10mm', 25],
			'left float with margin-right' => ['right', 'float: left; margin-right: 10mm', 165],
		];
	}

	/**
	 * A float holding a float of set width is as wide as that float's margin box, and the float inside sits against
	 * the side it floats to
	 *
	 * @dataProvider nestedFloatProvider
	 *
	 * @param string $side
	 * @param string $inner
	 * @param float $innerX
	 */
	public function testAFloatWithNoWidthCountsTheMarginOfAFloatInsideIt($side, $inner, $innerX)
	{
		$page = $this->firstPage('<div style="float: ' . $side . '; background: #0f0"><div style="' . $inner . '; width: 20mm; background: #f00">x</div></div><p>bb</p>');

		$boxes = $this->filledBoxes($page);
		$this->assertCount(2, $boxes);
		$this->assertEqualsWithDelta(30, $boxes[0]['w'], 0.1);
		$this->assertEqualsWithDelta(20, $boxes[1]['w'], 0.01);
		$this->assertEqualsWithDelta($innerX, $boxes[1]['x'], 0.1);
	}

	/**
	 * Text inside a block of set width counts only through that block, so a block whose text wraps leaves the float
	 * as wide as the block, not the whole width
	 */
	public function testTextInsideABlockOfSetWidthDoesNotWidenTheFloat()
	{
		$page = $this->firstPage('<div style="float: left; background: #0f0"><div style="width: 40mm; background: #f00">' . str_repeat('word ', 30) . '</div></div><p>bb</p>');

		$boxes = $this->filledBoxes($page);
		$this->assertCount(2, $boxes);
		$this->assertEqualsWithDelta(40, $boxes[0]['w'], 0.1);
		$this->assertEqualsWithDelta(15 + $boxes[0]['w'], $this->positionOf('bb', $page)['x'], 0.01);
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
