<?php

namespace Mpdf;

/**
 * page-break-before: always and page-break-after: always on a table row start a new page at that row, with the header
 * and footer rows repeated, as they do for a block. A break before the first body row, on a header or footer row, or in
 * a nested table is left to the table around it.
 */
class RowPageBreakTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each cell's text is drawn on the pages the row breaks put it on
	 *
	 * @dataProvider providerRowBreak
	 */
	public function testRowBreak($html, $pages, array $config = [])
	{
		$this->assertSame($pages, $this->pagesOf($html, $config));
	}

	/**
	 * Forced breaks before and after a row, on the first and last rows, on header and footer rows, in a nested table
	 * and in both CSS modes
	 *
	 * @return array
	 */
	public function providerRowBreak()
	{
		$always = '<table><tr><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr></table>';

		return [
			'before' => [$always, ['MA' => [1], 'MB' => [2]]],
			'after' => ['<table><tr style="page-break-after: always"><td>MA</td></tr><tr><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'break-before: page' => ['<table><tr><td>MA</td></tr><tr style="break-before: page"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'break-after: page' => ['<table><tr style="break-after: page"><td>MA</td></tr><tr><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'left' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: left"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]], ['mirrorMargins' => true]],
			'right' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: right"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]], ['mirrorMargins' => true]],
			'style sheet' => ['<style>tr.new { page-break-before: always; }</style><table><tr><td>MA</td></tr><tr class="new"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'legacy mode' => [$always, ['MA' => [1], 'MB' => [2]], ['cssMode' => CssMode::LEGACY]],
			'auto' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: auto"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [1]]],
			'avoid' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: avoid"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [1]]],
			'after: always beats the next row\'s avoid' => ['<table><tr style="page-break-after: always"><td>MA</td></tr><tr style="page-break-before: avoid"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'before: always beats the previous row\'s avoid' => ['<table><tr style="page-break-after: avoid"><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'two breaks' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr><tr style="page-break-before: always"><td>MC</td></tr></table>', ['MA' => [1], 'MB' => [2], 'MC' => [3]]],
			'first row' => ['<p>MA</p><table><tr style="page-break-before: always"><td>MB</td></tr><tr><td>MC</td></tr></table>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
			'last row' => ['<table><tr><td>MA</td></tr><tr style="page-break-after: always"><td>MB</td></tr></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
			'thead row' => ['<table><thead><tr style="page-break-before: always"><th>MH</th></tr></thead><tbody><tr><td>MA</td></tr></tbody></table>', ['MH' => [1], 'MA' => [1]]],
			'after the thead row' => ['<table><thead><tr style="page-break-after: always"><th>MH</th></tr></thead><tbody><tr><td>MA</td></tr></tbody></table>', ['MH' => [1], 'MA' => [1]]],
			'tfoot row' => ['<table><tbody><tr><td>MA</td></tr></tbody><tfoot><tr style="page-break-before: always"><td>MF</td></tr></tfoot></table>', ['MA' => [1], 'MF' => [1]]],
			'tfoot row written before the body' => ['<table><tfoot><tr style="page-break-before: always; page-break-after: always"><td>MF</td></tr></tfoot><tbody><tr><td>MA</td></tr><tr><td>MB</td></tr></tbody></table>', ['MA' => [1], 'MB' => [1], 'MF' => [1]]],
			'nested table' => ['<table><tr><td>MA<table><tr><td>MB</td></tr><tr style="page-break-before: always"><td>MC</td></tr></table></td></tr></table>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
			'repeated header' => ['<table><thead><tr><th>MH</th></tr></thead><tbody><tr><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr></tbody></table>', ['MH' => [1, 2], 'MA' => [1], 'MB' => [2]]],
			'repeated footer' => ['<table><tfoot><tr><td>MF</td></tr></tfoot><tbody><tr><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr></tbody></table>', ['MA' => [1], 'MF' => [1, 2], 'MB' => [2]]],
			'repeated header and footer' => ['<table><thead><tr><th>MH</th></tr></thead><tfoot><tr><td>MF</td></tr></tfoot><tbody><tr><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr><tr><td>MC</td></tr></tbody></table>', ['MH' => [1, 2], 'MA' => [1], 'MF' => [1, 2], 'MB' => [2], 'MC' => [2]]],
		];
	}

	/**
	 * Render a document and find the pages each piece of text starting with "M" is drawn on, in drawing order
	 *
	 * @param string $html
	 * @param array $config
	 * @return int[][] The pages of each marker, keyed by the marker
	 */
	private function pagesOf($html, array $config = [])
	{
		$mpdf = new TextRecordingMpdf($config);
		$mpdf->WriteHTML($html);

		$pages = [];
		foreach ($mpdf->drawnText as $i => $text) {
			if (preg_match('/^M[A-Z]$/', trim($text))) {
				$pages[trim($text)][] = $mpdf->drawnBoxes[$i][0];
			}
		}

		return $pages;
	}

}
