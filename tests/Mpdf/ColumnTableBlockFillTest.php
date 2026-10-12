<?php

namespace Mpdf;

class ColumnTableBlockFillTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * Both CSS modes, since the fill beside a table in columns is drawn the same way in each
	 *
	 * @return array
	 */
	public static function cssModeProvider()
	{
		return [
			'standard' => [CssMode::STANDARD],
			'legacy' => [CssMode::LEGACY],
		];
	}

	/**
	 * A table in a block that has a border but no background, inside columns, renders without a warning: the block's
	 * fill is put back after each row only when the block has one
	 *
	 * @dataProvider cssModeProvider
	 *
	 * @param string $cssMode
	 */
	public function testTableInBorderedBlockInColumnsRendersSilently($cssMode)
	{
		$this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<columns column-count="2" /><div style="border: 1px solid black"><table><tr><td>Cell</td></tr></table></div>');
		}, ['cssMode' => $cssMode]);
	}

	/**
	 * A table header that repeats at the top of the next column, in a bordered block without a background, renders
	 * without a warning too
	 *
	 * @dataProvider cssModeProvider
	 *
	 * @param string $cssMode
	 */
	public function testRepeatedHeaderInBorderedBlockInColumnsRendersSilently($cssMode)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$rows = str_repeat('<tr><td>Cell</td></tr>', 120);
			$mpdf->WriteHTML('<columns column-count="2" /><div style="border: 1px solid black"><table><thead><tr><th>Head</th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
		}, ['cssMode' => $cssMode]);

		$this->assertGreaterThan(1, substr_count($pdf, '(Head)'), 'The header should repeat in the next column');
	}
}
