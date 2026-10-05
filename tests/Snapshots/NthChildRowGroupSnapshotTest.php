<?php

namespace Snapshots;

/**
 * tr:nth-child and tr:first-child counting the rows of their row group: the thead, each tbody, the tfoot, and the rows
 * written straight into the table.
 *
 * @group snapshot
 */
class NthChildRowGroupSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nth-child-row-group';
	}

	/**
	 * Tables with several row groups, a footer written after the body, a nested table and a header repeated on the next
	 * page, under captions saying which rows should be shaded
	 */
	public function generatePdf()
	{
		$rows = '';
		for ($row = 1; $row <= 40; $row++) {
			$rows .= '<tr><td>Body ' . $row . '</td><td>Body ' . $row . '</td></tr>';
		}

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			table.bodies tr:nth-child(odd) { background-color: #a9dfbf; }

			table.groups tr:first-child { background-color: #d7bde2; }

			table.straight tr:first-child { background-color: #aed6f1; }

			table.nested tr:nth-child(2) { background-color: #f9e79f; }
			table.nested td td { border-color: #666666; }

			table.repeated tr:nth-child(2) { background-color: #a9dfbf; }
		</style>

		<h1>tr:nth-child counts the rows of the row group</h1>

		<p class="caption">tr:nth-child(odd) is green. Each tbody starts again at 1: A1, A3, B1 and B3 are green.</p>
		<table class="bodies">
			<tbody>
				<tr><td>A1</td><td>A1</td></tr>
				<tr><td>A2</td><td>A2</td></tr>
				<tr><td>A3</td><td>A3</td></tr>
			</tbody>
			<tbody>
				<tr><td>B1</td><td>B1</td></tr>
				<tr><td>B2</td><td>B2</td></tr>
				<tr><td>B3</td><td>B3</td></tr>
			</tbody>
		</table>

		<p class="caption">tr:first-child is purple. The footer is written after the body, and its first row is purple, as are the first header row and the first body row.</p>
		<table class="groups">
			<thead>
				<tr><th>Head 1</th><th>Head 1</th></tr>
				<tr><th>Head 2</th><th>Head 2</th></tr>
			</thead>
			<tbody>
				<tr><td>Body 1</td><td>Body 1</td></tr>
				<tr><td>Body 2</td><td>Body 2</td></tr>
			</tbody>
			<tfoot>
				<tr><td>Foot 1</td><td>Foot 1</td></tr>
				<tr><td>Foot 2</td><td>Foot 2</td></tr>
			</tfoot>
		</table>

		<p class="caption">tr:first-child is blue. The rows written straight into the table after the header, and those between the two tbody, each start a group: Head 1, Direct 1, Body 1, Between 1 and Last 1 are blue.</p>
		<table class="straight">
			<thead>
				<tr><th>Head 1</th><th>Head 1</th></tr>
			</thead>
			<tr><td>Direct 1</td><td>Direct 1</td></tr>
			<tr><td>Direct 2</td><td>Direct 2</td></tr>
			<tbody>
				<tr><td>Body 1</td><td>Body 1</td></tr>
				<tr><td>Body 2</td><td>Body 2</td></tr>
			</tbody>
			<tr><td>Between 1</td><td>Between 1</td></tr>
			<tr><td>Between 2</td><td>Between 2</td></tr>
			<tbody>
				<tr><td>Last 1</td><td>Last 1</td></tr>
			</tbody>
		</table>

		<p class="caption">tr:nth-child(2) is yellow. The table nested in the first header cell does not move the count: Inner 2, Head 2 and Body 2 are yellow.</p>
		<table class="nested">
			<thead>
				<tr>
					<th><table><tr><td>Inner 1</td></tr><tr><td>Inner 2</td></tr><tr><td>Inner 3</td></tr></table></th>
					<th>Head 1</th>
				</tr>
				<tr><th>Head 2</th><th>Head 2</th></tr>
			</thead>
			<tbody>
				<tr><td>Body 1</td><td>Body 1</td></tr>
				<tr><td>Body 2</td><td>Body 2</td></tr>
				<tr><td>Body 3</td><td>Body 3</td></tr>
			</tbody>
		</table>

		<p class="caption">tr:nth-child(2) is green. The header is repeated on the next page. Head 2 is green on both pages, and of the body rows only Body 2 is.</p>
		<table class="repeated">
			<thead>
				<tr><th>Head 1</th><th>Head 1</th></tr>
				<tr><th>Head 2</th><th>Head 2</th></tr>
			</thead>
			<tbody>
				<?php echo $rows; ?>
			</tbody>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
