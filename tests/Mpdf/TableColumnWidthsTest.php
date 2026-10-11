<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Under the standard CSS mode a table's columns take the widths its <col> and <colgroup> elements give them, and a
 * table with table-layout: fixed and a width sizes its columns from those and its first row alone, without widening
 * any for its content. The legacy mode drops <col> and <colgroup> and ignores table-layout, as it always has.
 *
 * The tables have no borders, so their columns add up to their width.
 */
class TableColumnWidthsTest extends TestCase
{

	/** Too long to break between words, it needs a 140 mm column */
	const LONG_WORD = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

	/**
	 * <col> sets the width of its column, by its style or its width attribute
	 *
	 * @dataProvider columnElements
	 *
	 * @param string $columns The elements before the first row
	 */
	public function testAColumnElementSetsItsColumnsWidth($columns)
	{
		$this->assertColumns([80, 20], '<table>' . $columns . '<tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>');
	}

	/**
	 * The ways of writing an 80 mm column and a 20 mm column
	 *
	 * @return array[]
	 */
	public function columnElements()
	{
		return [
			'style' => ['<col style="width: 80mm"><col style="width: 20mm">'],
			'width attribute' => ['<col width="80mm"><col width="20mm">'],
			'in a colgroup' => ['<colgroup><col width="80mm"><col width="20mm"></colgroup>'],
			'colgroup end tag left out' => ['<colgroup><col width="80mm"><col width="20mm"><tbody>'],
			'stylesheet' => ['<style>col.wide { width: 80mm } colgroup > col + col { width: 20mm }</style><colgroup><col class="wide"><col></colgroup>'],
		];
	}

	/**
	 * span gives each of the columns it counts the element's width, and a colgroup with no col in it spans its own
	 *
	 * @dataProvider spans
	 *
	 * @param string $columns The elements before the first row
	 * @param float[] $expected
	 */
	public function testSpanGivesEachColumnTheWidth($columns, array $expected)
	{
		$this->assertColumns($expected, '<table>' . $columns . '<tr><td>a</td><td>b</td><td>c</td></tr></table>');
	}

	/**
	 * Columns made with span, and the widths they come to
	 *
	 * @return array[]
	 */
	public function spans()
	{
		return [
			'col span' => ['<col span="2" width="30mm"><col width="10mm">', [30, 30, 10]],
			'colgroup span' => ['<colgroup span="2" width="30mm"></colgroup><col width="10mm">', [30, 30, 10]],
			'col in a colgroup takes its width' => ['<colgroup width="30mm"><col><col width="10mm"></colgroup><col width="20mm">', [30, 10, 20]],
			'colgroup span ignored with cols in it' => ['<colgroup span="3" width="30mm"><col></colgroup><col width="10mm"><col width="5mm">', [30, 10, 5]],
		];
	}

	/**
	 * A cell's own width wins over its column's, and a cell spanning columns takes none of theirs
	 */
	public function testACellsOwnWidthWinsAndASpanningCellTakesNone()
	{
		$columns = $this->columns(
			'<table><col width="80mm"><col width="20mm"><tr><td colspan="2">' . str_repeat('word ', 30) . '</td></tr><tr><td style="width: 50mm">a</td><td>b</td></tr></table>'
		);

		// The second row's first cell is 50 mm, which keeps its column from the 80 mm its col gives it
		$this->assertEqualsWithDelta(50, $columns[0], 0.01);
		$this->assertEqualsWithDelta(20, $columns[1], 0.01);
	}

	/**
	 * A fixed table keeps its width: its first column is as wide as the first row's cell says, however long its word,
	 * and the second takes the rest. Laid out by its content, the first column widens for the word and the table with it
	 */
	public function testAFixedTableDoesNotWidenAColumnForItsContent()
	{
		$fixed = '<table style="table-layout: fixed; width: 100mm"><tr><td style="width: 20mm">' . self::LONG_WORD . '</td><td>b</td></tr></table>';
		$this->assertColumns([20, 80], $fixed);

		$auto = $this->columns(str_replace('table-layout: fixed; ', '', $fixed));
		$this->assertGreaterThan(100, array_sum($auto));
	}

	/**
	 * The columns of a fixed table come from its col elements first, then from the cells of its first row, and the room
	 * they leave is shared equally among the rest. Later rows play no part
	 *
	 * @dataProvider fixedTables
	 *
	 * @param string $html
	 * @param float[] $expected
	 */
	public function testAFixedTableSizesItsColumnsFromColumnsAndTheFirstRow($html, array $expected)
	{
		$this->assertColumns($expected, $html);
	}

