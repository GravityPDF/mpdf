<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * An overline is drawn as thick as the font's underline, as an underline is, and at the size a table shrunk to fit
 * draws its text at
 */
class OverlineThicknessTest extends TestCase
{

	use PageStreams;

	/**
	 * Each CSS mode, crossed with a TrueType font and a core font
	 *
	 * @return array[]
	 */
	public function modesAndFonts()
	{
		$cases = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			$cases[$mode . ', DejaVu Serif Condensed'] = [$mode, ['mode' => '', 'default_font' => 'dejavuserifcondensed'], 1.76];
			$cases[$mode . ', core Times'] = [$mode, ['mode' => 'c', 'default_font' => 'times'], 2.0];
		}

		return $cases;
	}

	/**
	 * 40pt text with an overline draws it at the font's underline thickness, the same width as its underline, in
	 * either CSS mode
	 *
	 * @dataProvider modesAndFonts
	 *
	 * @param string $mode
	 * @param array $font
	 * @param float $points The font's underline thickness at 40pt
	 */
	public function testAnOverlineIsAsThickAsTheFontsUnderline($mode, array $font, $points)
	{
		$config = $font + ['cssMode' => $mode];

		$this->assertSame([$points], $this->lineWidths('<p style="font-size: 40pt; text-decoration: overline">Decorated</p>', $config));
		$this->assertSame([$points], $this->lineWidths('<p style="font-size: 40pt; text-decoration: underline">Decorated</p>', $config));
	}

	/**
	 * In a table shrunk to fit its width, the overline is thinned with the text, as the underline beside it is
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
	 * Each CSS mode
	 *
	 * @return array[]
	 */
	public function modes()
	{
		return [CssMode::STANDARD => [CssMode::STANDARD], CssMode::LEGACY => [CssMode::LEGACY]];
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
