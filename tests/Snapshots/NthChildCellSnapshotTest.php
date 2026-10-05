<?php

namespace Snapshots;

/**
 * td and th nth-child rules counting the cells of their row, not the grid columns a colspan or rowspan takes up, and
 * :first-child on rows and cells.
 *
 * @group snapshot
 */
class NthChildCellSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nth-child-cell';
	}

	/**
	 * Tables with spanning cells, header and footer rows and a nested table, under captions saying which cells should
	 * be shaded
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			table.colspan td:nth-child(2) { background-color: #a9dfbf; }
			table.colspan td:nth-child(3) { background-color: #ff0000; }

			table.rowspan td:first-child { background-color: #aed6f1; }
			table.rowspan td:nth-child(2) { background-color: #f9e79f; }

			table.groups th:nth-child(2), table.groups td:nth-child(2) { background-color: #a9dfbf; }

			table.rows tr:first-child { background-color: #d7bde2; }

			table.nested td:nth-child(3) { background-color: #a9dfbf; }
			table.nested td td { border-color: #666666; }
		</style>

		<h1>nth-child counts the cells of the row</h1>

		<p class="caption">td:nth-child(2) is green and td:nth-child(3) is red. The cell after the two-column cell is the second: it is green, and no cell is red.</p>
		<table class="colspan">
			<tr><td colspan="2">R1 C1-2</td><td>R1 second cell</td></tr>
			<tr><td colspan="2">R2 C1-2</td><td>R2 second cell</td></tr>
		</table>

		<p class="caption">td:first-child is blue and td:nth-child(2) is yellow. The two-row cell and the first cell of the second row are blue. Beside them, R1 C2 and R2 C3 are yellow.</p>
		<table class="rowspan">
			<tr><td rowspan="2">R1-2 C1</td><td>R1 C2</td><td>R1 C3</td></tr>
			<tr><td>R2 C2</td><td>R2 C3</td></tr>
		</table>

		<p class="caption">th:nth-child(2) and td:nth-child(2) are green, in the header, body and footer rows alike. After the two-column cells, the last cell is green. Elsewhere, the middle cell is green.</p>
		<table class="groups">
			<thead>
				<tr><th colspan="2">Head C1-2</th><th>Head second cell</th></tr>
			</thead>
			<tfoot>
				<tr><td colspan="2">Foot C1-2</td><td>Foot second cell</td></tr>
			</tfoot>
			<tbody>
				<tr><td>Body C1</td><td>Body C2</td><td>Body C3</td></tr>
			</tbody>
		</table>

		<p class="caption">tr:first-child is purple: the first header row and the first body row.</p>
		<table class="rows">
			<thead>
				<tr><th>Head 1</th><th>Head 1</th></tr>
				<tr><th>Head 2</th><th>Head 2</th></tr>
			</thead>
			<tbody>
				<tr><td>Body 1</td><td>Body 1</td></tr>
				<tr><td>Body 2</td><td>Body 2</td></tr>
				<tr><td>Body 3</td><td>Body 3</td></tr>
			</tbody>
		</table>

		<p class="caption">td:nth-child(3) is green. The cell holding the nested table is the second, so it is not green. The third cell of the nested table and the cell after the nested table are green.</p>
		<table class="nested">
			<tr>
				<td colspan="2">C1-2</td>
				<td><table><tr><td>Inner 1</td><td>Inner 2</td><td>Inner 3</td></tr></table></td>
				<td>Third cell</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
