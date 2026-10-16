<?php

namespace Mpdf;

/**
 * A forced page-break-before or page-break-after on a table row starts a new page at that row, with the header and
 * footer rows repeated, as it does for a block; left and right go on to the next left or right page. A break after the
 * last body row is a break after the table. A break before the first body row, on a header or footer row, or in a
 * nested table is left to the table around it.
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
		// left and right pages exist only with mirrored margins
		$mirrored = ['mirrorMargins' => true];

		return [
			'before' => [$always, ['MA' => [1], 'MB' => [2]]],
			'after' => ['<table><tr style="page-break-after: always"><td>MA</td></tr><tr><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'break-before: page' => ['<table><tr><td>MA</td></tr><tr style="break-before: page"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'right from an odd page' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: right"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [3]], $mirrored],
			'right from an even page' => ['<pagebreak /><table><tr><td>MA</td></tr><tr style="page-break-before: right"><td>MB</td></tr></table>', ['MA' => [2], 'MB' => [3]], $mirrored],
			'left from an odd page' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: left"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]], $mirrored],
			'left from an even page' => ['<pagebreak /><table><tr><td>MA</td></tr><tr style="page-break-before: left"><td>MB</td></tr></table>', ['MA' => [2], 'MB' => [4]], $mirrored],
			'after: right' => ['<table><tr style="page-break-after: right"><td>MA</td></tr><tr><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [3]], $mirrored],
			'right without mirrored margins is read as always' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: right"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'right beats the previous row\'s always' => ['<table><tr style="page-break-after: always"><td>MA</td></tr><tr style="page-break-before: right"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [3]], $mirrored],
			'after: right beats the next row\'s always' => ['<table><tr style="page-break-after: right"><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [3]], $mirrored],
			'the later of left and right wins' => ['<table><tr style="page-break-after: right"><td>MA</td></tr><tr style="page-break-before: left"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]], $mirrored],
			'header and footer on the page right gives' => ['<table><thead><tr><th>MH</th></tr></thead><tfoot><tr><td>MF</td></tr></tfoot><tbody><tr><td>MA</td></tr><tr style="page-break-before: right"><td>MB</td></tr></tbody></table>', ['MH' => [1, 3], 'MA' => [1], 'MF' => [1, 3], 'MB' => [3]], $mirrored],
			'style sheet' => ['<style>tr.new { page-break-before: always; }</style><table><tr><td>MA</td></tr><tr class="new"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'legacy mode' => [$always, ['MA' => [1], 'MB' => [2]], ['cssMode' => CssMode::LEGACY]],
			'auto' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: auto"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [1]]],
			'avoid' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: avoid"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [1]]],
			'after: always beats the next row\'s avoid' => ['<table><tr style="page-break-after: always"><td>MA</td></tr><tr style="page-break-before: avoid"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'before: always beats the previous row\'s avoid' => ['<table><tr style="page-break-after: avoid"><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr></table>', ['MA' => [1], 'MB' => [2]]],
			'two breaks' => ['<table><tr><td>MA</td></tr><tr style="page-break-before: always"><td>MB</td></tr><tr style="page-break-before: always"><td>MC</td></tr></table>', ['MA' => [1], 'MB' => [2], 'MC' => [3]]],
			'first row' => ['<p>MA</p><table><tr style="page-break-before: always"><td>MB</td></tr><tr><td>MC</td></tr></table>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
			'after the last row' => ['<table><tr><td>MA</td></tr><tr style="page-break-after: always"><td>MB</td></tr></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MC' => [2]]],
			'after the last row, before a tfoot' => ['<table><tbody><tr><td>MA</td></tr><tr style="page-break-after: always"><td>MB</td></tr></tbody><tfoot><tr><td>MF</td></tr></tfoot></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MF' => [1], 'MC' => [2]]],
			'after the last row, with a tfoot written first' => ['<table><tfoot><tr><td>MF</td></tr></tfoot><tbody><tr><td>MA</td></tr><tr style="page-break-after: always"><td>MB</td></tr></tbody></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MF' => [1], 'MC' => [2]]],
			'after the last row: right' => ['<table><tr><td>MA</td></tr><tr style="page-break-after: right"><td>MB</td></tr></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MC' => [3]], $mirrored],
			'after the last row: right beats the table\'s always' => ['<table style="page-break-after: always"><tr><td>MA</td></tr><tr style="page-break-after: right"><td>MB</td></tr></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MC' => [3]], $mirrored],
			'the table\'s left beats its last row\'s right' => ['<table style="page-break-after: left"><tr><td>MA</td></tr><tr style="page-break-after: right"><td>MB</td></tr></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MC' => [2]], $mirrored],
			'avoid after the last row' => ['<table><tr><td>MA</td></tr><tr style="page-break-after: avoid"><td>MB</td></tr></table><p>MC</p>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
			'after the last row of a nested table' => ['<table><tr><td><table><tr><td>MA</td></tr><tr style="page-break-after: always"><td>MB</td></tr></table>MC</td></tr></table>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
			'table: after right' => ['<table style="page-break-after: right"><tr><td>MA</td></tr></table><p>MB</p>', ['MA' => [1], 'MB' => [3]], $mirrored],
			'thead row' => ['<table><thead><tr style="page-break-before: always"><th>MH</th></tr></thead><tbody><tr><td>MA</td></tr></tbody></table>', ['MH' => [1], 'MA' => [1]]],
			'after the thead row' => ['<table><thead><tr style="page-break-after: always"><th>MH</th></tr></thead><tbody><tr><td>MA</td></tr></tbody></table>', ['MH' => [1], 'MA' => [1]]],
			'tfoot row' => ['<table><tbody><tr><td>MA</td></tr></tbody><tfoot><tr style="page-break-before: always"><td>MF</td></tr></tfoot></table>', ['MA' => [1], 'MF' => [1]]],
			'tfoot row written before the body' => ['<table><tfoot><tr style="page-break-before: always; page-break-after: always"><td>MF</td></tr></tfoot><tbody><tr><td>MA</td></tr><tr><td>MB</td></tr></tbody></table>', ['MA' => [1], 'MB' => [1], 'MF' => [1]]],
			'nested table' => ['<table><tr><td>MA<table><tr><td>MB</td></tr><tr style="page-break-before: always"><td>MC</td></tr></table></td></tr></table>', ['MA' => [1], 'MB' => [1], 'MC' => [1]]],
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

		return $mpdf->drawnPages('/^M[A-Z]$/');
	}

}
