<?php

namespace Snapshots;

/**
 * page-break-before: always and page-break-after: always on a table row start a new page at that row, with the
 * table's header and footer rows drawn again on each page, while a break on the first body row, on a footer row or in
 * a nested table is ignored.
 *
 * @group snapshot
 */
class RowPageBreakSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'row-page-break';
	}

	/**
	 * Tables whose rows break the page, each captioned with the page its rows should be on
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h2 { font-size: 12pt; margin: 0 0 2mm 0; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; line-height: 1.2; margin-bottom: 4mm; }
			td, th { border: 0.2mm solid #1f3a93; padding: 1mm 3mm; }
			td { background-color: #eaf2fb; }
			th { background-color: #c5d8f0; }
			tfoot td { background-color: #f4e9d6; }
			tr.new { page-break-before: always; }
		</style>

		<h2>Page 1</h2>
		<p class="caption">The second row of this table has page-break-before: always, so page 1 holds the first row only and the second row starts page 2.</p>
		<table>
			<tr><td>Page 1: first row</td><td>Second cell</td></tr>
			<tr class="new"><td>Page 2: second row, at the top of the page</td><td>Second cell</td></tr>
			<tr><td>Page 2: third row, below the second</td><td>Second cell</td></tr>
		</table>

		<p class="caption">Page 2: the first row of this table has page-break-after: always, so its second row starts page 3. The first row has page-break-before: always too, which a first body row ignores, so this table stays on page 2 under its caption.</p>
		<table>
			<tr style="page-break-before: always; page-break-after: always"><td>Page 2: first row</td></tr>
			<tr><td>Page 3: second row, at the top of the page</td></tr>
		</table>

		<p class="caption">Page 3: this table has a thead and a tfoot, written before the body. The third body row has break-before: page, so page 3 ends with the first two body rows and the footer, and page 4 starts with the header again, then the third and fourth body rows and the footer.</p>
		<table style="width: 100%">
			<thead>
				<tr><th>Heading, on pages 3 and 4</th><th>Second heading</th></tr>
			</thead>
			<tfoot>
				<tr style="page-break-before: always"><td>Footer, on pages 3 and 4; its own page-break-before is ignored</td><td>Second footer cell</td></tr>
			</tfoot>
			<tbody>
				<tr><td>Page 3: first body row</td><td>Second cell</td></tr>
				<tr><td>Page 3: second body row</td><td>Second cell</td></tr>
				<tr style="break-before: page"><td>Page 4: third body row, under the repeated header</td><td>Second cell</td></tr>
				<tr><td>Page 4: fourth body row</td><td>Second cell</td></tr>
			</tbody>
		</table>

		<p class="caption">Page 4: the table in this cell has a row with page-break-before: always, which a nested table ignores. Both rows stay in the cell.</p>
		<table>
			<tr>
				<td>
					Page 4: outer cell
					<table style="margin: 1mm 0 0 0">
						<tr><td>Nested first row</td></tr>
						<tr style="page-break-before: always"><td>Nested second row, still on page 4</td></tr>
					</table>
				</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
