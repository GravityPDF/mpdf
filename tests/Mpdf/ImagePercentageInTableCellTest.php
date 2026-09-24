<?php

namespace Mpdf;

/**
 * A percentage width, min-width or max-width on an image in a table cell is of the cell's content width, which is only
 * known once the table's columns are laid out.
 *
 * The tables are 180mm wide, the whole of an A4 page inside its default margins, with no cell padding or borders, so a
 * column's width is its content width.
 */
class ImagePercentageInTableCellTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * An image, by default 400px wide, 105.8mm at 96dpi
	 *
	 * @param string $style
	 * @param string $width Its width attribute
	 *
	 * @return string
	 */
	private function image($style, $width = '400')
	{
		return '<img src="' . __DIR__ . '/../data/img/bayeux2.jpg" width="' . $width . '" style="' . $style . '">';
	}

	/**
	 * A 180mm table of one row, its cells given as their width attribute and content
	 *
	 * @param array $cells
	 * @param string $css
	 *
	 * @return string
	 */
	private function table(array $cells, $css = '')
	{
		$html = '<style>table { width: 100%; border-collapse: collapse; } td { padding: 0; }' . $css . '</style><table><tr>';
		foreach ($cells as $cell) {
			$html .= '<td' . ($cell[0] !== '' ? ' width="' . $cell[0] . '"' : '') . '>' . $cell[1] . '</td>';
		}

		return $html . '</tr></table>';
	}

	/**
	 * Where each image on the first page is placed, keyed w/h/x in millimetres
	 *
	 * @param string $html
	 *
	 * @return array[]
	 */
	private function placements($html)
	{
		preg_match_all('/([-\d.]+) 0 0 ([-\d.]+) ([-\d.]+) [-\d.]+ cm \/I\d+ Do/', $this->pages($this->render($html))[0], $matches, PREG_SET_ORDER);

		return array_map(function ($match) {
			return ['w' => $match[1] / Mpdf::SCALE, 'h' => $match[2] / Mpdf::SCALE, 'x' => $match[3] / Mpdf::SCALE];
		}, $matches);
	}

	/**
	 * The width of the only image on the first page, in millimetres
	 *
	 * @param string $html
	 *
	 * @return float
	 */
	private function drawnWidth($html)
	{
		$placements = $this->placements($html);
		$this->assertCount(1, $placements);

		return $placements[0]['w'];
	}

	/**
	 * Lengths given as percentages, each in the middle of three 60mm columns
	 *
	 * @return array[]
	 */
	public static function percentages()
	{
		return [
			'max-width caps a wider picture' => ['max-width: 20%', 12],
			'max-width: 100% keeps it inside its cell' => ['max-width: 100%', 60],
			'width in CSS' => ['width: 50%', 30],
			'width as an attribute' => ['', 60, '100%'],
			'min-width widens a narrower picture' => ['width: 10mm; min-width: 50%', 30],
		];
	}

	/**
	 * @dataProvider percentages
	 *
	 * @param string $style
	 * @param float $expected
	 * @param string $width
	 */
	public function testAPercentageIsOfTheCell($style, $expected, $width = '400')
	{
		$html = $this->table([['33.3333%', 'one'], ['33.3333%', $this->image($style, $width)], ['', 'three']]);

		$this->assertEqualsWithDelta($expected, $this->drawnWidth($html), 0.05);
	}

	/**
	 * Outside a table a percentage is still of the block the image is in
	 */
	public function testOutsideATableAPercentageIsOfTheBlock()
	{
		$this->assertEqualsWithDelta(12, $this->drawnWidth('<div style="width: 60mm">' . $this->image('max-width: 20%') . '</div>'), 0.05);
	}

	/**
	 * An absolute max-width means the same in a cell as anywhere else
	 */
	public function testAnAbsoluteLengthIsUnchanged()
	{
		$html = $this->table([['33.3333%', 'one'], ['33.3333%', $this->image('max-width: 20mm')], ['', 'three']]);

		$this->assertEqualsWithDelta(20, $this->drawnWidth($html), 0.05);
	}

	/**
	 * A picture narrowed by a percentage no longer widens its column: the columns keep the 20%, 20% and 60% they were
	 * given, so the picture sits 36mm in from the left margin and is 36mm wide
	 */
	public function testThePictureLeavesTheColumnWidthsAlone()
	{
		$html = $this->table([['20%', 'one'], ['20%', $this->image('max-width: 100%')], ['60%', 'three']]);

		$placement = $this->placements($html)[0];
		$this->assertEqualsWithDelta(36, $placement['w'], 0.05);
		$this->assertEqualsWithDelta(15 + 36, $placement['x'], 0.05);
	}

	/**
	 * In a table shrunk to fit its page a percentage is of the cell as it is drawn: a 300mm column and a 60mm one are
	 * halved to fit 180mm, so a picture given min-width: 100% fills 30mm
	 */
	public function testInAShrunkTableAPercentageIsOfTheCellAsDrawn()
	{
		$html = '<style>table { border-collapse: collapse; } td { padding: 0; }</style><table><tr><td style="width: 300mm">one</td>'
			. '<td style="width: 60mm">' . $this->image('width: 10mm; min-width: 100%') . '</td></tr></table>';

		$this->assertEqualsWithDelta(30, $this->drawnWidth($html), 0.05);
	}

	/**
	 * In a nested table a percentage is of the inner cell: a 90mm table in the second half of the page, split 50/50
	 */
	public function testInANestedTableAPercentageIsOfTheInnerCell()
	{
		$inner = '<table style="width: 100%; border-collapse: collapse"><tr><td width="50%">one</td><td width="50%">'
			. $this->image('width: 100%') . '</td></tr></table>';
		$html = $this->table([['50%', 'one'], ['50%', $inner]]);

		$placement = $this->placements($html)[0];
		$this->assertEqualsWithDelta(45, $placement['w'], 0.05);
		$this->assertEqualsWithDelta(15 + 90 + 45, $placement['x'], 0.05);
	}

	/**
	 * Tables too wide for the page, with long unbreakable words in five columns and the image in a sixth, either
	 * directly or in a nested table
	 *
	 * @return array[]
	 */
	public static function overflowingTables()
	{
		$words = str_repeat('<td>https://example.com/a-rather-long-unbreakable-url</td>', 5);

		return [
			'in the table' => ['<table><tr>' . $words . '<td>%s</td></tr></table>'],
			'in a nested table' => ['<table><tr>' . $words . '<td><table><tr><td>x</td><td>%s</td></tr></table></td></tr></table>'],
		];
	}

	/**
	 * A table too wide for its page is shrunk as a whole: max-width: 100% no longer lets the cell narrow the picture
	 * to nothing, and it is drawn as it would be without it
	 *
	 * @dataProvider overflowingTables
	 *
	 * @param string $table
	 */
	public function testAPictureKeepsItsWidthInATableShrunkToFit($table)
	{
		$without = $this->drawnWidth(sprintf($table, $this->image('')));

		$this->assertGreaterThan(10, $without);
		$this->assertEqualsWithDelta($without, $this->drawnWidth(sprintf($table, $this->image('max-width: 100%'))), 0.05);
	}

	/**
	 * A percentage radius is of the picture's size in its cell, not of the size it had before the table was laid out
	 */
	public function testAPercentageRadiusIsOfTheSizedPicture()
	{
		$html = $this->table([['33.3333%', 'one'], ['33.3333%', $this->image('max-width: 100%; border-radius: 50%')], ['', 'three']]);

		preg_match('/([-\d.]+) [-\d.]+ m (?:[-\d. ]+ [lc] )+W n ([-\d. ]+) cm \/I1 Do Q/', $this->pages($this->render($html))[0], $matches);
		$cm = explode(' ', $matches[2]);

		/* The clip starts at the top tangent point of the top-left corner, half the picture's width in */
		$this->assertEqualsWithDelta((float) $cm[4] + (float) $cm[0] / 2, (float) $matches[1], 0.01);
	}

}
