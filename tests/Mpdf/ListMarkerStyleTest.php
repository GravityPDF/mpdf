<?php

namespace Mpdf;

/**
 * Under the standard CSS mode a list's markers take the counter styles of CSS Counter Styles that mPDF had no
 * conversion for, a string as the marker, <ol reversed>, <li value> and the list-item counter's counter-reset,
 * counter-increment and counter-set. The legacy mode draws the markers as mPDF v7 did.
 */
class ListMarkerStyleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each item's marker is drawn as a browser draws it
	 *
	 * @dataProvider providerMarkers
	 *
	 * @param string $html A list
	 * @param string[] $markers The markers its items should have, in order
	 */
	public function testMarkersAreDrawnAsInABrowser($html, array $markers)
	{
		$this->assertSame($markers, $this->markersOf($html));
	}

	/**
	 * @return array[] Lists and the markers their items should have
	 */
	public function providerMarkers()
	{
		$three = '<li>a</li><li>b</li><li>c</li>';

		return [
			'decimal-leading-zero' => ['<ol style="list-style-type: decimal-leading-zero"><li>a</li></ol>', ['01.']],
			'decimal-leading-zero past nine' => ['<ol start="10" style="list-style-type: decimal-leading-zero"><li>a</li></ol>', ['10.']],
			'upper-greek' => ['<ol style="list-style-type: upper-greek"><li>a</li><li>b</li></ol>', ['Α.', 'Β.']],
			'armenian' => ['<ol style="list-style-type: armenian"><li>a</li></ol>', ['Ա.']],
			'lower-armenian' => ['<ol start="12" style="list-style-type: lower-armenian"><li>a</li></ol>', ['ժբ.']],
			'georgian' => ['<ol start="9" style="list-style-type: georgian"><li>a</li></ol>', ['თ.']],
			'disclosure-closed' => ['<ul style="list-style-type: disclosure-closed"><li>a</li></ul>', ['▸']],
			'disclosure-open' => ['<ul style="list-style-type: disclosure-open"><li>a</li></ul>', ['▾']],
			'the shorthand reads the whole keyword' => ['<ol style="list-style: decimal-leading-zero"><li>a</li></ol>', ['01.']],
			'a string' => ['<ul style="list-style-type: \'→ \'"><li>a</li></ul>', ['→ ']],
			'a string keeps its case' => ['<ul style="list-style-type: \'Note: \'"><li>a</li></ul>', ['Note: ']],
			'a string in the shorthand' => ['<style>ul { list-style: "Ab" }</style><ul><li>a</li></ul>', ['Ab']],
			'a string with an escape' => ['<style>ul { list-style-type: "\2192  " }</style><ul><li>a</li></ul>', ['→ ']],
			'a string naming a code point' => ['<ul style="list-style-type: \'U+2022\'"><li>a</li></ul>', ['U+2022']],
			'reversed' => ['<ol reversed>' . $three . '</ol>', ['3.', '2.', '1.']],
			'reversed with start' => ['<ol reversed start="10"><li>a</li><li>b</li></ol>', ['10.', '9.']],
			'reversed past zero' => ['<ol reversed start="1"><li>a</li><li>b</li></ol>', ['1.', '0.']],
			'reversed counts only its own items' => ['<ol reversed><li>a<ol><li>x</li><li>y</li></ol></li><li>b</li></ol>', ['2.', '1.', '2.', '1.']],
			'value' => ['<ol><li value="7">a</li><li>b</li></ol>', ['7.', '8.']],
			'value in a reversed list' => ['<ol reversed><li>a</li><li value="10">b</li><li>c</li></ol>', ['3.', '10.', '9.']],
			'start' => ['<ol start="3"><li>a</li></ol>', ['3.']],
			'counter-reset' => ['<ol style="counter-reset: list-item 4"><li>a</li></ol>', ['5.']],
			'counter-reset beats start' => ['<ol start="9" style="counter-reset: list-item 4"><li>a</li></ol>', ['5.']],
			'counter-reset reversed' => ['<ol style="counter-reset: reversed(list-item)">' . $three . '</ol>', ['3.', '2.', '1.']],
			'counter-reset naming another counter' => ['<ol start="3" style="counter-reset: chapter 4"><li>a</li></ol>', ['3.']],
			'counter-increment' => ['<style>li { counter-increment: list-item 2 }</style><ol>' . $three . '</ol>', ['2.', '4.', '6.']],
			'counter-increment of another counter' => ['<style>li { counter-increment: chapter 2 }</style><ol>' . $three . '</ol>', ['1.', '2.', '3.']],
			'counter-set' => ['<ol><li>a</li><li style="counter-set: list-item 20">b</li><li>c</li></ol>', ['1.', '20.', '21.']],
			'counter-set beats value' => ['<ol><li value="7" style="counter-set: list-item 20">a</li></ol>', ['20.']],
			'counter-reset on an item' => ['<ol><li>a</li><li style="counter-reset: list-item 10">b</li><li>c</li></ol>', ['1.', '11.', '12.']],
			'upper-greek out of range' => ['<ol reversed start="1" style="list-style-type: upper-greek"><li>a</li><li>b</li></ol>', ['Α.', '0.']],
		];
	}

	/**
	 * The legacy mode draws a style mPDF had no conversion for, and a string, as a number, and reads no list attribute
	 * but start, and no counter property
	 *
	 * @dataProvider providerLegacyMarkers
	 *
	 * @param string $html A list
	 * @param string[] $markers The markers its items should have, in order
	 */
	public function testLegacyModeDrawsMarkersAsBefore($html, array $markers)
	{
		$this->assertSame($markers, $this->markersOf($html, ['cssMode' => CssMode::LEGACY]));
	}

	/**
	 * @return array[] Lists and the markers their items should have in the legacy mode
	 */
	public function providerLegacyMarkers()
	{
		return [
			'decimal-leading-zero' => ['<ol style="list-style-type: decimal-leading-zero"><li>a</li></ol>', ['1.']],
			'upper-greek' => ['<ol style="list-style-type: upper-greek"><li>a</li></ol>', ['1.']],
			'a string' => ['<ul style="list-style-type: \'→ \'"><li>a</li></ul>', ['1.']],
			'reversed' => ['<ol reversed><li>a</li><li>b</li></ol>', ['1.', '2.']],
			'value' => ['<ol><li value="7">a</li></ol>', ['1.']],
			'counter-reset' => ['<ol style="counter-reset: list-item 4"><li>a</li></ol>', ['1.']],
		];
	}

	/**
	 * A list in a table cell, whose markers are written into the cell's text, takes the same styles and numbers
	 */
	public function testListInATableCell()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<table><tr><td><ol reversed style="list-style-type: upper-greek"><li>a</li><li>b</li></ol><ul style="list-style-type: \'→ \'"><li>s</li></ul></td></tr></table>');

		$this->assertSame(['Β. ', 'a', 'Α. ', 'b', '→ ', 's'], $mpdf->drawnText);
	}

	/**
	 * A right-to-left list puts the suffix before the number, as it did, and draws disclosure-closed pointing left
	 */
	public function testRightToLeftList()
	{
		$this->assertSame(['.Α', '.Β'], $this->markersOf('<ol dir="rtl" style="list-style-type: upper-greek"><li>a</li><li>b</li></ol>'));
		$this->assertSame(['◂'], $this->markersOf('<ul dir="rtl" style="list-style-type: disclosure-closed"><li>a</li></ul>'));
	}

	/**
	 * mPDF's own list_auto_mode sizes the list's padding to the widest marker, which a string or a style drawn here
	 * now gives
	 */
	public function testPaddingFitsTheMarkerInMpdfListMode()
	{
		$narrow = $this->contentLeft('<ul style="list-style-type: \'-\'"><li>a</li></ul>');
		$wide = $this->contentLeft('<ul style="list-style-type: \'Chapter one: \'"><li>a</li></ul>');

		$this->assertGreaterThan($narrow + 10, $wide);
	}

	/**
	 * @param string $html A list
	 * @param array $config
	 *
	 * @return string[] The text of each marker drawn outside its item, in order
	 */
	private function markersOf($html, array $config = [])
	{
		$mpdf = new ListMarkerTextMpdf($config);
		$mpdf->WriteHTML($html);

		return $mpdf->markers;
	}

	/**
	 * @param string $html A list
	 *
	 * @return float Where the first item's text starts, in mm, under list_auto_mode mpdf
	 */
	private function contentLeft($html)
	{
		$mpdf = new TextRecordingMpdf(['list_auto_mode' => 'mpdf']);
		$mpdf->WriteHTML($html);

		return $mpdf->drawnBoxes[array_search('a', $mpdf->drawnText, true)][1];
	}
}
