<?php

namespace Snapshots;

/**
 * Tables inherit from the block around them under the standard cascade, one case per caption: a div's colour and
 * font, a list item's size, a table's percentage of its block, rem in a cell, a div's line-height and alignment, and
 * a nested table taking its cell's alignment and line-height.
 *
 * @group snapshot
 */
class TableInheritsFromBlockSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'table-inherits-from-block';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 10pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 5mm 0 1mm 0; font-size: 8pt; color: #606060; font-family: serif; line-height: normal; text-align: left; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 2mm; }
		</style>

		<h1>Tables inherit from the block around them</h1>

		<div style="color: #c00000; font-family: monospace">
			<p class="caption">color: #c00000 and font-family: monospace on a div. The table's cells are red and monospaced.</p>
			<table style="line-height: 1.2"><tr><td>First cell</td><td>Second cell</td></tr></table>
		</div>

		<ul>
			<li style="font-size: 14pt; color: #1f4fa0"><p class="caption">font-size: 14pt and a blue colour on a list item. The table's cells are 14pt and blue.</p>
				<table style="line-height: 1.2"><tr><td>14pt</td><td>blue</td></tr></table>
			</li>
		</ul>

		<div style="font-size: 20pt">
			<p class="caption">font-size: 20pt on a div, and font-size: 50% on the table in it. The cells are 10pt.</p>
			<table style="font-size: 50%; line-height: 1.2"><tr><td>10pt</td><td>10pt</td></tr></table>
		</div>

		<p class="caption">font-size: 20pt on a table, and font-size: 1rem on its second cell. The second cell is 11pt, the size of html, which no rule sets.</p>
		<table style="font-size: 20pt; line-height: 1.2"><tr><td>20pt</td><td style="font-size: 1rem">11pt</td></tr></table>

		<div style="line-height: 2.5; text-align: right">
			<p class="caption">line-height: 2.5 and text-align: right on a div. The cells' lines are 2.5 apart, and the th and the td are aligned right.</p>
			<table><tr><th style="width: 50mm">Header</th><td style="width: 50mm">First line<br>Second line</td></tr></table>
		</div>

		<p class="caption">text-align: right and line-height: 2.5 on a cell holding a nested table. The nested th and td are aligned right, and the td's lines are 2.5 apart.</p>
		<table style="line-height: 1.2"><tr><td style="text-align: right; line-height: 2.5; width: 120mm">
			<table><tr><th style="width: 40mm">Nested header</th><td style="width: 40mm">First line<br>Second line</td></tr></table>
		</td></tr></table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
