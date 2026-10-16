<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * An overline takes the font's underline thickness, so it matches the underline on the same text, and a table shrunk
 * to fit thins it with its text
 */
class OverlineThicknessTest extends TestCase
{

	use DrawnStyles;
	use PageStreams;

	/**
	 * Each CSS mode, crossed with a TrueType font and a core font
	 *
	 * @return array[]
	 */
	public function modesAndFonts()
	{
		$cases = [];
		foreach (array_keys($this->modes()) as $mode) {
			$cases[$mode . ', DejaVu Serif Condensed'] = [$mode, ['mode' => '', 'default_font' => 'dejavuserifcondensed'], 1.76];
			$cases[$mode . ', core Times'] = [$mode, ['mode' => 'c', 'default_font' => 'times'], 2.0];
		}

		return $cases;
	}

	/**
	 * At 40pt an overline and an underline are both drawn at the font's underline thickness, in either CSS mode
	 *
	 * @dataProvider modesAndFonts
	 *
	 * @param string $mode
	 * @param array $font
	 * @param float $points The font's underline thickness at 40pt
	 */
	public function testAnOverlineIsAsThickAsTheFontsUnderline($mode, array $font, $points)
	{
		$widths = $this->lineWidths(
			'<p style="font-size: 40pt; text-decoration: overline">Decorated</p><p style="font-size: 40pt; text-decoration: underline">Decorated</p>',
			$font + ['cssMode' => $mode]
		);

		$this->assertSame([$points, $points], $widths);
	}

	/**
	 * In a table shrunk to fit, the overline is thinned with the text to the width of the underline beside it
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testAnOverlineIsShrunkWithItsTable($mode)
	{
		$widths = $this->lineWidths(
			'<table style="width: 50mm" autosize="4"><tr><td style="font-size: 40pt; text-decoration: underline overline">DecoratedDecoratedDecorated</td></tr></table>',
			['mode' => '', 'default_font' => 'dejavuserifcondensed', 'cssMode' => $mode]
		);

		$this->assertCount(2, $widths);
		$this->assertLessThan(1.76, $widths[0]);
		$this->assertSame($widths[0], $widths[1]);
	}

	/**
	 * The line widths, in points, a document sets for its text decorations, in the order they are drawn
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return float[]
	 */
	private function lineWidths($html, array $config)
	{
		preg_match_all('/([\d.]+) w 0 j 0 J/', implode("\n", $this->pages($this->render($html, $config))), $matches);

		return array_map('floatval', $matches[1]);
	}

}
