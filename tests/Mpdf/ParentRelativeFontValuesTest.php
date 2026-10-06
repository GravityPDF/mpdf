<?php

namespace Mpdf;

/**
 * font-weight as a number, bolder and lighter, and font-size: larger and smaller, which the standard CSS mode computes
 * from the parent element's values, nested two and three deep in each place a document can put them. The legacy mode
 * reads only normal and bold, and ignores larger and smaller, as it always has
 */
class ParentRelativeFontValuesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/**
	 * @dataProvider providerNestedValues
	 *
	 * @param string $mode A CssMode constant
	 * @param string $context As inContext() takes it
	 * @param string $html Elements nested around the pieces of text aa, bb and cc
	 * @param array $expected For each piece of text, its font style ('' or 'B') and size in points
	 */
	public function testNestedValues($mode, $context, $html, array $expected)
	{
		$mpdf = $this->render($this->inContext($context, $html), $mode);

		$this->assertDrawnInContext($context, $mpdf, array_keys($expected));
		$this->assertSame($expected, $this->drawnStyles($mpdf, array_keys($expected)));
	}

	/**
	 * A text circle reads larger and smaller against its parent's size, as a form field does, and in the legacy mode
	 * ignores them, keeping the paragraph's size rather than being drawn at 0pt
	 */
	public function testATextCircleReadsLargerAgainstItsParentsSize()
	{
		$html = '<p style="font-size: 14pt"><textcircle r="30mm" top-text="Circular" style="font-size: larger" /></p>';
		foreach ([CssMode::STANDARD => '16.800', CssMode::LEGACY => '14.000'] as $mode => $size) {
			$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => $mode]);
			$mpdf->SetCompression(false);
			$mpdf->WriteHTML($html);

			$this->assertStringContainsString(' ' . $size . ' Tf', $mpdf->Output('', 'S'), $mode);
		}
	}

	/**
	 * @return array[] Each case of nested values in each context, under each CSS mode, with the style and size each
	 * piece of text is drawn in
	 */
	public function providerNestedValues()
	{
		$contexts = ['flow', 'table cell', 'nested table', 'list item', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'];

		// Each case: the HTML, what standard draws, and what legacy draws, with legacy's in a table cell where it differs
		$cases = [
			'bolder, bolder and lighter blocks' => [
				'<div style="font-weight: bolder">aa<div style="font-weight: bolder">bb<div style="font-weight: lighter">cc</div></div></div>',
				['aa' => ['B', 11.0], 'bb' => ['B', 11.0], 'cc' => ['B', 11.0]], // 700, 900, 700
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'lighter and lighter spans inside 700' => [
				'<p><span style="font-weight: 700">aa<span style="font-weight: lighter">bb<span style="font-weight: lighter">cc</span></span></span></p>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]], // 700, 400, 100
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'bolder paragraph and span inside 300' => [
				'<div style="font-weight: 300">aa<p style="font-weight: bolder">bb<span style="font-weight: bolder">cc</span></p></div>',
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // 300, 400, 700
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'bolder strong and normal span inside b' => [
				'<p><b>aa<strong style="font-weight: bolder">bb<span style="font-weight: normal">cc</span></strong></b></p>',
				['aa' => ['B', 11.0], 'bb' => ['B', 11.0], 'cc' => ['', 11.0]], // 700, 900, 400
				['aa' => ['B', 11.0], 'bb' => ['B', 11.0], 'cc' => ['', 11.0]],
			],
			'lighter span and b inside strong' => [
				'<p><strong>aa<span style="font-weight: lighter">bb<b>cc</b></span></strong></p>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // 700, 400, 700
				['aa' => ['B', 11.0], 'bb' => ['B', 11.0], 'cc' => ['B', 11.0]],
			],
			'lighter heading inside 900' => [
				'<div style="font-weight: 900"><h2 style="font-weight: lighter">aa<span style="font-weight: lighter">bb<span style="font-weight: bolder">cc</span></span></h2></div>',
				['aa' => ['B', 16.5], 'bb' => ['', 16.5], 'cc' => ['B', 16.5]], // 700, 400, 700
				['aa' => ['', 16.5], 'bb' => ['', 16.5], 'cc' => ['', 16.5]],
			],
			'lighter heading' => [
				'<h1 style="font-weight: lighter">aa<span style="font-weight: bolder">bb</span></h1>',
				['aa' => ['', 22.0], 'bb' => ['', 22.0]], // 100, 400
				['aa' => ['', 22.0], 'bb' => ['', 22.0]],
			],
			'bolder span inside a heading' => [
				'<h3>aa<span style="font-weight: bolder">bb<span style="font-weight: lighter">cc</span></span></h3>',
				['aa' => ['B', 12.87], 'bb' => ['B', 12.87], 'cc' => ['B', 12.87]], // 700, 900, 700
				['aa' => ['B', 12.87], 'bb' => ['B', 12.87], 'cc' => ['B', 12.87]],
			],
			'numbers either side of bold' => [
				'<div style="font-weight: 600">aa<div style="font-weight: 500">bb<div style="font-weight: bolder">cc</div></div></div>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // 600, 500, 700
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'weights in the font shorthand' => [
				'<div style="font: 600 11pt serif">aa<p style="font: lighter 11pt serif">bb<span style="font: bolder 11pt serif">cc</span></p></div>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // 600, 400, 700
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // legacy reads bolder as bold
			],
			'larger block and smaller span' => [
				'<div style="font-size: 20pt">aa<div style="font-size: larger">bb<span style="font-size: smaller">cc</span></div></div>',
				['aa' => ['', 20.0], 'bb' => ['', 24.0], 'cc' => ['', 20.0]],
				['aa' => ['', 20.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
				// A block in a table cell is styled as an inline element, which keeps its parent's size
				['aa' => ['', 20.0], 'bb' => ['', 20.0], 'cc' => ['', 20.0]],
			],
			'smaller and smaller spans' => [
				'<p style="font-size: 10pt">aa<span style="font-size: smaller">bb<span style="font-size: smaller">cc</span></span></p>',
				['aa' => ['', 10.0], 'bb' => ['', 8.33], 'cc' => ['', 6.94]],
				['aa' => ['', 10.0], 'bb' => ['', 10.0], 'cc' => ['', 10.0]],
			],
			'larger and larger blocks' => [
				'<div style="font-size: 10pt">aa<div style="font-size: larger">bb<p style="font-size: larger">cc</p></div></div>',
				['aa' => ['', 10.0], 'bb' => ['', 12.0], 'cc' => ['', 14.4]],
				['aa' => ['', 10.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
				['aa' => ['', 10.0], 'bb' => ['', 10.0], 'cc' => ['', 10.0]],
			],
		];

		$data = [];
		foreach ($cases as $name => $case) {
			foreach ($contexts as $context) {
				$legacy = isset($case[3]) && in_array($context, ['table cell', 'nested table'], true) ? $case[3] : $case[2];
				$data[$name . ' in ' . $context . ', standard'] = [CssMode::STANDARD, $context, $case[0], $case[1]];
				$data[$name . ' in ' . $context . ', legacy'] = [CssMode::LEGACY, $context, $case[0], $legacy];
			}
		}

		return $data;
	}

	/**
	 * @dataProvider providerStartingValues
	 *
	 * @param string $mode A CssMode constant
	 * @param string $html A document whose values are computed from those of the body, a table or a list
	 * @param array $expected For each piece of text, its font style ('' or 'B') and size in points
	 */
	public function testValuesFromTheBodyTablesAndLists($mode, $html, array $expected)
	{
		$mpdf = $this->render($html, $mode);

		$this->assertSame($expected, $this->drawnStyles($mpdf, array_keys($expected)));
	}

	/**
	 * @return array[] Documents that compute values from the body, a table, a header cell or a list, under each CSS
	 * mode, with the style and size each piece of text is drawn in
	 */
	public function providerStartingValues()
	{
		$cases = [
			'lighter paragraph in an 800 body' => [
				'<style>body { font-weight: 800 }</style><p style="font-weight: lighter">aa</p><p>bb</p>',
				['aa' => ['B', 11.0], 'bb' => ['B', 11.0]], // 700, 800
				['aa' => ['', 11.0], 'bb' => ['', 11.0]],
			],
			'header cells' => [
				'<table><tr><th>aa</th><th style="font-weight: lighter">bb</th><th style="font-weight: normal">cc</th></tr></table>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]], // 700, 100, 400
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'cells and their content in a 600 table' => [
				'<table style="font-weight: 600"><tr><td>aa</td><td style="font-weight: lighter">bb<span style="font-weight: bolder">cc</span></td></tr></table>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // 600, 400, 700
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'cells in a table inside an 800 table' => [
				'<table style="font-weight: 800"><tr><td>x<table style="font-weight: lighter"><tr><td>aa</td><td style="font-weight: lighter">bb</td></tr></table></td></tr></table>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0]], // 700, 400
				['aa' => ['', 11.0], 'bb' => ['', 11.0]],
			],
			'cells in a larger table' => [
				'<table style="font-size: larger"><tr><td>aa</td><td style="font-size: smaller">bb<span style="font-size: smaller">cc</span></td></tr></table>',
				['aa' => ['', 13.2], 'bb' => ['', 11.0], 'cc' => ['', 9.17]],
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'list items in a 900 list' => [
				'<ul style="font-weight: 900"><li style="font-weight: lighter">aa<ul><li style="font-weight: lighter">bb</li><li>cc</li></ul></li></ul>',
				['aa' => ['B', 11.0], 'bb' => ['', 11.0], 'cc' => ['B', 11.0]], // 700, 400, 700
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
			'larger list items' => [
				'<ul style="font-size: 10pt"><li style="font-size: larger">aa<ul><li style="font-size: larger">bb</li></ul></li></ul>',
				['aa' => ['', 12.0], 'bb' => ['', 14.4]],
				['aa' => ['', 11.0], 'bb' => ['', 11.0]],
			],
			'larger and smaller form fields' => [
				'<p style="font-size: 14pt"><input type="text" style="font-size: larger" value="aa" /> <select style="font-size: smaller"><option>bb</option></select> <textarea style="font-size: larger">cc</textarea></p>',
				// From the paragraph's 14pt, as each font size is computed from the parent's
				['aa' => ['', 16.8], 'bb' => ['', 11.67], 'cc' => ['', 16.8]],
				// Ignored rather than drawn at 0pt, so each field keeps the paragraph's size, as an ignored size does elsewhere
				['aa' => ['', 14.0], 'bb' => ['', 14.0], 'cc' => ['', 14.0]],
			],
			'weights that are not weights keep the parent\'s' => [
				'<div style="font-weight: 600">aa<p style="font-weight: 1001">bb</p><p style="font-weight: heavy">cc</p></div>',
				['aa' => ['B', 11.0], 'bb' => ['B', 11.0], 'cc' => ['B', 11.0]],
				['aa' => ['', 11.0], 'bb' => ['', 11.0], 'cc' => ['', 11.0]],
			],
		];

		$data = [];
		foreach ($cases as $name => $case) {
			$data[$name . ', standard'] = [CssMode::STANDARD, $case[0], $case[1]];
			$data[$name . ', legacy'] = [CssMode::LEGACY, $case[0], $case[2]];
		}

		return $data;
	}

	/**
	 * Writes and closes a document in core-font mode, recording the font style and size each piece of text is drawn in.
	 * Closing it draws the footers
	 *
	 * @param string $html
	 * @param string $mode A CssMode constant
	 *
	 * @return FontStateRecordingMpdf
	 */
	private function render($html, $mode)
	{
		$mpdf = new FontStateRecordingMpdf(['mode' => 'c', 'cssMode' => $mode]);
		$mpdf->WriteHTML($html);
		$mpdf->Output('', 'S');

		return $mpdf;
	}

	/**
	 * The font style and size, rounded to hundredths of a point, the named pieces of text were drawn in. A header, a
	 * footer or a positioned block is laid out once to be measured before it is drawn, and the last drawing is the one
	 * kept
	 *
	 * @param FontStateRecordingMpdf $mpdf A document, written
	 * @param string[] $texts
	 *
	 * @return array For each piece of text, its style and size
	 */
	private function drawnStyles(FontStateRecordingMpdf $mpdf, array $texts)
	{
		$fontStyles = $this->keyedByText($mpdf, $mpdf->drawnFontStyle);
		$fontSizes = $this->keyedByText($mpdf, $mpdf->drawnFontSize);

		$styles = [];
		foreach ($texts as $text) {
			$this->assertArrayHasKey($text, $fontStyles, sprintf('"%s" is not drawn', $text));
			$styles[$text] = [$fontStyles[$text], round((float) $fontSizes[$text], 2)];
		}

		return $styles;
	}

	/**
	 * HTML in one of the places a document can put it
	 *
	 * @param string $context flow, table cell, nested table, list item, header, footer, positioned block, kept block (a
	 *                        page-break-inside: avoid block laid out again on the next page) or forced page break (after
	 *                        one, inside a block)
	 * @param string $html
	 *
	 * @return string
	 */
	private function inContext($context, $html)
	{
		switch ($context) {
			case 'table cell':
				return '<table><tr><td>' . $html . '</td></tr></table>';

			case 'nested table':
				return '<table><tr><td>outer<table><tr><td>' . $html . '</td></tr></table></td></tr></table>';

			case 'list item':
				return '<ul><li>' . $html . '</li></ul>';

			case 'header':
				return '<htmlpageheader name="h">' . $html . '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';

			case 'footer':
				return '<htmlpagefooter name="f">' . $html . '</htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>';

			case 'positioned block':
				return '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">' . $html . '</div>';

			case 'kept block':
				return '<div style="height: 200mm">filler</div><div style="page-break-inside: avoid"><p>kept</p>' . $html
					. '<div style="height: 60mm"></div></div>';

			case 'forced page break':
				return '<div><p>before the break</p><pagebreak />' . $html . '</div>';

			default:
				return $html;
		}
	}

	/**
	 * Pieces of text put in a context by inContext() are drawn where it puts them, so that a context cannot quietly
	 * stop testing what it is named for: the first above the body's text in the header, below the bottom margin in the
	 * footer, and at the positioned block's corner, and all of them on the second page once the kept block has been laid
	 * out again or after the forced page break
	 *
	 * @param string $context As inContext() takes it
	 * @param FontStateRecordingMpdf $mpdf The document, written
	 * @param string[] $texts
	 */
	private function assertDrawnInContext($context, FontStateRecordingMpdf $mpdf, array $texts)
	{
		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);
		list(, $x, , $y) = $boxes[$texts[0]];

		if ($context === 'header') {
			$this->assertLessThan($boxes['body'][3], $y);
		} elseif ($context === 'footer') {
			$this->assertGreaterThanOrEqual($mpdf->h - $mpdf->bMargin, $y);
		} elseif ($context === 'positioned block') {
			$this->assertEqualsWithDelta(20, $x, 0.01);
			$this->assertGreaterThanOrEqual(60, $y);
		}

		foreach ($texts as $text) {
			$this->assertSame(in_array($context, ['kept block', 'forced page break'], true) ? 2 : 1, $boxes[$text][0], $text);
		}
	}
}
