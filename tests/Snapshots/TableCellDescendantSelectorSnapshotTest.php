<?php

namespace Snapshots;

/**
 * Stylesheet rules with a descendant selector naming a table, row or cell, reaching the images, inline elements and
 * blocks inside the cell (#223, mpdf/mpdf#420).
 *
 * @group snapshot
 */
class TableCellDescendantSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'table-cell-descendant-selector';
	}

	/**
	 * Tables whose content is styled only by rules naming the table, row or cell, each under a caption saying what
	 * should be seen, then a table in a positioned block
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { border-collapse: collapse; margin-bottom: 2mm; }
			td, th { border: 0.2mm solid #999999; padding: 1mm; vertical-align: top; }
			h2 { margin: 5mm 0 1mm 0; font-size: 11pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }

			td img { max-width: 100%; }
			td.thumb img { max-width: 20mm; border-radius: 3mm; }

			table.chain tr td span { color: #c0392b; font-weight: bold; }
			.grid .row .cell .mark { background-color: #f9e79f; }
			td.note p { color: #1f3a93; font-style: italic; margin: 1mm 0; }
			td.note ul { margin: 0; }
			td.note li { color: #0a7d32; }
			td.plain p { color: #b3006b; }

			div.report td em { color: #8e44ad; text-decoration: underline; }
			div.elsewhere td em { color: #ff0000; font-size: 16pt; }

			td.outer td b { color: #d35400; }

			thead th span { color: #ffffff; background-color: #1f3a93; }
			tfoot td span { color: #7a4a00; font-style: italic; }
			tbody td small { color: #0a7d32; }

			#side td span { color: #ffffff; background-color: #16a085; }
		</style>

		<h1>Descendant rules reaching into table cells</h1>

		<h2>Pictures sized by td img and td.thumb img</h2>
		<p class="caption">Three 60mm columns. The first two pictures fill their cells; the thumbnail stops at 20mm with rounded corners.</p>
		<table style="width: 100%">
			<tr>
				<td width="33.3333%"><img src="img/bayeux2.jpg" width="400"></td>
				<td width="33.3333%"><img src="img/bayeux2.jpg" width="600"></td>
				<td class="thumb"><img src="img/bayeux2.jpg" width="400"></td>
			</tr>
		</table>

		<h2>Tag and class chains</h2>
		<p class="caption">Red bold words from table.chain tr td span; yellow highlight from .grid .row .cell .mark.</p>
		<table class="chain grid">
			<tr class="row">
				<td class="cell">Plain, <span>red and bold</span>, plain</td>
				<td class="cell">Plain, <b class="mark">highlighted</b>, <span class="mark">both</span></td>
			</tr>
		</table>

		<h2>Blocks in a cell, chosen by the cell's class</h2>
		<p class="caption">Blue italic paragraph and green list items in td.note; magenta paragraph in td.plain; nothing in the unclassed cell.</p>
		<table>
			<tr>
				<td class="note"><p>A note paragraph</p><ul><li>First item</li><li>Second item</li></ul></td>
				<td class="plain"><p>A plain paragraph</p></td>
				<td><p>An unstyled paragraph</p></td>
			</tr>
		</table>

		<h2>Rules naming a block outside the table</h2>
		<p class="caption">Purple underlined emphasis from div.report td em; the div.elsewhere rule, which would make it large and red, does not apply.</p>
		<div class="report">
			<table>
				<tr><td>Some <em>emphasised</em> words</td></tr>
			</table>
		</div>

		<h2>A table nested in a cell</h2>
		<p class="caption">Orange bold in the inner table from td.outer td b; the bold directly in the outer cell, not in a td inside it, stays black.</p>
		<table>
			<tr>
				<td class="outer">
					<b>Outer bold</b>
					<table>
						<tr><td>Inner <b>bold</b></td></tr>
					</table>
				</td>
			</tr>
		</table>

		<h2>Header, body and footer cells</h2>
		<p class="caption">White on blue in the header, green notes in the body and brown italic in the footer.</p>
		<table style="width: 100%">
			<thead>
				<tr><th><span>Row</span></th><th><span>Description</span></th></tr>
			</thead>
			<tfoot>
				<tr><td colspan="2"><span>End of the table</span></td></tr>
			</tfoot>
			<tbody>
				<?php for ($i = 1; $i <= 3; $i++) { ?>
				<tr><td><?php echo $i; ?></td><td>Row <?php echo $i; ?> <small>(note <?php echo $i; ?>)</small></td></tr>
				<?php } ?>
			</tbody>
		</table>

		<h2>A table in a positioned block</h2>
		<p class="caption">White on teal words from #side td span, in the block positioned at the bottom right of this page.</p>
		<div id="side" style="position: absolute; bottom: 20mm; right: 15mm; width: 70mm; border: 0.3mm dashed #16a085; padding: 2mm;">
			<table><tr><td>Fixed <span>cell text</span></td></tr></table>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
