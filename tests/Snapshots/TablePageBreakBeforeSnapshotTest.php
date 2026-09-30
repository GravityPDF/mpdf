<?php

namespace Snapshots;

/**
 * page-break-before and break-before on a table start it on a new page, inside the blocks around it, while the same
 * property on a nested table is ignored.
 *
 * @group snapshot
 */
class TablePageBreakBeforeSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'table-page-break-before';
	}

	/**
	 * Tables that start a page, each captioned with the page it should be on
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h2 { font-size: 12pt; margin: 0 0 2mm 0; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #1f3a93; background-color: #eaf2fb; padding: 1mm 3mm; }
			div.box { border: 0.3mm solid #c0392b; padding: 3mm; }
			table.new { page-break-before: always; }
		</style>

		<h2>Page 1</h2>
		<p class="caption">The table after this caption has page-break-before: always in a style sheet, so page 1 holds only this heading and caption.</p>

		<table class="new">
			<tr><td>Page 2: this table starts the page.</td><td>Second cell</td></tr>
			<tr><td>Second row</td><td>Second row</td></tr>
		</table>

		<div class="box">
			<p class="caption">This red box starts on page 2. The table in it has break-before: page, so it moves to page 3, and the box is drawn again around it there.</p>
			<table style="break-before: page">
				<tr><td>Page 3: this table is inside the red box, below its border and padding.</td></tr>
			</table>
			<p class="caption">This caption follows the table inside the red box on page 3.</p>
		</div>

		<table style="width: 100%; margin-top: 4mm">
			<tr>
				<td>
					Page 3: the table in this cell has page-break-before: always, which a nested table ignores. It stays in the cell.
					<table style="page-break-before: always"><tr><td>Nested table</td></tr></table>
				</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
