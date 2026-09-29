<?php

namespace Mpdf;

/**
 * line-height: 0 gives lines no height, rather than being read as normal, a line height smaller than the font is
 * kept rather than stretched down to the baseline, and a negative line-height is ignored.
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
	 * @dataProvider providerHeights
	 *
	 * @param string $html The document
	 * @param float $height How far it moves down the page, in mm
	 */
	public function testBlockHeight($html, $height)
	{
		$this->assertEqualsWithDelta($height, $this->heightOf($html), 0.001);
	}

	/**
	 * @return array[] Documents of three lines of 10pt text, and their height
	 */
	public function providerHeights()
	{
		$lines = 'aa<br>bb<br>cc';

		return [
			'zero on a paragraph' => ['<p style="line-height: 0">' . $lines . '</p>', 0.0],
			'zero length on a paragraph' => ['<p style="line-height: 0px">' . $lines . '</p>', 0.0],
			'zero inherited from a div' => ['<div style="line-height: 0"><p>' . $lines . '</p></div>', 0.0],
			'zero in a table cell' => [
				'<table style="border-collapse: collapse"><tr><td style="line-height: 0; padding: 0">' . $lines . '</td></tr></table>',
				0.0,
			],
			'zero on a table' => [
				'<table style="border-collapse: collapse; line-height: 0"><tr><td style="padding: 0">' . $lines . '</td></tr></table>',
				0.0,
			],
			'a length smaller than the font' => ['<p style="line-height: 1mm">' . $lines . '</p>', 3.0],
			'a factor smaller than the font' => ['<p style="line-height: 0.5">' . $lines . '</p>', 1.5 * 10 / Mpdf::SCALE],
		];
	}

	/**
	 * @dataProvider providerNegative
	 *
	 * @param string $negative A document with a negative line-height
	 * @param string $without The same document without it
	 */
	public function testNegativeLineHeightIsIgnored($negative, $without)
	{
		$this->assertEqualsWithDelta($this->heightOf($without), $this->heightOf($negative), 0.001);
	}

	/**
	 * @return array[] Documents with a negative line-height, and the same documents without it
	 */
	public function providerNegative()
	{
		$lines = 'aa<br>bb<br>cc';

		return [
			'number' => [
				'<div style="line-height: 2"><p style="line-height: -1">' . $lines . '</p></div>',
				'<div style="line-height: 2"><p>' . $lines . '</p></div>',
			],
			'length' => [
				'<div style="line-height: 2"><p style="line-height: -2mm">' . $lines . '</p></div>',
				'<div style="line-height: 2"><p>' . $lines . '</p></div>',
			],
			'font shorthand' => [
				'<div style="line-height: 2"><p style="font: 10pt/-1 serif">' . $lines . '</p></div>',
				'<div style="line-height: 2"><p>' . $lines . '</p></div>',
			],
			'table cell' => [
				'<table><tr><td style="line-height: -1">' . $lines . '</td></tr></table>',
				'<table><tr><td>' . $lines . '</td></tr></table>',
			],
		];
	}

	/**
	 * @param string $html A document
	 *
	 * @return float How far it moves down the page, in mm, with paragraphs at 10pt and no margins
	 */
	private function heightOf($html)
	{
		$mpdf = new Mpdf();
		$mpdf->WriteHTML('<style>p { margin: 0; font-size: 10pt } td { font-size: 10pt }</style>');

		$y = $mpdf->y;
		$mpdf->WriteHTML($html);

		return $mpdf->y - $y;
	}

}
