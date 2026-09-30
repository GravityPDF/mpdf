<?php

namespace Snapshots;

/**
 * tr, td and th:nth-child counted from the element's place among its siblings in the document, wherever the table is
 * written: header cells among the cells, mPDF's own tags between cells, rows written with no tbody or end tags, a
 * nested table, a page header, a positioned block and a page-break-inside: avoid block laid out again on the next page.
 *
 * @group snapshot
 */
class NthChildTableContextsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nth-child-table-contexts';
	}

	/**
	 * One table for each context, each under a caption saying which rows and cells should be shaded
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			table.mixed td:nth-child(2), table.mixed th:nth-child(2) { background-color: #a9dfbf; }

			table.tags td:first-child { background-color: #d7bde2; }
			table.tags td:nth-child(3) { background-color: #aed6f1; }

			table.bare tr:nth-child(even) { background-color: #f9e79f; }

			table.outer tr:nth-child(2) td { font-weight: bold; }
			table.outer td:nth-child(3) { background-color: #f5b7b1; }

			table.header tr:nth-child(2) { background-color: #a9dfbf; }

			table.positioned td:nth-child(2) { background-color: #d7bde2; }

			table.kept tr:nth-child(3) { background-color: #aed6f1; }
		</style>

		<htmlpageheader name="header">
			<table class="header">
				<tr><td>Header row 1</td></tr>
				<tr><td>Header row 2, green</td></tr>
				<tr><td>Header row 3</td></tr>
			</table>
		</htmlpageheader>
		<sethtmlpageheader name="header" value="on" show-this-page="1" />

		<h1>tr, td and th:nth-child wherever the table is written</h1>

		<p class="caption">The page header's table is written apart from the flow, and counts its own rows: its second row is green on both pages.</p>

		<p class="caption">td:nth-child(2) and th:nth-child(2) are green. A th counts among the cells of its row as a td does: the second cell of each row is green, whichever it is.</p>
		<table class="mixed">
			<tr><th>th 1</th><td>td 2</td><th>th 3</th></tr>
			<tr><td>td 1</td><th>th 2</th><td>td 3</td></tr>
		</table>

		<p class="caption">td:first-child is purple and td:nth-child(3) blue. mPDF's own tags written between the cells, such as a bookmark before the first cell, are not cells: A and D are purple, C and F blue.</p>
		<table class="tags">
			<tr><bookmark content="Row one" /><td>A</td><td>B</td><tocentry content="Row one" /><td>C</td></tr>
			<tr><td>D</td><indexentry content="Row two" /><td>E</td><td>F</td></tr>
		</table>

		<p class="caption">tr:nth-child(even) is yellow. The rows are written with no tbody and no end tags: rows 2 and 4 are yellow.</p>
		<table class="bare">
			<tr><td>Row 1<td>Row 1
			<tr><td>Row 2<td>Row 2
			<tr><td>Row 3<td>Row 3
			<tr><td>Row 4<td>Row 4
		</table>

		<p class="caption">Inside the outer table td:nth-child(3) is red, and the cells of tr:nth-child(2) are bold. The nested table counts its own rows and cells, and the outer table counts on after it: Outer 1c and Outer 2c are red, and none of the nested table's two columns is. Inner C, Inner D and the outer table's second row are bold.</p>
		<table class="outer">
			<tr>
				<td>Outer 1a</td>
				<td><table><tr><td>Inner A</td><td>Inner B</td></tr><tr><td>Inner C</td><td>Inner D</td></tr></table></td>
				<td>Outer 1c</td>
			</tr>
			<tr><td>Outer 2a</td><td>Outer 2b</td><td>Outer 2c</td></tr>
		</table>

		<p class="caption">An absolutely positioned block at the foot of the page holds a table whose td:nth-child(2) is purple: Second is purple.</p>
		<div style="position: absolute; bottom: 20mm; left: 20mm; width: 80mm;">
			<table class="positioned"><tr><td>First</td><td>Second</td><td>Third</td></tr></table>
		</div>

		<?php echo str_repeat('<p>Filler to bring the kept block to the foot of the page.</p>', 8); ?>

		<p class="caption">A page-break-inside: avoid block is laid out, found not to fit, and laid out again on the next page. In its table tr:nth-child(3) is blue: only Kept row 3.</p>
		<div style="page-break-inside: avoid;">
			<p>The kept block.</p>
			<table class="kept">
				<tr><td>Kept row 1</td></tr>
				<tr><td>Kept row 2</td></tr>
				<tr><td>Kept row 3, blue</td></tr>
				<tr><td>Kept row 4</td></tr>
				<tr><td>Kept row 5</td></tr>
				<tr><td>Kept row 6</td></tr>
			</table>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['margin_top' => 36]);

		$this->mpdf->WriteHTML($html);
	}

}
