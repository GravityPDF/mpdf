<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * A set height fixes a block's box in the standard CSS mode: a percentage is taken of the parent's height, shorter
 * content leaves space, taller content overflows, and overflow other than visible clips it to the padding box. Legacy
 * mode keeps the height as a minimum taken of the page area (#587)
 */
class BlockHeightTest extends TestCase
{

	use PageStreams;

	/**
	 * Six single-line blocks with no margins, about 56pt tall, for a box too short for them
	 */
	const SIX_LINES = '<div>line</div><div>line</div><div>line</div><div>line</div><div>line</div><div>line</div>';

	/**
	 * A percentage is taken of the parent's set height: 50% of 40mm is 20mm, and the parent stays 40mm
	 */
	public function testAPercentageHeightIsTakenOfTheParentsHeight()
	{
		$page = $this->page('<div style="height:40mm;background:#eee"><div style="height:50%;background:#f00">aa</div></div><div>next</div>');

		$fills = $this->rectangles($page, 're f');
		$this->assertCount(2, $fills);
		$this->assertEqualsWithDelta($this->pt(40), -$fills[0][3], 0.01, 'The outer box should be 40mm tall');
		$this->assertEqualsWithDelta($this->pt(20), -$fills[1][3], 0.01, 'The inner box should be 20mm tall');
		$this->assertEqualsWithDelta($this->pt(40), $this->y($page, 'aa') - $this->y($page, 'next'), 0.01);
	}

	/**
	 * With no height on the parent a percentage is auto, so the block is as tall as its content
	 */
	public function testAPercentageHeightWithNoParentHeightIsAuto()
	{
		$this->assertSame(
			$this->page('<div style="background:#f00">aa</div><div>next</div>'),
			$this->page('<div style="height:50%;background:#f00">aa</div><div>next</div>')
		);
	}

	/**
	 * A block inside a table cell is laid out as inline content, so its height does nothing, in either mode
	 *
	 * @dataProvider modes
	 */
	public function testAPercentageHeightInATableCellIsIgnored($mode)
	{
		$this->assertSame(
			$this->page('<table><tr><td><div>aa</div></td></tr></table><div>next</div>', $mode),
			$this->page('<table><tr><td><div style="height:50%">aa</div></td></tr></table><div>next</div>', $mode)
		);
	}

	/**
	 * Content shorter than the height leaves the box at its height, padding and borders. A hidden overflow clips to
	 * the padding box; a visible one clips nothing
	 */
	public function testShorterContentLeavesTheBoxAtItsHeight()
	{
		$box = '<div style="height:30mm;padding:2mm;border:1pt solid #000;overflow:%s">aa</div><div>next</div>';
		$page = $this->page(sprintf($box, 'hidden'));

		// The text sits 2mm and 1pt inside the top; next sits on the bottom edge
		$this->assertEqualsWithDelta($this->pt(32) + 1, $this->y($page, 'aa') - $this->y($page, 'next'), 0.01);
		$clips = $this->rectangles($page, 're W n');
		$this->assertCount(1, $clips);
		$this->assertEqualsWithDelta($this->pt(34), -$clips[0][3], 0.01, 'The clip should be the padding box');
		$this->assertEqualsWithDelta($this->pt(180) - 2, $clips[0][2], 0.01, 'The clip should be the padding box');

		$visible = $this->page(sprintf($box, 'visible'));
		$this->assertSame([], $this->rectangles($visible, 're W n'));
		$this->assertSame($this->y($page, 'next'), $this->y($visible, 'next'));
	}

	/**
	 * Content taller than the box overflows it when the overflow is visible: each line is drawn where it would be with
	 * no height, nothing is clipped, and what follows starts at the foot of the box
	 */
	public function testTallerContentOverflowsAVisibleBox()
	{
		$page = $this->page('<div style="height:10mm">' . self::SIX_LINES . '</div><div>next</div>');
		$unsized = $this->page('<div>' . self::SIX_LINES . '</div><div>next</div>');
		$lines = $this->ys($page, 'line');

		$this->assertSame($this->ys($unsized, 'line'), $lines);
		$this->assertSame([], $this->rectangles($page, 're W n'));
		$this->assertEqualsWithDelta($this->pt(10), $lines[0] - $this->y($page, 'next'), 0.01);
	}

	/**
	 * Content taller than the box is clipped to the padding box when the overflow is hidden, clip, auto or scroll: the
	 * clip opens before the first line and closes after the last, and the border is drawn after the clip closes
	 *
	 * @dataProvider clippingOverflows
	 */
	public function testTallerContentIsClippedByAHiddenBox($overflow)
	{
		$page = $this->page('<div style="height:10mm;border:1pt solid #000;overflow:' . $overflow . '">' . self::SIX_LINES . '</div><div>next</div>');
		$lines = $this->ys($page, 'line');

		$clips = $this->rectangles($page, 're W n');
		$this->assertCount(1, $clips);
		$this->assertEqualsWithDelta($this->pt(10), -$clips[0][3], 0.01);
		$this->assertCount(6, $lines, 'Every line is drawn; the clip hides the ones past the box');

		$firstLine = strpos($page, '(line) Tj');
		$close = strpos($page, "\nQ\n", strrpos($page, '(line) Tj'));
		$this->assertLessThan($firstLine, strpos($page, 're W n'), 'The clip should open before the first line');
		$this->assertNotFalse($close, 'The clip should close after the last line');
		$this->assertGreaterThan($close, strpos($page, '0.000 0.000 0.000 RG'), 'The border should be drawn outside the clip');
		$this->assertEqualsWithDelta($this->pt(10) + 1, $lines[0] - $this->y($page, 'next'), 0.01);
	}

	/**
	 * A block whose height does not fit in what is left of the page starts on the next page, where it takes its height
	 */
	public function testABlockThatDoesNotFitMovesToTheNextPage()
	{
		$pages = $this->pages($this->render($this->filler(26) . '<div style="height:40mm">aa</div><div>next</div>'));

		$this->assertCount(2, $pages);
		$this->assertOnlyOnPage(1, 1, '(aa)', $pages, 'the block');
		$this->assertOnlyOnPage(1, 1, '(next)', $pages, 'the text after it');
		$this->assertEqualsWithDelta($this->pt(40), $this->y($pages[1], 'aa') - $this->y($pages[1], 'next'), 0.01);
	}

	/**
	 * A block whose content runs past the foot of the page is laid out as before: the content continues on the next
	 * page, the box ends with it, and nothing is clipped
	 */
	public function testABlockWhoseContentRunsPastTheFootOfThePageEndsWithItsContent()
	{
		$pages = $this->pages($this->render('<div style="height:20mm;overflow:hidden">' . str_repeat('<p>line</p>', 40) . '</div><div>next</div>'));

		$this->assertCount(2, $pages);
		$second = $this->ys($pages[1], 'line');
		$this->assertCount(40, array_merge($this->ys($pages[0], 'line'), $second));
		$this->assertSame([], $this->rectangles($pages[0], 're W n'));
		$this->assertSame([], $this->rectangles($pages[1], 're W n'));
		$this->assertOnlyOnPage(1, 1, '(next)', $pages, 'the text after it');
		$this->assertLessThan(min($second), $this->y($pages[1], 'next'));
		foreach ($pages as $page) {
			$this->assertStringNotContainsString('___OVERFLOW', $page);
		}
	}

	/**
	 * A kept block whose box fits the page but whose content runs over it is laid out twice: the measuring pass leaves
	 * no clip on the page it left, and the block is clipped on the page it moved to
	 */
	public function testAKeptBlockThatMovesIsClippedOnThePageItMovesTo()
	{
		$pages = $this->pages($this->render($this->filler(27) . '<div style="page-break-inside:avoid;height:10mm;overflow:hidden;background:#eee">' . self::SIX_LINES . '</div><div>next</div>'));

		$this->assertCount(2, $pages);
		$this->assertSame([], $this->rectangles($pages[0], 're W n'));
		$this->assertStringNotContainsString('___OVERFLOW', $pages[0]);
		$this->assertOnlyOnPage(1, 6, '(line)', $pages, 'a line of the block');
		$this->assertCount(1, $this->rectangles($pages[1], 're W n'));
		$this->assertEqualsWithDelta($this->pt(10), $this->ys($pages[1], 'line')[0] - $this->y($pages[1], 'next'), 0.01);
	}

	/**
	 * A block with a set height inside one that clips: the inner background, which is painted at the top of the page,
	 * and its border are clipped to the outer box, and the outer box keeps its height
	 */
	public function testANestedBlockIsClippedToTheBoxAroundIt()
	{
		$page = $this->page('<div style="height:20mm;overflow:hidden;background:#eee"><div style="height:50mm;background:#f00;border:1pt solid #00f">aa</div></div><div>next</div>');

		$fills = $this->rectangles($page, 're f');
		$this->assertCount(2, $fills);
		$this->assertEqualsWithDelta($this->pt(20), -$fills[0][3], 0.01, 'The outer background is its box');
		$this->assertEqualsWithDelta($this->pt(50) + 2, -$fills[1][3], 0.01, 'The inner background is its own box');

		$outer = sprintf('%.3F %.3F %.3F %.3F re W n', $fills[0][0], $fills[0][1], $fills[0][2], $fills[0][3]);
		$this->assertSame(2, substr_count($page, $outer), 'The outer box clips the inner background and the content');
		$this->assertLessThan(strpos($page, '1.000 0.000 0.000 rg'), strpos($page, $outer), 'The inner background is painted inside the clip');
		$this->assertGreaterThan(strrpos($page, $outer), strpos($page, '0.000 0.000 1.000 RG'), 'The inner border is drawn inside the content clip');

		// After the last border stroke, its own Q and then the clip's close
		$lastStroke = strrpos($page, "\nS\n");
		$tail = substr($page, $lastStroke, strpos($page, '(next)') - $lastStroke);
		$this->assertSame(2, substr_count($tail, "\nQ\n"), 'The clip closes after the inner border, before the text that follows');
		$this->assertEqualsWithDelta($this->pt(20), $this->y($page, 'aa') + 1 - $this->y($page, 'next'), 0.01);
	}

	/**
	 * A zero height with hidden overflow hides the content and takes no room
	 */
	public function testAZeroHeightHidesTheContent()
	{
		$page = $this->page('<div style="height:0;overflow:hidden">hidden</div><div>next</div>');

		$clips = $this->rectangles($page, 're W n');
		$this->assertCount(1, $clips);
		$this->assertSame(0.0, $clips[0][3]);
		$this->assertSame($this->y($page, 'hidden'), $this->y($page, 'next'));
	}

	/**
	 * A clipped block in an HTML header is written to a buffer first; its clip is resolved there
	 */
	public function testAClippedBlockInAHeaderIsClipped()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetHTMLHeader('<div style="height:8mm;overflow:hidden;background:#ddd"><p>head</p><p>more</p></div>');
		$mpdf->WriteHTML('<p>body</p>');
		$page = $this->pages($this->output($mpdf))[0];

		$clips = $this->rectangles($page, 're W n');
		$this->assertCount(1, $clips);
		$this->assertEqualsWithDelta($this->pt(8), -$clips[0][3], 0.01);
		$this->assertStringNotContainsString('___OVERFLOW', $page);
		$this->assertLessThan(strpos($page, '(head) Tj'), strpos($page, 're W n'));
		$this->assertGreaterThan(strpos($page, '(more) Tj'), strpos($page, "\nQ\n", strpos($page, '(more) Tj')));
	}

	/**
	 * Legacy mode keeps the old layout: a percentage is taken of the page area, 265mm of an A4 page, whether or not the
	 * parent has a height, the height is a minimum, and overflow clips nothing
	 */
	public function testLegacyModeKeepsTheOldLayout()
	{
		$legacy = ['cssMode' => CssMode::LEGACY];

		$page = $this->page('<div style="height:40mm;background:#eee"><div style="height:50%;background:#f00">aa</div></div>', $legacy);
		$fills = $this->rectangles($page, 're f');
		$this->assertEqualsWithDelta($this->pt(132.5), -$fills[1][3], 0.01, 'The inner box is half the page area');
		$this->assertEqualsWithDelta($this->pt(132.5), -$fills[0][3], 0.01, 'The outer box grows to hold it');

		$page = $this->page('<div style="height:50%;background:#f00">aa</div>', $legacy);
		$this->assertEqualsWithDelta($this->pt(132.5), -$this->rectangles($page, 're f')[0][3], 0.01);

		$page = $this->page('<div style="height:10mm;overflow:hidden">' . self::SIX_LINES . '</div><div>next</div>', $legacy);
		$lines = $this->ys($page, 'line');
		$this->assertSame([], $this->rectangles($page, 're W n'));
		$this->assertCount(6, $lines);
		$this->assertLessThan(min($lines), $this->y($page, 'next'), 'next follows the sixth line');
	}

	/**
	 * @return array[]
	 */
	public function modes()
	{
		return [
			'standard' => [[]],
			'legacy' => [['cssMode' => CssMode::LEGACY]],
		];
	}

	/**
	 * The overflow values that clip: nothing scrolls on paper
	 *
	 * @return array[]
	 */
	public function clippingOverflows()
	{
		return [
			'hidden' => ['hidden'],
			'clip' => ['clip'],
			'auto' => ['auto'],
			'scroll' => ['scroll'],
		];
	}

	/**
	 * The content stream of the only page of the document
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string
	 */
	private function page($html, array $config = [])
	{
		$pages = $this->pages($this->render($html, $config));
		$this->assertCount(1, $pages);

		return $pages[0];
	}

	/**
	 * @param float $mm
	 *
	 * @return float In points
	 */
	private function pt($mm)
	{
		return $mm * Mpdf::SCALE;
	}

	/**
	 * The baselines a text is drawn at, in the order drawn
	 *
	 * @param string $stream
	 * @param string $text
	 *
	 * @return float[] In points from the foot of the page
	 */
	private function ys($stream, $text)
	{
		preg_match_all('/BT -?[\d.]+ (-?[\d.]+) Td\s+\(' . preg_quote($text, '/') . '\) Tj/', $stream, $matches);

		return array_map('floatval', $matches[1]);
	}

	/**
	 * The baseline of a text drawn once
	 *
	 * @param string $stream
	 * @param string $text
	 *
	 * @return float In points from the foot of the page
	 */
	private function y($stream, $text)
	{
		$ys = $this->ys($stream, $text);
		$this->assertCount(1, $ys, "'$text' should be drawn once");

		return $ys[0];
	}
}
