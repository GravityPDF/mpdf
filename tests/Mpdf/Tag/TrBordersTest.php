<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;

class TrBordersTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	private function render($rowStyle)
	{
		$mpdf = new Mpdf();
		$mpdf->compress = false;
		$mpdf->WriteHTML(
			'<style>table { border-collapse: collapse } td { border: 1px solid #000000 }</style>'
			. '<table>'
			. '<tr' . ($rowStyle ? ' style="' . $rowStyle . '"' : '') . '><td>a</td><td>b</td></tr>'
			. '<tr><td>c</td><td>d</td></tr>'
			. '</table>'
		);

		return $mpdf->Output('', 'S');
	}

	/**
	 * Collapsed cell borders are stroked one segment at a time
	 */
	private function segments($pdf)
	{
		$matches = [];
		preg_match_all('/([\d.]+) ([\d.]+) m ([\d.]+) ([\d.]+) l S/', $pdf, $matches, PREG_SET_ORDER);

		return $matches;
	}

	private function horizontalSegments($pdf)
	{
		$horizontal = 0;
		foreach ($this->segments($pdf) as $segment) {
			if ($segment[2] === $segment[4]) {
				$horizontal++;
			}
		}

		return $horizontal;
	}

	private function redSegments($pdf)
	{
		return substr_count($pdf, '1.000 0.000 0.000 RG');
	}

	/**
	 * A row that names one side used to be handed the other three as null, which resolved to a
	 * border of zero width and switched off the ones the cells had asked for. See mpdf/mpdf#1893.
	 */
	public function testARowBorderOnOneSideLeavesTheCellBordersAlone()
	{
		$this->assertSame(
			$this->horizontalSegments($this->render('')),
			$this->horizontalSegments($this->render('border-left: 2px solid #ff0000'))
		);
	}

	public function testARowBorderOnOneSideLeavesTheCountOfSegmentsAlone()
	{
		$this->assertSame(
			count($this->segments($this->render(''))),
			count($this->segments($this->render('border-left: 2px solid #ff0000')))
		);
	}

	public function testARowBorderOnTheLeftIsStillDrawn()
	{
		$this->assertSame(1, $this->redSegments($this->render('border-left: 2px solid #ff0000')));
	}

	/**
	 * Tr::close() reads trborder-right but tested trborder-left, so a row that named only its right
	 * side never reached the code that draws it
	 */
	public function testARowBorderOnTheRightIsDrawn()
	{
		$this->assertSame(1, $this->redSegments($this->render('border-right: 2px solid #ff0000')));
	}

	/**
	 * Td::open() read the row's top and bottom borders only when the row also set its left one, so a row that named
	 * only its top or bottom drew neither
	 *
	 * @dataProvider topAndBottom
	 *
	 * @param string $side
	 */
	public function testARowBorderOnTheTopOrBottomAloneIsDrawn($side)
	{
		$this->assertGreaterThan(0, $this->redSegments($this->render('border-' . $side . ': 2px solid #ff0000')));
	}

	/**
	 * The sides Td::open() draws for a row
	 *
	 * @return array[]
	 */
	public function topAndBottom()
	{
		return ['top' => ['top'], 'bottom' => ['bottom']];
	}

	public function testARowWithNoBorderOfItsOwnDrawsNone()
	{
		$this->assertSame(0, $this->redSegments($this->render('')));
	}

	/**
	 * The cells' blue borders on the side a row covers are drawn, or not, and the row's red border is drawn, or not
	 *
	 * @dataProvider rowBorders
	 *
	 * @param string $side bottom, top, left or right
	 * @param string $css Rules for the row
	 * @param bool $cellBorder Whether the cells' borders are drawn
	 * @param bool $rowBorder Whether the row's border is drawn
	 */
	public function testARowBorderReplacesTheCellsOnlyWhenItIsDrawnOrHidden($side, $css, $cellBorder, $rowBorder)
	{
		foreach ([\Mpdf\CssMode::STANDARD, \Mpdf\CssMode::LEGACY] as $mode) {
			$pdf = $this->renderStylesheet('td { border-' . $side . ': 0.5mm solid #00f; } ' . $css, $mode);

			$this->assertSame($cellBorder, strpos($pdf, '0.000 0.000 1.000 RG') !== false, $mode . ': the cells\' border');
			$this->assertSame($rowBorder, strpos($pdf, '1.000 0.000 0.000 RG') !== false, $mode . ': the row\'s border');
		}
	}

	/**
	 * Each side a row covers, with rules for the rows, whether the cells' borders are drawn, and whether the row's is
	 *
	 * @return array[]
	 */
	public function rowBorders()
	{
		$rules = [
			'no border' => ['', true, false],
			'a colour alone' => ['tr { border-color: #f00; }', true, false],
			'no width' => ['tr { border: 0 solid #f00; }', true, false],
			'no style' => ['tr { border: none; }', true, false],
			'a style and a colour but no width' => ['tr { border-style: solid; border-width: 0; border-color: #f00; }', true, false],
			'hidden' => ['tr { border: hidden; }', false, false],
			'drawn' => ['tr { border: 0.5mm solid #f00; }', false, true],
		];

		$data = [];
		foreach (['bottom', 'top', 'left', 'right'] as $side) {
			foreach ($rules as $name => $rule) {
				$data[$side . ': ' . $name] = array_merge([$side], $rule);
			}
		}

		return $data;
	}

	/**
	 * A reset reaching every element leaves the borders a later rule gives the cells
	 *
	 * @dataProvider resets
	 *
	 * @param string $reset
	 */
	public function testAResetLeavesTheCellsBorders($reset)
	{
		$this->assertStringContainsString('0.000 0.000 1.000 RG', $this->renderStylesheet($reset . ' td { border-bottom: 0.5mm solid #00f; }', \Mpdf\CssMode::STANDARD));
	}

	/**
	 * Resets as frameworks write them
	 *
	 * @return array[]
	 */
	public function resets()
	{
		return [
			'a border colour' => ['* { border-color: #dee2e6; }'],
			'no width, a style and a colour' => ['*, ::before, ::after { border-width: 0; border-style: solid; border-color: #e5e7eb; }'],
			'the border shorthand' => ['* { border: 0 solid; }'],
		];
	}

	/**
	 * @param string $css
	 * @param string $mode A CssMode value
	 *
	 * @return string An uncompressed document holding a table of one-cell rows with collapsed borders, under a
	 *                stylesheet
	 */
	private function renderStylesheet($css, $mode)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => $mode]);
		$mpdf->SetCompression(false);
		$mpdf->WriteHTML('<style>table { border-collapse: collapse; } ' . $css . '</style><table><tr><td>a</td></tr><tr><td>b</td></tr></table>');

		return $mpdf->Output('', 'S');
	}

}
