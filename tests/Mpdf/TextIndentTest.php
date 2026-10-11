<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * text-indent under cssMode standard: hanging indents every line but the first, each-line indents the first line after
 * a forced break too, and a table cell's first line is indented. Under cssMode legacy text-indent indents the first line
 * of a block only, as before, and a value with a keyword indents nothing.
 *
 * Each test reads where each line starts from where Cell() drew its text.
 */
class TextIndentTest extends TestCase
{

	/**
	 * Five lines of text in a 30mm block
	 */
	const TEXT = 'aaaa bbbb cccc dddd eeee ffff gggg hhhh iiii jjjj';

	/**
	 * Where each line starts, from the start of the line that starts furthest left
	 *
	 * @dataProvider providerIndents
	 *
	 * @param string $html
	 * @param float[] $expected How far each line is indented, in mm
	 * @param float[]|null $legacy The same under cssMode legacy, or null for every line at 0
	 */
	public function testIndents($html, array $expected, $legacy = null)
	{
		$this->assertIndents($expected, $this->indents($html, CssMode::STANDARD));
		$legacyIndents = $this->indents($html, CssMode::LEGACY);
		$this->assertIndents($legacy === null ? array_fill(0, count($legacyIndents), 0) : $legacy, $legacyIndents);
	}

	/**
	 * @return array[] Documents, how far each of their lines is indented, and the same under cssMode legacy
	 */
	public function providerIndents()
	{
		$text = self::TEXT;

		return [
			'a length' => [
				'<div style="width: 30mm; text-indent: 10mm">' . $text . '</div>',
				[10, 0, 0, 0],
				[10, 0, 0, 0],
			],
			'hanging' => [
				'<div style="width: 30mm; text-indent: 10mm hanging">' . $text . '</div>',
				[0, 10, 10, 10, 10],
			],
			'hanging, keyword first and in capitals' => [
				'<div style="width: 30mm; text-indent: HANGING 10mm">' . $text . '</div>',
				[0, 10, 10, 10, 10],
			],
			'hanging, as a percentage of the containing block' => [
				'<div style="width: 50mm"><div style="width: 30mm; text-indent: 20% hanging">' . $text . '</div></div>',
				[0, 10, 10, 10, 10],
			],
			'hanging, inherited by a paragraph' => [
				'<div style="width: 30mm; text-indent: 10mm hanging"><p>' . $text . '</p></div>',
				[0, 10, 10, 10, 10],
			],
			'each-line' => [
				'<div style="width: 30mm; text-indent: 10mm each-line">aaaa bbbb cccc dddd<br>eeee ffff gggg hhhh</div>',
				[10, 0, 10, 0],
			],
			'each-line after an empty line' => [
				'<div style="width: 30mm; text-indent: 10mm each-line">aaaa<br><br>eeee ffff gggg hhhh</div>',
				[10, 10, 0],
			],
			'hanging each-line' => [
				'<div style="width: 30mm; text-indent: 10mm hanging each-line">aaaa bbbb cccc dddd<br>eeee ffff gggg hhhh</div>',
				[0, 10, 0, 10],
			],
			'without each-line, a line after a break' => [
				'<div style="width: 30mm; text-indent: 10mm">aaaa bbbb cccc dddd<br>eeee ffff gggg hhhh</div>',
				[10, 0, 0, 0],
				[10, 0, 0, 0],
			],
			'hanging, the lines after a block inside' => [
				'<div style="width: 30mm; text-indent: 10mm hanging">aaaa bbbb cccc dddd<div style="text-indent: 0">eeee</div>ffff gggg hhhh iiii</div>',
				[0, 10, 0, 10, 10],
			],
			'a table cell' => [
				'<table><tr><td style="width: 30mm; text-indent: 10mm">' . $text . '</td></tr></table>',
				[10, 0, 0, 0],
			],
			'a table cell, hanging' => [
				'<table><tr><td style="width: 30mm; text-indent: 10mm hanging">' . $text . '</td></tr></table>',
				[0, 10, 10, 10, 10],
			],
			'a table cell, each-line' => [
				'<table><tr><td style="width: 30mm; text-indent: 10mm each-line">aaaa bbbb cccc dddd<br>eeee ffff gggg hhhh</td></tr></table>',
				[10, 0, 10, 0],
			],
			'a table cell, in em' => [
				'<table><tr><td style="width: 30mm; font-size: 10pt; text-indent: 2em">' . $text . '</td></tr></table>',
				[20 / Mpdf::SCALE, 0, 0, 0],
				[0, 0, 0],
			],
			'a table cell, inherited from the table' => [
				'<table style="text-indent: 10mm"><tr><td style="width: 30mm">' . $text . '</td></tr></table>',
				[10, 0, 0, 0],
			],
			'a table cell holding a paragraph' => [
				'<table><tr><td style="width: 30mm; text-indent: 10mm"><p>' . $text . '</p></td></tr></table>',
				[10, 0, 0, 0],
			],
		];
	}