	/**
	 * Fixed tables and their columns' widths
	 *
	 * @return array[]
	 */
	public function fixedTables()
	{
		$table = '<table style="table-layout: fixed; width: 120mm">';

		return [
			'col wins over the first row' => [$table . '<col width="30mm"><tr><td style="width: 90mm">a</td><td>b</td><td>c</td></tr></table>', [30, 45, 45]],
			'later rows ignored' => [$table . '<tr><td style="width: 20mm">a</td><td>b</td></tr><tr><td style="width: 100mm">c</td><td>d</td></tr></table>', [20, 100]],
			'percentage' => [$table . '<tr><td style="width: 25%">a</td><td>b</td><td>c</td></tr></table>', [30, 45, 45]],
			'colspan shares its width' => [$table . '<tr><td colspan="2" style="width: 60mm">a</td><td>b</td></tr><tr><td>c</td><td>d</td><td>e</td></tr></table>', [30, 30, 60]],
			'all set and narrower: widened in proportion' => [$table . '<tr><td style="width: 20mm">a</td><td style="width: 40mm">b</td></tr></table>', [40, 80]],
			'wider than the table: the table widens' => [$table . '<col width="100mm"><col width="50mm"><tr><td>a</td><td>b</td></tr></table>', [100, 50]],
			'no widths: equal columns' => [$table . '<tr><td>' . self::LONG_WORD . '</td><td>b</td><td>c</td></tr></table>', [40, 40, 40]],
		];
	}

	/**
	 * table-layout: fixed needs a width: on a table with none, its columns are sized by their content
	 */
	public function testAFixedTableWithNoWidthIsLaidOutByItsContent()
	{
		$html = '<table style="table-layout: fixed"><tr><td style="width: 20mm">a</td><td>' . str_repeat('word ', 10) . '</td></tr></table>';

		$this->assertSame($this->columns(str_replace(' style="table-layout: fixed"', '', $html)), $this->columns($html));
	}

	/**
	 * A fixed table nested in a cell keeps its columns, and the cell is as wide as the table
	 */
	public function testANestedFixedTableKeepsItsColumns()
	{
		$mpdf = $this->layOut('<table><tr><td><table style="table-layout: fixed; width: 60mm"><tr><td style="width: 10mm">' . self::LONG_WORD . '</td><td>b</td></tr></table></td><td>c</td></tr></table>');

		$nested = $this->tableAt($mpdf, 2);
		$this->assertEqualsWithDelta([10, 50], array_values($nested['columns']), 0.01);
		$this->assertEqualsWithDelta(60, $nested['w'], 0.01);
	}

	/**
	 * Rows after a colgroup still sit in the tbody a browser gives them, so a selector through it reaches their cells
	 */
	public function testRowsAfterAColgroupAreInTheImpliedTbody()
	{
		$this->assertColumns(
			[30, 20],
			'<style>table > tbody > tr > td.first { width: 30mm }</style><table><colgroup><col><col width="20mm"><tr><td class="first">a</td><td>b</td></tr></table>'
		);
	}

	/**
	 * The legacy mode drops col and colgroup and ignores table-layout: the tables are laid out as without them
	 *
	 * @dataProvider legacyTables
	 *
	 * @param string $html
	 * @param string $without The same table without what the legacy mode ignores
	 */
	public function testTheLegacyModeIgnoresColumnsAndTableLayout($html, $without)
	{
		$this->assertSame($this->columns($without, CssMode::LEGACY), $this->columns($html, CssMode::LEGACY));
	}

	/**
	 * Tables with columns or a fixed layout, and the same tables without them
	 *
	 * @return array[]
	 */
	public function legacyTables()
	{
		$fixed = '<table style="width: 100mm"><tr><td style="width: 20mm">' . self::LONG_WORD . '</td><td>b</td></tr></table>';

		return [
			'col' => ['<table><col width="80mm"><col width="20mm"><tr><td>a</td><td>b</td></tr></table>', '<table><tr><td>a</td><td>b</td></tr></table>'],
			'colgroup' => ['<table><colgroup width="80mm" span="2"></colgroup><tr><td>a</td><td>b</td></tr></table>', '<table><tr><td>a</td><td>b</td></tr></table>'],
			'table-layout' => [str_replace('style="', 'style="table-layout: fixed; ', $fixed), $fixed],
		];
	}

	/**
	 * Asserts the widths of the columns of the outermost table, to a hundredth of a millimetre
	 *
	 * @param float[] $expected
	 * @param string $html
	 */
	private function assertColumns(array $expected, $html)
	{
		$this->assertEqualsWithDelta($expected, $this->columns($html), 0.01);
	}

	/**
	 * @param string $html
	 * @param string $cssMode
	 *
	 * @return float[] The widths of the outermost table's columns, in order
	 */
	private function columns($html, $cssMode = CssMode::STANDARD)
	{
		$table = $this->tableAt($this->layOut($html, $cssMode), 1);

		return array_values($table['columns']);
	}

	/**
	 * @param string $html
	 * @param string $cssMode
	 *
	 * @return TableWidthRecordingMpdf The document with the HTML written in it
	 */
	private function layOut($html, $cssMode = CssMode::STANDARD)
	{
		$mpdf = new TableWidthRecordingMpdf(['mode' => 'c', 'cssMode' => $cssMode]);
		$mpdf->WriteHTML('<style>table { border-collapse: collapse }</style>' . $html);

		return $mpdf;
	}

	/**
	 * @param TableWidthRecordingMpdf $mpdf
	 * @param int $level 1 for the outermost table
	 *
	 * @return array The last table laid out at that level, as TableWidthRecordingMpdf records it
	 */
	private function tableAt(TableWidthRecordingMpdf $mpdf, $level)
	{
		$tables = array_filter($mpdf->tableWidths, function ($table) use ($level) {
			return $table['level'] === $level;
		});

		$this->assertNotEmpty($tables);

		return end($tables);
	}
}
