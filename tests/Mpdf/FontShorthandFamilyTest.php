<?php

namespace Mpdf;

/**
 * The family named by the font shorthand or <font face> is read as font-family reads it: a name is kept whole, the
 * list is tried in order, and when mPDF knows none of it the text keeps the family it inherits rather than being
 * drawn in the first registered font.
 */
class FontShorthandFamilyTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider providerFamilies
	 *
	 * @param string $html The document
	 * @param string $family The family its text should be drawn in
	 */
	public function testTextIsDrawnInTheFamilyNamed($html, $family)
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML($html);

		$this->assertSame(['Hello world'], $mpdf->drawnText);
		$this->assertSame([$family], $mpdf->drawnFontFamily);
	}

	/**
	 * @return array[] Documents and the family their text should be drawn in
	 */
	public function providerFamilies()
	{
		return [
			'shorthand, unregistered family' => [
				'<style>div { font-family: dejavuserif } p { font: 12pt Roboto }</style><div><p>Hello world</p></div>',
				'dejavuserif',
			],
			'shorthand, quoted name with spaces' => [
				'<style>p { font: 12pt "DejaVu Sans Mono" }</style><p>Hello world</p>',
				'dejavusansmono',
			],
			'shorthand, unquoted name with spaces' => [
				'<style>p { font: 12pt DejaVu Sans Mono }</style><p>Hello world</p>',
				'dejavusansmono',
			],
			'shorthand, list with unregistered names first' => [
				'<style>p { font: italic 12pt/1.4 Roboto, "Segoe UI", "DejaVu Serif", sans-serif }</style><p>Hello world</p>',
				'dejavuserif',
			],
			'font face, unregistered family' => [
				'<p style="font-family: dejavuserif"><font face="Segoe UI">Hello world</font></p>',
				'dejavuserif',
			],
			'font face, name with spaces' => [
				'<p><font face="DejaVu Sans Mono">Hello world</font></p>',
				'dejavusansmono',
			],
			'font face, list with an unregistered name first' => [
				'<p><font face="Roboto, DejaVu Serif">Hello world</font></p>',
				'dejavuserif',
			],
		];
	}

	/**
	 * The size given with an unregistered family still applies
	 */
	public function testSizeAppliesWhenTheFamilyIsUnregistered()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<style>p { font: 18pt Roboto }</style><p>Hello world</p>');

		$this->assertSame([18.0], array_map('floatval', $mpdf->drawnFontSize));
	}

}
