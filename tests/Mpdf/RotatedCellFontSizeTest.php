<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Rotated text in a table cell whose font size is the one already in force, such as the size the table sets, is
 * measured and drawn at that size (#632)
 */
class RotatedCellFontSizeTest extends TestCase
{

	use PageStreams;

	/**
	 * The rotated text is drawn at the table's size, set by an attribute or by a rule
	 *
	 * @dataProvider tables
	 *
	 * @param string $html
	 */
	public function testDrawsRotatedTextAtTheTablesFontSize($html)
	{
		preg_match_all('/([\d.]+) Tf ET(?:(?!Tf ET).)*?\(Rotated\) Tj/s', $this->pages($this->render($html))[0], $sizes);

		$this->assertSame(['9.000'], $sizes[1]);
	}

	/**
	 * Tables that set a font size, with a rotated cell
	 *
	 * @return array[]
	 */
	public function tables()
	{
		return [
			'the size in the table\'s style' => ['<table style="font-size: 9pt"><tr style="text-rotate: 45"><td>Rotated</td></tr></table>'],
			'the size in a rule for the table' => ['<style>table { font-size: 9pt; }</style><table><tr><td>Plain</td></tr><tr style="text-rotate: 90"><td>Rotated</td></tr></table>'],
		];
	}
}
