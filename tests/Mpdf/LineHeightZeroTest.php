<?php

namespace Mpdf;

/**
 * line-height: 0 gives lines no height, rather than being read as normal, a line height smaller than the font is
 * kept rather than stretched down to the baseline, and a negative line-height is ignored. In cssMode legacy each is
 * read as mPDF v7 read it.
 */
class LineHeightZeroTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider providerZero
	 *
	 * @param string $value A line-height of zero
	 */
	public function testZeroIsAZeroLineHeight($value)
	{
		$mpdf = new Mpdf();

		$this->assertSame('0mm', $mpdf->fixLineheight($value));
	}

	/**
	 * @return array[] The ways of writing a line-height of zero
	 */
	public function providerZero()
	{
		return [
			'number' => ['0'],
			'decimal' => ['0.0'],
			'px' => ['0px'],
			'mm' => ['0mm'],
			'percentage' => ['0%'],
			'em' => ['0em'],
		];
	}

	/**
	 * In cssMode legacy a line-height of zero is returned as normal: as a number, the normal line height of the font,
	 * whatever the PHP version, and as a length, the normal factor
	 */
	public function testZeroIsNotAZeroLineHeightInLegacyMode()
	{
		$mpdf = new Mpdf(['cssMode' => CssMode::LEGACY]);

		$this->assertSame('N', $mpdf->fixLineheight('0'));
		$this->assertSame('N', $mpdf->fixLineheight('0.0'));
		$this->assertSame($mpdf->normalLineheight, $mpdf->fixLineheight('0px'));
	}

	/**
	 * Values other than zero are returned as they were
	 */
	public function testOtherValuesAreUnchanged()
	{
		$mpdf = new Mpdf();

		$this->assertSame('N', $mpdf->fixLineheight('normal'));
		$this->assertSame(1.5, $mpdf->fixLineheight('1.5'));
		$this->assertSame('5mm', $mpdf->fixLineheight('5mm'));
	}

	/**
	 * Lines take the height line-height gives them. In cssMode legacy line-height: 0 is read as normal, or on a table
	 * as not set, and a line height smaller than the font is stretched down to the baseline
	 *
	 * @dataProvider providerHeights
	 *
	 * @param string $html The document
	 * @param float $height How far it moves down the page, in mm
	 * @param float $legacy How far it moves down the page in legacy mode, in mm
	 */
	public function testBlockHeight($html, $height, $legacy)
	{
		$this->assertEqualsWithDelta($height, $this->heightOf($html), 0.001, 'standard');
		$this->assertEqualsWithDelta($legacy, $this->heightOf($html, CssMode::LEGACY), 0.01, 'legacy');
	}

	/**
	 * @return array[] Documents of three lines of 10pt text, their height, and their height in legacy mode
	 */
	public function providerHeights()
	{
		$lines = 'aa<br>bb<br>cc';

		return [
			'zero on a paragraph' => ['<p style="line-height: 0">' . $lines . '</p>', 0.0, 14.04],
			'zero length on a paragraph' => ['<p style="line-height: 0px">' . $lines . '</p>', 0.0, 14.08],
			'zero inherited from a div' => ['<div style="line-height: 0"><p>' . $lines . '</p></div>', 0.0, 14.04],
			'zero in a table cell' => [
				'<table style="border-collapse: collapse"><tr><td style="line-height: 0; padding: 0">' . $lines . '</td></tr></table>',
				0.0,
				14.04,
			],
			'zero on a table' => [
				'<table style="border-collapse: collapse; line-height: 0"><tr><td style="padding: 0">' . $lines . '</td></tr></table>',
				0.0,
				14.04,
			],
			'zero on a table in a double-spaced div' => [
				'<div style="line-height: 2"><table style="border-collapse: collapse; line-height: 0"><tr><td style="padding: 0">' . $lines . '</td></tr></table></div>',
				0.0,
				21.17,
			],
			'a length smaller than the font' => ['<p style="line-height: 1mm">' . $lines . '</p>', 3.0, 5.16],
			'a factor smaller than the font' => ['<p style="line-height: 0.5">' . $lines . '</p>', 1.5 * 10 / Mpdf::SCALE, 6.31],
		];
	}

	/**
	 * A negative line-height is ignored. In cssMode legacy it is applied, and squashes the lines
	 *
	 * @dataProvider providerNegative
	 *
	 * @param string $negative A document with a negative line-height
	 * @param string $without The same document without it
	 * @param float $legacy How far the document with it moves down the page in legacy mode, in mm
	 */
	public function testNegativeLineHeight($negative, $without, $legacy)
	{
		$this->assertEqualsWithDelta($this->heightOf($without), $this->heightOf($negative), 0.001, 'standard');
		$this->assertEqualsWithDelta($legacy, $this->heightOf($negative, CssMode::LEGACY), 0.01, 'legacy');
	}

	/**
	 * @return array[] Documents with a negative line-height, the same documents without it, and the height of the
	 *                 first in legacy mode
	 */
	public function providerNegative()
	{
		$lines = 'aa<br>bb<br>cc';

		return [
			'number' => [
				'<div style="line-height: 2"><p style="line-height: -1">' . $lines . '</p></div>',
				'<div style="line-height: 2"><p>' . $lines . '</p></div>',
				3.27,
			],
			'length' => [
				'<div style="line-height: 2"><p style="line-height: -2mm">' . $lines . '</p></div>',
				'<div style="line-height: 2"><p>' . $lines . '</p></div>',
				0.66,
			],
			'font shorthand' => [
				'<div style="line-height: 2"><p style="font: 10pt/-1 serif">' . $lines . '</p></div>',
				'<div style="line-height: 2"><p>' . $lines . '</p></div>',
				3.27,
			],
			'table cell' => [
				'<table><tr><td style="line-height: -1">' . $lines . '</td></tr></table>',
				'<table><tr><td>' . $lines . '</td></tr></table>',
				5.03,
			],
		];
	}

	/**
	 * @param string $html A document
	 * @param string $mode A CssMode value
	 *
	 * @return float How far it moves down the page, in mm, with paragraphs at 10pt and no margins
	 */
	private function heightOf($html, $mode = CssMode::STANDARD)
	{
		$mpdf = new Mpdf(['cssMode' => $mode]);
		$mpdf->WriteHTML('<style>p { margin: 0; font-size: 10pt } td { font-size: 10pt }</style>');

		$y = $mpdf->y;
		$mpdf->WriteHTML($html);

		return $mpdf->y - $y;
	}

}
