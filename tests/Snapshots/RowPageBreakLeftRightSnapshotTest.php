<?php

namespace Snapshots;

/**
 * page-break-before: left and right on a table row go on to the next left or right page, leaving a blank page where
 * the next one has the wrong side, with the table's header and footer rows on the page the row lands on. A forced
 * break after the last body row starts a new page for what follows the table.
 *
 * @group snapshot
 */
class RowPageBreakLeftRightSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'row-page-break-left-right';
	}

	/**
	 * Tables whose rows break to a left or right page or end with a break, each captioned with the page its rows should
	 * be on, in a document with mirrored margins and the page number in the footer
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; line-height: 1.2; margin-bottom: 4mm; }
			td, th { border: 0.2mm solid #1f3a93; padding: 1mm 3mm; }
			td { background-color: #eaf2fb; }
			th { background-color: #c5d8f0; }
			tfoot td { background-color: #f4e9d6; }
		</style>

		<p class="caption">Page 1 (right): the third body row of this table has page-break-before: right. Page 2 is a left page, so it is left blank and the third row starts page 3 under the repeated header, with the footer drawn on pages 1 and 3 only.</p>
		<table style="width: 100%">
			<thead>
				<tr><th>Heading, on pages 1 and 3</th><th>Second heading</th></tr>
			</thead>
			<tfoot>
				<tr><td>Footer, on pages 1 and 3</td><td>Second footer cell</td></tr>
			</tfoot>
			<tbody>
				<tr><td>Page 1: first body row</td><td>Second cell</td></tr>
				<tr><td>Page 1: second body row</td><td>Second cell</td></tr>
				<tr style="page-break-before: right"><td>Page 3: third body row, under the repeated header</td><td>Second cell</td></tr>
			</tbody>
		</table>

		<p class="caption">Page 3 (right): the second row of this table has page-break-before: left, so it starts page 4, the next left page. Its last row has page-break-after: always, which ends the table's page, so the paragraph after it starts page 5.</p>
		<table>
			<tr><td>Page 3: first row</td></tr>
			<tr style="page-break-before: left"><td>Page 4: second row, at the top of the page</td></tr>
			<tr style="page-break-after: always"><td>Page 4: last row, with page-break-after: always</td></tr>
		</table>

		<p class="caption">Page 5 (right): this paragraph follows the table above. The table below has a tfoot, and its last body row has page-break-after: right, so page 6 is left blank and the paragraph after the table starts page 7.</p>
		<table>
			<tbody>
				<tr><td>Page 5: first row</td></tr>
				<tr style="page-break-after: right"><td>Page 5: last body row, with page-break-after: right</td></tr>
			</tbody>
			<tfoot>
				<tr><td>Page 5: footer</td></tr>
			</tfoot>
		</table>

		<p class="caption">Page 7 (right): the paragraph after the second table.</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['mirrorMargins' => true]);
		$footer = '<div style="text-align: center; font-size: 8pt">Page {PAGENO}</div>';
		$this->mpdf->SetHTMLFooter($footer, 'O');
		$this->mpdf->SetHTMLFooter($footer, 'E');

		$this->mpdf->WriteHTML($html);
	}

}