	/**
	 * A negative hanging indent moves every line but the first left, into the padding mPDF makes room for it in
	 */
	public function testNegativeHanging()
	{
		$mpdf = $this->draw('<div style="width: 40mm; text-indent: -10mm hanging">' . self::TEXT . '</div>', CssMode::STANDARD);
		$starts = $this->starts($mpdf);

		$this->assertEqualsWithDelta(25.0, $starts[0], 0.001);
		$this->assertEqualsWithDelta(15.0, $starts[1], 0.001);
		$this->assertEqualsWithDelta(15.0, end($starts), 0.001);
	}

	/**
	 * A right-to-left cell's first line is indented from its right edge
	 */
	public function testRightToLeftCell()
	{
		$mpdf = $this->draw('<table><tr><td style="width: 30mm; direction: rtl; text-indent: 10mm">' . self::TEXT . '</td></tr></table>', CssMode::STANDARD);
		$ends = array_map(function ($box) {
			return $box[2];
		}, $mpdf->drawnBoxes);

		$this->assertEqualsWithDelta(10.0, $ends[1] - $ends[0], 0.001);
		$this->assertEqualsWithDelta($ends[1], end($ends), 0.001);
	}

	/**
	 * A cell sized to its content is wide enough for its indented first line, which stays one line
	 */
	public function testCellWidthHoldsTheIndent()
	{
		$mpdf = $this->draw('<table><tr><td style="text-indent: 20mm">aaaa bbbb</td></tr></table>', CssMode::STANDARD);
		$plain = $this->draw('<table><tr><td>aaaa bbbb</td></tr></table>', CssMode::STANDARD);

		$this->assertSame(['aaaa bbbb'], array_map('trim', $mpdf->drawnText));
		$this->assertEqualsWithDelta($plain->drawnBoxes[0][1] + 20, $mpdf->drawnBoxes[0][1], 0.001);
	}

	/**
	 * A header row repeated on the next page keeps its cells' indent
	 */
	public function testRepeatedHeaderRow()
	{
		$rows = str_repeat('<tr><td>row</td></tr>', 80);
		$mpdf = $this->draw('<table><thead><tr><th style="text-indent: 10mm; text-align: left">head</th></tr></thead>' . $rows . '</table>', CssMode::STANDARD);

		$heads = [];
		foreach ($mpdf->drawnText as $i => $text) {
			if (trim($text) === 'head') {
				$heads[$mpdf->drawnBoxes[$i][0]] = $mpdf->drawnBoxes[$i][1];
			}
		}
		$row = $mpdf->drawnBoxes[array_search('row', array_map('trim', $mpdf->drawnText), true)][1];

		$this->assertCount(2, $heads);
		$this->assertEqualsWithDelta($row + 10, $heads[1], 0.001);
		$this->assertEqualsWithDelta($row + 10, $heads[2], 0.001);
	}

	/**
	 * Each pair of indents is equal
	 *
	 * @param float[] $expected
	 * @param float[] $actual
	 */
	private function assertIndents(array $expected, array $actual)
	{
		$this->assertCount(count($expected), $actual, 'The text is drawn on another number of lines');
		foreach ($expected as $line => $indent) {
			$this->assertEqualsWithDelta($indent, $actual[$line], 0.001, sprintf('Line %d is indented by the wrong amount', $line));
		}
	}

	/**
	 * @param string $html
	 * @param string $mode A CssMode value
	 *
	 * @return float[] How far each line drawn starts right of where the line that starts furthest left does, in mm
	 */
	private function indents($html, $mode)
	{
		$starts = $this->starts($this->draw($html, $mode));
		$left = min($starts);

		return array_map(function ($start) use ($left) {
			return $start - $left;
		}, $starts);
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written
	 *
	 * @return float[] Where each line drawn starts, in order, in mm
	 */
	private function starts(TextRecordingMpdf $mpdf)
	{
		return array_map(function ($box) {
			return $box[1];
		}, $mpdf->drawnBoxes);
	}

	/**
	 * @param string $html
	 * @param string $mode A CssMode value
	 *
	 * @return TextRecordingMpdf The document, written, with paragraphs that have no margins
	 */
	private function draw($html, $mode)
	{
		$mpdf = new TextRecordingMpdf(['cssMode' => $mode]);
		$mpdf->WriteHTML('<style>p { margin: 0 } td, th { padding: 0 }</style>' . $html);

		return $mpdf;
	}
}
