<?php

namespace Mpdf;

/**
 * Under the standard CSS mode a <q> is drawn between quotation marks, as a browser draws it: from quotes, or for its
 * language when quotes is auto, the next pair for a <q> inside another. The marks are text, drawn in the <q>'s style.
 * The legacy mode draws none.
 */
class QuotationMarksTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The quotation is drawn between the marks a browser would draw
	 *
	 * @dataProvider providerQuotations
	 *
	 * @param string $html
	 * @param string $text The text that should be drawn
	 */
	public function testQuotationMarks($html, $text)
	{
		$this->assertSame($text, $this->textOf($html));
	}

	/**
	 * @return array[] Documents with a <q> and the text that should be drawn
	 */
	public function providerQuotations()
	{
		return [
			'English by default' => ['<p><q>qq</q></p>', '“qq”'],
			'nested in English' => ['<p><q>a <q>b</q> c</q></p>', '“a ‘b’ c”'],
			'three deep takes the last pair' => ['<p><q>a <q>b <q>c</q></q></q></p>', '“a ‘b ‘c’’”'],
			'German' => ['<p lang="de"><q>a <q>b</q> c</q></p>', '„a ‚b‘ c“'],
			'French' => ['<p lang="fr"><q>qq</q></p>', '«qq»'],
			'the lang of the q' => ['<p><q lang="ja">qq</q></p>', '「qq」'],
			'a region of a language listed' => ['<p lang="de-AT"><q>qq</q></p>', '„qq“'],
			'a region listed on its own' => ['<p lang="pt-BR"><q>qq</q></p>', '“qq”'],
			'a language not listed' => ['<p lang="xx"><q>qq</q></p>', '“qq”'],
			'quotes' => ['<p style="quotes: \'«\' \'»\'"><q>qq</q></p>', '«qq»'],
			'quotes with two pairs' => ['<p style="quotes: \'(\' \')\' \'[\' \']\'"><q>a <q>b</q></q></p>', '(a [b])'],
			'quotes with escapes' => ['<style>p { quotes: "\201C" "\201D" }</style><p><q>qq</q></p>', '“qq”'],
			'quotes keeps case' => ['<p style="quotes: \'Q\' \'E\'"><q>qq</q></p>', 'QqqE'],
			'quotes with an ampersand' => ['<p style="quotes: \'&amp;lt;\' \'&amp;\'"><q>qq</q></p>', '&lt;qq&'],
			'quotes on the q' => ['<p><q style="quotes: \'«\' \'»\'">qq</q></p>', '«qq»'],
			'quotes: none' => ['<p style="quotes: none"><q>qq</q></p>', 'qq'],
			'quotes: auto' => ['<p lang="fr" style="quotes: auto"><q>qq</q></p>', '«qq»'],
			'an odd number of strings is invalid' => ['<p lang="fr" style="quotes: \'a\'"><q>qq</q></p>', '«qq»'],
			'ending in an element' => ['<p><q>a <b>b</b></q></p>', '“a b”'],
			'an empty q' => ['<p>x<q></q>y</p>', 'x“”y'],
		];
	}

	/**
	 * A <q> in a table cell is drawn between its marks too
	 */
	public function testQuotationInATableCell()
	{
		$this->assertSame('“qq”', $this->textOf('<table><tr><td><q>qq</q></td></tr></table>'));
	}

	/**
	 * The marks belong to the <q>, so take its colour
	 */
	public function testMarksTakeTheStyleOfTheQ()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<p>a <q style="color: #ff0000">qq</q> b</p>');

		$i = array_search('“qq”', $mpdf->drawnText, true);
		$this->assertNotFalse($i);
		$this->assertSame('1.000 0.000 0.000 rg', $mpdf->drawnColours[$i]);
	}

	/**
	 * A block laid out again after it ran onto another page under page-break-inside: avoid draws each mark once
	 */
	public function testMarksAreAddedOnceWhenABlockIsLaidOutAgain()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<div style="height: 260mm"></div><div style="page-break-inside: avoid"><p><q>a <q>b</q></q></p><p>' . str_repeat('Text. ', 200) . '</p></div>');

		$this->assertSame(1, $mpdf->unwinds);
		$this->assertStringStartsWith('“a ‘b’”Text.', implode('', $mpdf->drawnText));
		$this->assertSame(0, $mpdf->quoteDepth);
	}

	/**
	 * The legacy mode draws no marks
	 */
	public function testLegacyModeDrawsNoMarks()
	{
		$this->assertSame('qq', $this->textOf('<p><q>qq</q></p>', ['cssMode' => CssMode::LEGACY]));
	}

	/**
	 * @param string $html
	 * @param array $config
	 *
	 * @return string The text drawn, line after line
	 */
	private function textOf($html, array $config = [])
	{
		$mpdf = new TextRecordingMpdf($config);
		$mpdf->WriteHTML($html);

		return implode('', $mpdf->drawnText);
	}
}
