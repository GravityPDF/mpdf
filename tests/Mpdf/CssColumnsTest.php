<?php

namespace Mpdf;

/**
 * A block at the top level with column-count or column-width is laid out in columns in the standard CSS mode, with
 * column-gap between them, column-rule drawn down the gaps, column-span: all on a child and column-fill
 */
class CssColumnsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * About eighty words, which take five lines across the page and more than one line in each of four columns
	 *
	 * @return string
	 */
	private function words()
	{
		return str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor. ', 8);
	}

	/**
	 * The text lines drawn on each page, as [x, y, text] with x and y in mm from the top left of the page
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return array[]
	 */
	private function lines($html, $config = [])
	{
		$mpdf = $this->mpdf($config);
		$mpdf->WriteHTML($html);
		$h = $mpdf->h;

		$pages = [];
		foreach ($this->pages($this->output($mpdf)) as $stream) {
			preg_match_all('/BT (-?\d+\.\d+) (-?\d+\.\d+) Td\s*\((.*?)\) Tj/', $stream, $m, PREG_SET_ORDER);
			$pages[] = array_map(function ($match) use ($h) {
				return [round($match[1] / Mpdf::SCALE, 1), round($h - $match[2] / Mpdf::SCALE, 1), $match[3]];
			}, $m);
		}

		return $pages;
	}

	/**
	 * The distinct left edges the lines of a page start at, in mm, in order
	 *
	 * @param array $lines
	 *
	 * @return float[]
	 */
	private function columnEdges(array $lines)
	{
		$edges = array_values(array_unique(array_map(function ($line) {
			return $line[0];
		}, $lines)));
		sort($edges);

		return $edges;
	}

	/**
	 * The vertical lines each page strokes, as [x, top, bottom] in mm from the top left of the page
	 *
	 * @param string $html
	 *
	 * @return array[]
	 */
	private function rules($html)
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML($html);
		$h = $mpdf->h;

		$pages = [];
		foreach ($this->pages($this->output($mpdf)) as $stream) {
			preg_match_all('/(-?\d+\.\d+) (-?\d+\.\d+) m (-?\d+\.\d+) (-?\d+\.\d+) l S/', $stream, $m, PREG_SET_ORDER);
			$rules = [];
			foreach ($m as $match) {
				if ($match[1] === $match[3]) {
					$rules[] = [round($match[1] / Mpdf::SCALE, 1), round($h - $match[2] / Mpdf::SCALE, 1), round($h - $match[4] / Mpdf::SCALE, 1)];
				}
			}
			$pages[] = $rules;
		}

		return $pages;
	}

	/**
	 * column-count: 2 with a 10mm gap gives two 85mm columns across the 180mm text area, starting at 15mm and 110mm
	 */
	public function testColumnCountLaysOutTwoColumns()
	{
		$pages = $this->lines('<div style="column-count: 2; column-gap: 10mm">' . $this->words() . '</div>');

		$this->assertCount(1, $pages);
		$this->assertSame([15.0, 110.0], $this->columnEdges($pages[0]));
	}

	/**
	 * The columns share the lines between them, so each is about half as tall as the text in one column
	 */
	public function testColumnsAreBalanced()
	{
		$pages = $this->lines('<div style="column-count: 2; column-gap: 10mm">' . $this->words() . '</div>');
		$counts = array_count_values(array_map(function ($line) {
			return (string) $line[0];
		}, $pages[0]));

		$this->assertLessThanOrEqual(1, abs($counts['15'] - $counts['110']));
	}

	/**
	 * columns with only a width gives as many 40mm columns as fit in 180mm with the normal 1em gap: four
	 */
	public function testColumnWidthGivesAsManyColumnsAsFit()
	{
		$pages = $this->lines('<div style="columns: 40mm">' . $this->words() . '</div>');

		$this->assertCount(4, $this->columnEdges($pages[0]));
	}

	/**
	 * A column-count with a column-width is the most columns there are: three of the four 40mm columns that fit
	 */
	public function testColumnCountCapsTheColumnsAWidthGives()
	{
		$pages = $this->lines('<div style="columns: 3 40mm; column-gap: 0">' . $this->words() . '</div>');

		$this->assertSame([15.0, 75.0, 135.0], $this->columnEdges($pages[0]));
	}

	/**
	 * A column-width wider than half the text area leaves one column
	 */
	public function testColumnWidthWiderThanHalfLeavesOneColumn()
	{
		$pages = $this->lines('<div style="column-width: 100mm">' . $this->words() . '</div>');

		$this->assertSame([15.0], $this->columnEdges($pages[0]));
	}

	/**
	 * column-rule draws a line down the middle of the gap, from the top of the columns to the foot of the longer one
	 */
	public function testColumnRuleIsDrawnDownTheGap()
	{
		$html = '<div style="column-count: 2; column-gap: 10mm; column-rule: 1px solid">' . $this->words() . '</div>';
		$lines = $this->lines($html);
		$rules = $this->rules($html);

		$this->assertCount(1, $rules[0]);
		$this->assertSame(105.0, $rules[0][0][0]);
		$this->assertLessThan(min(array_column($lines[0], 1)), $rules[0][0][1]);
		$this->assertGreaterThan(max(array_column($lines[0], 1)), $rules[0][0][2]);
	}

	/**
	 * A column rule is drawn in its colour, which is currentColor when it names none, in its width, and dashed where
	 * its style says so, with the dashes of a dashed border
	 */
	public function testColumnRuleTakesItsColourWidthAndStyle()
	{
		$named = $this->render('<div style="column-count: 2; column-rule: 2mm dashed #ff0000">' . $this->words() . '</div>');
		$current = $this->render('<div style="color: #0000ff; column-count: 2; column-rule: 2mm solid">' . $this->words() . '</div>');

		$this->assertMatchesRegularExpression('/q 1\.000 0\.000 0\.000 RG 5\.669 w \[5\.669 15\.118\] 0 d 0 J [\d. ]+ m [\d. ]+ l S Q/', $named);
		$this->assertMatchesRegularExpression('/q 0\.000 0\.000 1\.000 RG 5\.669 w \[\] 0 d 0 J [\d. ]+ m [\d. ]+ l S Q/', $current);
	}

	/**
	 * A column-rule-style given without the shorthand draws a medium rule in the element's colour, or black
	 */
	public function testColumnRuleLonghandsTakeTheirInitialValues()
	{
		$coloured = $this->render('<div style="color: #00ff00; column-count: 2; column-rule-style: solid">' . $this->words() . '</div>');
		$black = $this->render('<div style="column-count: 2; column-rule-style: solid">' . $this->words() . '</div>');

		$this->assertMatchesRegularExpression('/q 0\.000 1\.000 0\.000 RG 2\.250 w \[\] 0 d/', $coloured);
		$this->assertMatchesRegularExpression('/q 0\.000 0\.000 0\.000 RG 2\.250 w \[\] 0 d/', $black);
	}

	/**
	 * column-rule-style: none, the default, draws no rule
	 */
	public function testNoColumnRuleWithoutAStyle()
	{
		$rules = $this->rules('<div style="column-count: 2; column-rule: 1px">' . $this->words() . '</div>');

		$this->assertSame([], $rules[0]);
	}

	/**
	 * A heading with column-span: all ends the columns before it, is laid out across the page, and the columns start
	 * again below it
	 */
	public function testColumnSpanAllLaysAChildOutAcrossThePage()
	{
		$pages = $this->lines(
			'<div style="column-count: 2; column-gap: 10mm"><p>' . $this->words() . '</p>'
			. '<h2 style="column-span: all">Spanning heading</h2><p>' . $this->words() . '</p></div>'
		);
		$headings = array_values(array_filter($pages[0], function ($line) {
			return $line[2] === 'Spanning heading';
		}));

		$this->assertCount(1, $headings);
		$heading = $headings[0];
		$this->assertSame(15.0, $heading[0]);
		$above = array_filter($pages[0], function ($line) use ($heading) {
			return $line[1] < $heading[1];
		});
		$below = array_filter($pages[0], function ($line) use ($heading) {
			return $line[1] > $heading[1];
		});

		$this->assertSame([15.0, 110.0], $this->columnEdges($above));
		$this->assertSame([15.0, 110.0], $this->columnEdges($below));
	}

	/**
	 * The heading that spans the columns is as wide as the page's text area: centred text lands mid-page, not
	 * mid-column
	 */
	public function testColumnSpanAllChildIsAsWideAsThePage()
	{
		$pages = $this->lines(
			'<div style="column-count: 2"><p>' . $this->words() . '</p>'
			. '<h2 style="column-span: all; text-align: center">Centred</h2><p>' . $this->words() . '</p></div>'
		);

		$centred = array_filter($pages[0], function ($line) {
			return $line[0] > 80 && $line[0] < 105;
		});

		$this->assertCount(1, $centred);
	}

	/**
	 * A columned block longer than a page fills both columns of the first page, carries on in both columns of the
	 * next, and draws its rule on each page
	 */
	public function testColumnedBlockCrossesAPage()
	{
		$html = '<div style="column-count: 2; column-gap: 10mm; column-rule: 1px solid">'
			. str_repeat('<p>' . $this->words() . '</p>', 14) . '</div><p>After the columns</p>';
		$pages = $this->lines($html);
		$rules = $this->rules($html);

		$this->assertCount(2, $pages);
		$this->assertSame([15.0, 110.0], $this->columnEdges($pages[0]));
		$this->assertSame([15.0, 110.0], $this->columnEdges(array_slice($pages[1], 0, -1)));
		$this->assertCount(1, $rules[0]);
		$this->assertCount(1, $rules[1]);
		$this->assertSame(105.0, $rules[1][0][0]);
	}

	/**
	 * Text after the columned block starts below the foot of its longer column, across the page
	 */
	public function testTextAfterTheColumnsStartsBelowThem()
	{
		$pages = $this->lines('<div style="column-count: 2">' . $this->words() . '</div><p>After</p>');
		$last = array_pop($pages[0]);

		$this->assertSame(15.0, $last[0]);
		$this->assertGreaterThan(max(array_column($pages[0], 1)), $last[1]);
	}

	/**
	 * The block's top margin is kept above the columns, so both columns start at the same height
	 */
	public function testTopMarginIsAboveTheColumns()
	{
		$pages = $this->lines('<p>Before</p><p style="column-count: 2; margin-top: 20mm">' . $this->words() . '</p>');
		$tops = [];
		foreach (array_slice($pages[0], 1) as $line) {
			if (!isset($tops[(string) $line[0]])) {
				$tops[(string) $line[0]] = $line[1];
			}
		}

		$this->assertCount(2, $tops);
		$this->assertSame(1, count(array_unique($tops)));
		$this->assertGreaterThan($pages[0][0][1] + 20, reset($tops));
	}

	/**
	 * column-fill: auto fills one column before the next, so text shorter than a column stays in the first
	 */
	public function testColumnFillAutoFillsTheFirstColumnFirst()
	{
		$pages = $this->lines('<div style="column-count: 2; column-fill: auto">' . $this->words() . '</div>');

		$this->assertSame([15.0], $this->columnEdges($pages[0]));
	}

	/**
	 * column-fill: auto still fills one column after another on the page after a page break, where mPDF starts its
	 * columns again
	 */
	public function testColumnFillAutoHoldsAcrossAPageBreak()
	{
		$pages = $this->lines('<div style="column-count: 2; column-fill: auto">' . str_repeat('<p>' . $this->words() . '</p>', 10) . '</div>');

		$this->assertCount(2, $pages);
		$this->assertSame([15.0], $this->columnEdges($pages[1]));
	}

	/**
	 * A block with a border of its own, or one inside a bordered block, stays in one column: columns that span the page
	 * cannot be drawn inside its box
	 *
	 * @dataProvider boxedBlocks
	 *
	 * @param string $html
	 */
	public function testBlockWithABoxStaysInOneColumn($html)
	{
		$pages = $this->lines($html);

		$this->assertSame([16.0], $this->columnEdges($pages[0]));
	}

	/**
	 * Blocks that ask for columns but have a box of their own or sit inside one
	 *
	 * @return array[]
	 */
	public function boxedBlocks()
	{
		$words = $this->words();

		return [
			'inside a bordered block' => ['<div style="border: 1px solid; padding: 0.7mm"><div style="column-count: 2">' . $words . '</div></div>'],
			'with its own border' => ['<div style="column-count: 2; border: 1px solid; padding: 0.7mm">' . $words . '</div>'],
			'with side margins' => ['<div style="column-count: 2; margin: 0 1mm">' . $words . '</div>'],
		];
	}

	/**
	 * The legacy CSS mode ignores the column properties, as mPDF did
	 */
	public function testLegacyModeKeepsOneColumn()
	{
		$pages = $this->lines(
			'<div style="column-count: 2; column-rule: 1px solid">' . $this->words() . '</div>',
			['cssMode' => CssMode::LEGACY]
		);

		$this->assertSame([15.0], $this->columnEdges($pages[0]));
	}

	/**
	 * page-break-inside: avoid on a columned block is dropped rather than the columns: they cannot be laid out twice
	 */
	public function testPageBreakInsideAvoidKeepsTheColumns()
	{
		$pages = $this->lines('<div style="column-count: 2; column-gap: 10mm; page-break-inside: avoid">' . $this->words() . '</div>');

		$this->assertSame([15.0, 110.0], $this->columnEdges($pages[0]));
	}

	/**
	 * mPDF's own columns are left to their tag: a column-count on a block inside them adds none
	 */
	public function testBlockInsideColumnsTagIsNotColumnedAgain()
	{
		$pages = $this->lines(
			'<columns column-count="2" column-gap="10" /><div style="column-count: 3">' . $this->words() . '</div><columns column-count="1" />'
		);

		$this->assertSame([15.0, 110.0], $this->columnEdges($pages[0]));
	}

}
