<?php

namespace Mpdf;

/**
 * A table with a background can be the first thing drawn in a column
 */
class TableFirstInColumnTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * Tables whose first output is a background rectangle, opening the first or the second column, in each CSS mode
	 *
	 * @return string[][]
	 */
	public function tablesOpeningAColumn()
	{
		$tables = [
			'table background' => '<table style="background: #ccc" border="1"><tr><td>A</td></tr></table>',
			'row background' => '<table border="1"><tr style="background: #ccc"><td>A</td></tr></table>',
			'cell background' => '<table border="1"><tr><td style="background: #ccc">A</td></tr></table>',
		];
		$columns = [
			'first column' => '',
			'second column' => '<p>Before</p><columnbreak />',
		];

		$cases = [];
		foreach (['standard', 'legacy'] as $mode) {
			foreach ($columns as $column => $before) {
				foreach ($tables as $name => $table) {
					$cases[$name . ', ' . $column . ', ' . $mode] = [$mode, $before . $table];
				}
			}
		}

		return $cases;
	}

	/**
	 * The column has no lowest point yet when the background is drawn, and that must not raise a warning
	 *
	 * @dataProvider tablesOpeningAColumn
	 *
	 * @param string $mode
	 * @param string $html
	 */
	public function testTableOpeningAColumnRaisesNoWarning($mode, $html)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML('<columns column-count="2" />' . $html);
		}, ['cssMode' => $mode]);

		$this->assertStringContainsString('0.800 0.800 0.800 rg', implode("\n", $this->pages($pdf)));
	}
}
