<?php

namespace Mpdf;

/**
 * page-break-before and break-before on a top-level table start it on a new page, as they do for a block. The blocks
 * around the table are closed and opened again on the new page, with the table inside them.
 */
class TablePageBreakBeforeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The table's text is drawn on the page the break names, and the text after the table follows it
	 *
	 * @dataProvider providerBreakBefore
	 */
	public function testBreakBeforeTable($html, $pages)
	{
		$lines = $this->lines($html, ['mirrorMargins' => true]);

		$this->assertSame($pages, array_map(function ($line) {
			return $line[0];
		}, $lines));
	}

	/**
	 * Break values on a table, written inline and in a style sheet, and on a nested table
	 *
	 * @return array
	 */
	public function providerBreakBefore()
	{
		$table = '<table style="%s"><tr><td>MB</td></tr></table>';

		return [
			'always' => ['<p>MA</p>' . sprintf($table, 'page-break-before: always') . '<p>MC</p>', ['MA' => 1, 'MB' => 2, 'MC' => 2]],
			'break-before: page' => ['<p>MA</p>' . sprintf($table, 'break-before: page') . '<p>MC</p>', ['MA' => 1, 'MB' => 2, 'MC' => 2]],
			'right' => ['<p>MA</p>' . sprintf($table, 'page-break-before: right'), ['MA' => 1, 'MB' => 3]],
			'left' => ['<p>MA</p>' . sprintf($table, 'page-break-before: left'), ['MA' => 1, 'MB' => 2]],
			'style sheet' => ['<style>table.new { page-break-before: always; }</style><p>MA</p><table class="new"><tr><td>MB</td></tr></table>', ['MA' => 1, 'MB' => 2]],
			'auto' => ['<p>MA</p>' . sprintf($table, 'page-break-before: auto'), ['MA' => 1, 'MB' => 1]],
			'avoid' => ['<p>MA</p>' . sprintf($table, 'page-break-before: avoid'), ['MA' => 1, 'MB' => 1]],
			'nested table' => ['<table><tr><td>MA' . sprintf($table, 'page-break-before: always') . '</td></tr></table>', ['MA' => 1, 'MB' => 1]],
		];
	}

	/**
	 * A table that breaks inside a bordered block stays inside it on the new page, below its border and padding
	 */
	public function testTableStaysInsideTheBlockAroundIt()
	{
		$html = '<table style="page-break-before: always"><tr><td>MB</td></tr></table>';

		$alone = $this->lines('<p>MA</p>' . $html);
		$inside = $this->lines('<div style="border: 1mm solid red; padding: 3mm"><p>MA</p>' . $html . '<p>MC</p></div>');

		$this->assertSame(2, $inside['MB'][0]);
		$this->assertSame(2, $inside['MC'][0]);
		$this->assertEqualsWithDelta($alone['MB'][1] + 1 + 3, $inside['MB'][1], 0.001);
		$this->assertGreaterThan($inside['MB'][1], $inside['MC'][1]);
	}

	/**
	 * Render a document and find where each piece of text starting with "M" is drawn
	 *
	 * @param string $html
	 * @param array $config
	 * @return array[] The page and top of each marker, keyed by the marker
	 */
	private function lines($html, array $config = [])
	{
		$mpdf = new TextRecordingMpdf($config);
		$mpdf->WriteHTML($html);

		$lines = [];
		foreach ($mpdf->drawnText as $i => $text) {
			if (preg_match('/^M[A-Z]$/', trim($text))) {
				$lines[trim($text)] = [$mpdf->drawnBoxes[$i][0], $mpdf->drawnY[$i]];
			}
		}

		return $lines;
	}

}
