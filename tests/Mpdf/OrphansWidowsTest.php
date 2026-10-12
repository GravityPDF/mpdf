<?php

namespace Mpdf;

/**
 * orphans and widows keep a paragraph from leaving fewer lines than they ask at the foot of a page, or carrying fewer
 * onto the next. Each paragraph here is set 25mm wide, so each "LineN xxxxxx" is a line of its own, after one-line
 * filler paragraphs; an A4 page holds 53 lines, so after n fillers 53 - n lines of the paragraph fit page 1.
 */
class OrphansWidowsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The rows of the issue: a three-line paragraph that leaves one line behind or carries one over moves whole, with
	 * both at 2, and with both at 3
	 *
	 * @dataProvider threeLineProvider
	 *
	 * @param int $fillers
	 * @param string $style
	 */
	public function testAThreeLineParagraphMovesWholeRatherThanLeaveALineAlone($fillers, $style)
	{
		$mpdf = $this->write($this->fillers($fillers) . $this->paragraph(3, $style));

		$this->assertSame([2, 2, 2], $this->linePages($mpdf));
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * Two lines on page 1 and the last alone on page 2, or the first alone on page 1, with both at 2, set or by default, or at 3
	 *
	 * @return array[]
	 */
	public function threeLineProvider()
	{
		return [
			'last line alone, 2' => [51, 'orphans: 2; widows: 2'],
			'last line alone, neither set' => [51, ''],
			'first line alone, 2' => [52, 'orphans: 2; widows: 2'],
			'first line alone, 3' => [52, 'orphans: 3; widows: 3'],
		];
	}

	/**
	 * Both at 1 break the paragraph where the page ends, and it is laid out once
	 */
	public function testBothAtOneBreakWhereThePageEnds()
	{
		$mpdf = $this->write($this->fillers(51) . $this->paragraph(3, 'orphans: 1; widows: 1'));

		$this->assertSame([1, 1, 2], $this->linePages($mpdf));
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * The legacy CSS mode reads neither property
	 */
	public function testLegacyModeReadsNeither()
	{
		$mpdf = $this->write($this->fillers(51) . $this->paragraph(3, 'orphans: 3; widows: 3'), ['cssMode' => CssMode::LEGACY]);

		$this->assertSame([1, 1, 2], $this->linePages($mpdf));
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * A paragraph whose break already leaves enough lines on each side is laid out once
	 */
	public function testABreakLeavingEnoughLinesOnEachSideStays()
	{
		$mpdf = $this->write($this->fillers(50) . $this->paragraph(6, 'orphans: 3; widows: 3'));

		$this->assertSame([1, 1, 1, 2, 2, 2], $this->linePages($mpdf));
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * With both at 3, a six-line paragraph that would carry one or two lines over carries three, leaving three behind
	 *
	 * @dataProvider sixLineProvider
	 *
	 * @param int $fillers
	 */
	public function testWidowsCarryLinesOverWhileEnoughStayBehind($fillers)
	{
		$mpdf = $this->write($this->fillers($fillers) . $this->paragraph(6, 'orphans: 3; widows: 3'));

		$this->assertSame([1, 1, 1, 2, 2, 2], $this->linePages($mpdf));
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * Five lines and then one on page 1, or four and then two
	 *
	 * @return array[]
	 */
	public function sixLineProvider()
	{
		return [
			'one carried over' => [48],
			'two carried over' => [49],
		];
	}

	/**
	 * With both at 3, a six-line paragraph that would leave two lines behind moves whole
	 */
	public function testOrphansMoveAParagraphLeavingTooFewLinesBehind()
	{
		$mpdf = $this->write($this->fillers(51) . $this->paragraph(6, 'orphans: 3; widows: 3'));

		$this->assertSame([2, 2, 2, 2, 2, 2], $this->linePages($mpdf));
	}

	/**
	 * With both at 3, a four-line paragraph moves whole where there is no break leaving three lines on each side: three
	 * and one, or two and two
	 *
	 * @dataProvider fourLineProvider
	 *
	 * @param int $fillers
	 */
	public function testAFourLineParagraphMovesWholeWhereNoBreakLeavesEnoughOnBothSides($fillers)
	{
		$mpdf = $this->write($this->fillers($fillers) . $this->paragraph(4, 'orphans: 3; widows: 3'));

		$this->assertSame([2, 2, 2, 2], $this->linePages($mpdf));
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * Three lines and then one on page 1, or two and two
	 *
	 * @return array[]
	 */
	public function fourLineProvider()
	{
		return [
			'three and one' => [50],
			'two and two' => [51],
		];
	}

	/**
	 * widows may leave a single line behind where orphans is 1
	 */
	public function testWidowsMayLeaveOneLineBehindWhereOrphansIsOne()
	{
		$mpdf = $this->write($this->fillers(51) . $this->paragraph(4, 'orphans: 1; widows: 3'));

		$this->assertSame([1, 2, 2, 2], $this->linePages($mpdf));
	}

	/**
	 * Both are inherited: a paragraph keeps to what the block around it sets
	 */
	public function testAParagraphInheritsBothFromItsBlock()
	{
		$mpdf = $this->write('<div style="orphans: 2; widows: 2">' . $this->fillers(51) . $this->paragraph(3) . '</div>');

		$this->assertSame([2, 2, 2], $this->linePages($mpdf));
	}

	/**
	 * A value that is not a positive integer is ignored, leaving both at 2
	 *
	 * @dataProvider invalidProvider
	 *
	 * @param string $value
	 */
	public function testAValueThatIsNotAPositiveIntegerIsIgnored($value)
	{
		$mpdf = $this->write($this->fillers(51) . $this->paragraph(3, 'orphans: ' . $value . '; widows: ' . $value));

		$this->assertSame([2, 2, 2], $this->linePages($mpdf));
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * Zero, a negative number, a fraction and a word
	 *
	 * @return array[]
	 */
	public function invalidProvider()
	{
		return [['0'], ['-2'], ['2.5'], ['two']];
	}

	/**
	 * A paragraph that runs onto a page of its own keeps its first break clear of orphans and its last of widows, and is
	 * laid out no more than three times: its first line alone at the foot of page 1 moves it to page 2, and its last
	 * line alone on page 3 then takes two more lines with it
	 */
	public function testAParagraphIsLaidOutAtMostThreeTimes()
	{
		$mpdf = $this->write($this->fillers(52) . $this->paragraph(54, 'orphans: 3; widows: 3'));

		$this->assertSame(array_merge(array_fill(0, 51, 2), array_fill(0, 3, 3)), $this->linePages($mpdf));
		$this->assertSame(2, $mpdf->unwinds);
	}

	/**
	 * A paragraph that fills the page it starts at the top of is not moved off it, which would leave the page empty
	 */
	public function testAParagraphAtTheTopOfAPageStaysThere()
	{
		$mpdf = $this->write($this->paragraph(54, 'orphans: 60; widows: 1'));

		$this->assertSame(array_merge(array_fill(0, 53, 1), [2]), $this->linePages($mpdf));
	}

	/**
	 * A kept block too tall for a page is laid out again where it is, and the paragraph in it then keeps to its widows
	 */
	public function testAParagraphInAKeptBlockTooTallForAPageKeepsToItsWidows()
	{
		$mpdf = $this->write('<div style="page-break-inside: avoid">' . $this->paragraph(55, 'widows: 3') . '</div>');

		$this->assertSame(array_merge(array_fill(0, 52, 1), array_fill(0, 3, 2)), $this->linePages($mpdf));
		$this->assertSame(2, $mpdf->unwinds, 'One unwind for the kept block, one for the widows');
	}

	/**
	 * A kept block that fits a fresh page moves whole, and the paragraph in it is not measured on its own
	 */
	public function testAParagraphInAKeptBlockThatMovesIsLeftToIt()
	{
		$mpdf = $this->write($this->fillers(51) . '<div style="page-break-inside: avoid">' . $this->paragraph(3, 'orphans: 2; widows: 2') . '</div>');

		$this->assertSame([2, 2, 2], $this->linePages($mpdf));
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * The text of a block before a block nested in it, and before a table, keeps to the block's widows
	 *
	 * @dataProvider nestedProvider
	 *
	 * @param string $after
	 */
	public function testTheTextBeforeANestedBlockOrTableKeepsToItsWidows($after)
	{
		$mpdf = $this->write($this->fillers(51) . '<div style="margin: 0; width: 25mm; orphans: 1; widows: 2">' . $this->lines(3) . $after . '</div>');

		$this->assertSame([1, 2, 2], array_slice($this->linePages($mpdf), 0, 3));
	}

	/**
	 * A nested block and a table
	 *
	 * @return array[]
	 */
	public function nestedProvider()
	{
		return [
			'block' => ['<div>After</div>'],
			'table' => ['<table><tr><td>After</td></tr></table>'],
		];
	}

	/**
	 * A paragraph beside a float is laid out again with the float where it is
	 */
	public function testAParagraphBesideAFloatKeepsToItsWidows()
	{
		$float = '<div style="float: left; width: 20mm; height: 20mm"></div>';
		$mpdf = $this->write($this->fillers(47) . $float . '<p style="margin: 0; widows: 3">' . $this->words(100) . '</p>');

		$boxes = $this->lineBoxes($mpdf, 'Word');
		$this->assertSame([1, 1, 1, 1, 2, 2, 2], $this->pagesOf($boxes));
		$this->assertSame(1, $mpdf->unwinds);
		foreach (array_slice($boxes, 0, 4) as $box) {
			$this->assertEqualsWithDelta(35.0, $box[1], 0.01, 'A line on page 1 starts beside the float');
		}
	}

	/**
	 * Lines carried onto a page whose page area is narrower are set in it
	 */
	public function testLinesCarriedOntoANarrowerPageAreSetInIt()
	{
		$css = '<style>@page { margin-left: 20mm; margin-right: 20mm; } @page :left { margin-left: 60mm; margin-right: 40mm; }</style>';
		$mpdf = $this->write($css . $this->fillers(49) . '<p style="margin: 0; widows: 3">' . $this->words(80) . '</p>', ['mirrorMargins' => true]);

		$boxes = $this->lineBoxes($mpdf, 'Word');
		$this->assertSame([1, 1, 1, 2, 2, 2], $this->pagesOf($boxes));
		foreach (array_slice($boxes, 3) as $box) {
			$this->assertGreaterThanOrEqual(60.0, $box[1]);
			$this->assertLessThanOrEqual(170.0, $box[2]);
		}
	}

	/**
	 * A bookmark in a paragraph that moves is made once, on the page the paragraph moves to
	 */
	public function testABookmarkInAMovedParagraphIsMadeOnceOnItsNewPage()
	{
		$mpdf = $this->write($this->fillers(51) . '<p style="margin: 0; width: 25mm; orphans: 2; widows: 2"><bookmark content="Moved" />' . $this->lines(3) . '</p>');

		$this->assertCount(1, $mpdf->BMoutlines);
		$this->assertSame(2, $mpdf->BMoutlines[0]['p']);
	}

	/**
	 * Neither applies in a table cell or in columns, which lay out as without them
	 *
	 * @dataProvider unsupportedProvider
	 *
	 * @param string $before
	 * @param string $after
	 */
	public function testNeitherAppliesInATableCellOrColumns($before, $after)
	{
		$html = function ($style) use ($before, $after) {
			return $before . $this->fillers(51) . $this->paragraph(3, $style) . $after;
		};
		$without = $this->write($html(''));
		$with = $this->write($html('orphans: 3; widows: 3'));

		$this->assertSame($without->drawnBoxes, $with->drawnBoxes);
		$this->assertSame(0, $with->unwinds);
	}

	/**
	 * A table cell and two columns
	 *
	 * @return array[]
	 */
	public function unsupportedProvider()
	{
		return [
			'table cell' => ['<table><tr><td>', '</td></tr></table>'],
			'columns' => ['<columns column-count="2" />', '<columns column-count="1" />'],
		];
	}

	/**
	 * A document with the HTML written, whose lines have been recorded as they were drawn
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return \Mpdf\TextRecordingMpdf
	 */
	private function write($html, array $config = [])
	{
		$mpdf = new TextRecordingMpdf($config + ['mode' => 'c']);
		$mpdf->WriteHTML($html);

		return $mpdf;
	}

	/**
	 * @param int $count
	 *
	 * @return string One-line filler paragraphs
	 */
	private function fillers($count)
	{
		return str_repeat('<p style="margin: 0">Filler</p>', $count);
	}

	/**
	 * @param int $lines
	 * @param string $style
	 *
	 * @return string A paragraph of that many lines, with no margins
	 */
	private function paragraph($lines, $style = '')
	{
		return '<p style="margin: 0; width: 25mm; ' . $style . '">' . $this->lines($lines) . '</p>';
	}

	/**
	 * @param int $count
	 *
	 * @return string Text that wraps to that many lines 25mm wide
	 */
	private function lines($count)
	{
		$words = [];
		for ($i = 1; $i <= $count; $i++) {
			$words[] = 'Line' . $i . ' xxxxxx';
		}

		return implode(' ', $words);
	}

	/**
	 * @param int $count
	 *
	 * @return string That many words, which wrap where the width of the line says
	 */
	private function words($count)
	{
		return trim(str_repeat('Word ', $count));
	}

	/**
	 * @param \Mpdf\TextRecordingMpdf $mpdf
	 *
	 * @return int[] The page each line of the paragraph was drawn on, in order
	 */
	private function linePages(TextRecordingMpdf $mpdf)
	{
		return $this->pagesOf($this->lineBoxes($mpdf));
	}

	/**
	 * @param array[] $boxes As lineBoxes() gives them
	 *
	 * @return int[] The page of each
	 */
	private function pagesOf(array $boxes)
	{
		return array_map(function ($box) {
			return $box[0];
		}, $boxes);
	}

	/**
	 * @param \Mpdf\TextRecordingMpdf $mpdf
	 * @param string $prefix
	 *
	 * @return array[] The page, left edge, right edge and top of each line drawn starting with the prefix, in order
	 */
	private function lineBoxes(TextRecordingMpdf $mpdf, $prefix = 'Line')
	{
		$boxes = [];
		foreach ($mpdf->drawnText as $i => $text) {
			if (strpos(ltrim($text), $prefix) === 0) {
				$boxes[] = $mpdf->drawnBoxes[$i];
			}
		}

		return $boxes;
	}
}
