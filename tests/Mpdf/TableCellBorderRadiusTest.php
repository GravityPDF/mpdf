<?php

namespace Mpdf;

/**
 * border-radius on a table cell in the separated borders model: the cell's backgrounds are clipped to its rounded
 * border box and its border drawn along it. Under border-collapse: collapse CSS ignores the radius, and so does the
 * legacy CSS mode.
 */
class TableCellBorderRadiusTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The corners of a clip path, anticlockwise from the top left, as RoundedBox::path() runs them
	 */
	const CORNERS = ['TL', 'BL', 'BR', 'TR'];

	/**
	 * The first page of a document holding a one-cell table, the cell 40 mm by 12 mm with no padding
	 *
	 * @param string $cellCss
	 * @param string $tableCss
	 * @param array $config
	 *
	 * @return string
	 */
	private function cellPage($cellCss, $tableCss = 'border-collapse: separate; border-spacing: 2mm', $config = [])
	{
		$html = '<table style="' . $tableCss . '"><tr><td style="width: 40mm; height: 12mm; padding: 0; ' . $cellCss . '">A</td></tr></table>';

		return $this->pages($this->render($html, $config))[0];
	}

	/**
	 * The clip paths on a page that run through at least one curve
	 *
	 * @param string $page
	 *
	 * @return string[]
	 */
	private function roundedClips($page)
	{
		preg_match_all('/q 0 w ((?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c )+) W n/', $page, $m);

		return array_values(array_filter($m[1], function ($path) {
			return strpos($path, ' c ') !== false;
		}));
	}

	/**
	 * The radius of each rounded corner of a path, keyed TL/TR/BR/BL as [horizontal, vertical] in millimetres. A
	 * corner is a run of curves; its radii are how far the run travels along each axis. Square corners are left out.
	 *
	 * @param string $path
	 *
	 * @return array
	 */
	private function radii($path)
	{
		preg_match_all('/((?:[-\d.]+ )+)([mlc]) /', $path, $ops, PREG_SET_ORDER);

		$radii = [];
		$corner = 0;
		$point = null;
		$arcStart = null;
		foreach ($ops as $op) {
			$numbers = array_map('floatval', explode(' ', trim($op[1])));
			if ($op[2] === 'c') {
				if ($arcStart === null) {
					$arcStart = $point;
				}
			} elseif ($arcStart !== null) {
				$radii[self::CORNERS[$corner]] = [abs($point[0] - $arcStart[0]) / Mpdf::SCALE, abs($point[1] - $arcStart[1]) / Mpdf::SCALE];
				$arcStart = null;
			}
			if ($op[2] === 'l') {
				$corner++;
			}
			$point = array_slice($numbers, -2);
		}

		return $radii;
	}

	/**
	 * The width and height of the box a path runs round, in millimetres
	 *
	 * @param string $path
	 *
	 * @return float[]
	 */
	private function sizeOf($path)
	{
		preg_match_all('/([-\d.]+) ([-\d.]+) [ml] /', $path, $m);
		$xs = array_map('floatval', $m[1]);
		$ys = array_map('floatval', $m[2]);

		return [(max($xs) - min($xs)) / Mpdf::SCALE, (max($ys) - min($ys)) / Mpdf::SCALE];
	}

	/**
	 * The one rounded clip on a page, asserting there is exactly one
	 *
	 * @param string $page
	 *
	 * @return string
	 */
	private function onlyClip($page)
	{
		$clips = $this->roundedClips($page);
		$this->assertCount(1, $clips, 'one rounded clip');

		return $clips[0];
	}

	/**
	 * A length rounds every corner of the cell's background by that much
	 */
	public function testALengthRoundsTheBackgroundOfACell()
	{
		$radii = $this->radii($this->onlyClip($this->cellPage('background: #0f0; border-radius: 3mm')));

		$this->assertSame(self::CORNERS, array_keys($radii));
		foreach ($radii as $corner => $radius) {
			$this->assertEqualsWithDelta([3, 3], $radius, 0.01, $corner);
		}
	}

	/**
	 * The clip runs round the box the cell's background fills when it is square, inside the border-spacing
	 */
	public function testTheClipIsTheBoxASquareBackgroundFills()
	{
		$this->assertSame(1, preg_match('/[-\d.]+ [-\d.]+ ([-\d.]+) -([-\d.]+) re f/', $this->cellPage('background: #0f0; border: 1mm solid #00f'), $square));

		$size = $this->sizeOf($this->onlyClip($this->cellPage('background: #0f0; border: 1mm solid #00f; border-radius: 3mm')));

		$this->assertEqualsWithDelta([$square[1] / Mpdf::SCALE, $square[2] / Mpdf::SCALE], $size, 0.01);
	}

	/**
	 * A percentage is of the cell's own border box: horizontal of its width, vertical of its height
	 */
	public function testAPercentageIsOfTheCellsOwnBox()
	{
		$clip = $this->onlyClip($this->cellPage('background: #0f0; border-radius: 50%'));
		$size = $this->sizeOf($clip);

		foreach ($this->radii($clip) as $corner => $radius) {
			$this->assertEqualsWithDelta([$size[0] / 2, $size[1] / 2], $radius, 0.01, $corner);
		}
	}

	/**
	 * A corner longhand rounds that corner alone
	 */
	public function testACornerLonghandRoundsThatCornerAlone()
	{
		$radii = $this->radii($this->onlyClip($this->cellPage('background: #0f0; border-bottom-right-radius: 4mm 2mm')));

		$this->assertSame(['BR'], array_keys($radii));
		$this->assertEqualsWithDelta([4, 2], $radii['BR'], 0.01);
	}

	/**
	 * The row's background shows through a cell with none of its own, and is clipped to the cell's corners too
	 */
	public function testTheRowBackgroundIsClippedToARoundedCell()
	{
		$html = '<table style="border-collapse: separate"><tr style="background: #fcc"><td style="width: 40mm; border-radius: 3mm">A</td></tr></table>';

		$this->onlyClip($this->pages($this->render($html))[0]);
	}

	/**
	 * A gradient background is clipped to the rounded box as a colour is
	 */
	public function testAGradientIsClippedToARoundedCell()
	{
		$page = $this->cellPage('background-image: linear-gradient(#f00, #00f); border-radius: 3mm');

		$this->onlyClip($page);
		$this->assertMatchesRegularExpression('/c [-\d. ]+ l\s+W n\s+q [-\d. ]+ re W n\s+[-\d. ]+ cm\s+\/Sh\d+ sh/', $page, 'the gradient is painted inside the rounded clip');
	}

	/**
	 * The border is stroked side by side along the curve of each corner
	 */
	public function testTheBorderFollowsTheCorners()
	{
		$page = $this->cellPage('border: 1mm solid #00f; border-radius: 3mm');

		$this->assertSame(4, preg_match_all('/c S/', $page), 'four sides end in a curve');
	}

	/**
	 * A double border is two strokes per side, and groove two of different colours
	 */
	public function testDoubleAndGrooveBordersAreTwoStrokesPerSide()
	{
		$this->assertSame(8, preg_match_all('/c S/', $this->cellPage('border: 3mm double #00f; border-radius: 5mm')));

		$groove = $this->cellPage('border: 3mm groove #36c; border-radius: 5mm');
		$this->assertSame(8, preg_match_all('/c S/', $groove));
		preg_match_all('/([\d.]+ [\d.]+ [\d.]+) RG/', $groove, $m);
		$this->assertCount(2, array_unique($m[1]), 'groove takes two tones');
	}

	/**
	 * A side the cell has no border on is not drawn
	 */
	public function testOnlyTheSidesWithABorderAreDrawn()
	{
		$this->assertSame(1, preg_match_all('/ S/', $this->cellPage('border-top: 1mm solid #00f; border-radius: 3mm')));
	}

	/**
	 * CSS ignores border-radius on a cell under border-collapse: collapse; the cell is drawn as if it had none
	 */
	public function testACollapsedCellStaysSquare()
	{
		$collapse = 'border-collapse: collapse';

		$this->assertSame(
			$this->cellPage('background: #0f0; border: 1mm solid #00f', $collapse),
			$this->cellPage('background: #0f0; border: 1mm solid #00f; border-radius: 3mm', $collapse)
		);
	}

	/**
	 * The legacy CSS mode draws the cell square, as before
	 */
	public function testTheLegacyModeKeepsTheCellSquare()
	{
		$legacy = ['cssMode' => CssMode::LEGACY];
		$table = 'border-collapse: separate; border-spacing: 2mm';

		$this->assertSame(
			$this->cellPage('background: #0f0; border: 1mm solid #00f', $table, $legacy),
			$this->cellPage('background: #0f0; border: 1mm solid #00f; border-radius: 3mm', $table, $legacy)
		);
	}

	/**
	 * A radius of nothing draws the cell exactly as one without
	 */
	public function testAZeroRadiusDrawsTheCellAsBefore()
	{
		$this->assertSame(
			$this->cellPage('background: #0f0; border: 1mm solid #00f'),
			$this->cellPage('background: #0f0; border: 1mm solid #00f; border-radius: 0')
		);
	}

	/**
	 * Simple tables draw every cell with the table's own border, and leave the radius out with the cell's border
	 */
	public function testSimpleTablesKeepTheCellSquare()
	{
		$this->assertSame([], $this->roundedClips($this->cellPage('background: #0f0; border-radius: 3mm', 'border-collapse: separate', ['simpleTables' => true])));
	}

	/**
	 * A header row repeated on the next page keeps its rounded corners
	 */
	public function testARepeatedHeaderRowStaysRounded()
	{
		$rows = str_repeat('<tr><td>Row</td></tr>', 80);
		$html = '<table style="border-collapse: separate"><thead><tr><th style="background: #ff0; border-radius: 3mm">Head</th></tr></thead><tbody>' . $rows . '</tbody></table>';

		$pages = $this->pages($this->render($html));

		$this->assertGreaterThan(1, count($pages));
		foreach ($pages as $n => $page) {
			$this->assertCount(1, $this->roundedClips($page), 'page ' . ($n + 1));
		}
	}

	/**
	 * A cell in a nested table is clipped by its own table, not the outer one's rounded cell
	 */
	public function testANestedTableKeepsItsOwnClips()
	{
		$inner = '<table style="border-collapse: separate"><tr><td style="background: #0f0; border-radius: 2mm">In</td></tr></table>';
		$html = '<table style="border-collapse: separate"><tr><td style="background: #ccc; border-radius: 5mm; padding: 4mm">' . $inner . '</td></tr></table>';

		$clips = $this->roundedClips($this->pages($this->render($html))[0]);

		$this->assertCount(2, $clips);
		$this->assertNotSame($clips[0], $clips[1]);
	}
}
