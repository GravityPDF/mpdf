<?php

namespace Snapshots;

/**
 * Rows and row groups hand their inherited properties to their cells under the standard cascade, one case per
 * caption: a row's colour, a tbody's font, a thead's alignment against a th's defaults, a tfoot's weight, a rule on the
 * tbody a row written straight into the table is in, a row's size against its row group's, and a cell's own value.
 *
 * @group snapshot
 */
class RowPropertiesToCellsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'row-properties-to-cells';
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
			p.caption { margin: 5mm 0 1mm 0; font-size: 8pt; color: #606060; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 2mm; width: 40mm; }
			table.implied tbody { color: #0a7d3b; font-style: italic; }
		</style>

		<h1>Rows and row groups pass their properties to their cells</h1>

		<p class="caption">color: #c00000 on the second row. Its two cells are red; the first row's are black.</p>
		<table>
			<tr><td>First row</td><td>First row</td></tr>
			<tr style="color: #c00000"><td>Second row</td><td>Second row</td></tr>
		</table>

		<p class="caption">font-family: monospace and text-transform: uppercase on a tbody. Every cell in it is monospaced and in capitals.</p>
		<table>
			<tbody style="font-family: monospace; text-transform: uppercase">
				<tr><td>first cell</td><td>second cell</td></tr>
				<tr><td>third cell</td><td>fourth cell</td></tr>
			</tbody>
		</table>

		<p class="caption">text-align: right and font-weight: normal on a thead. The th is right-aligned, as it inherits an alignment, and still bold. The td beside it is right-aligned and not bold. The th in the tbody below sets nothing and inherits no alignment, so it is centred.</p>
		<table>
			<thead style="text-align: right; font-weight: normal"><tr><th>Header</th><td>Data in the head</td></tr></thead>
			<tbody><tr><th>Centred</th><td>Body data</td></tr></tbody>
		</table>

		<p class="caption">font-weight: bold and color: #1e5f74 on a tfoot. Its cells are bold and teal; the tbody's are not.</p>
		<table>
			<tbody><tr><td>Item</td><td>12.00</td></tr></tbody>
			<tfoot style="font-weight: bold; color: #1e5f74"><tr><td>Total</td><td>12.00</td></tr></tfoot>
		</table>

		<p class="caption">A rule for tbody, and rows written straight into the table with no tbody tag. The cells are green and italic, as the rows are in a tbody all the same.</p>
		<table class="implied">
			<tr><td>First row</td><td>First row</td></tr>
			<tr><td>Second row</td><td>Second row</td></tr>
		</table>

		<p class="caption">font-size: 150% on a tbody in a 10pt table, and font-size: 80% on its second row. The first row's cells are 15pt, the second row's 12pt.</p>
		<table>
			<tbody style="font-size: 150%">
				<tr><td>15pt</td><td>15pt</td></tr>
				<tr style="font-size: 80%"><td>12pt</td><td>12pt</td></tr>
			</tbody>
		</table>

		<p class="caption">color: #c00000 on a row whose second cell sets color: #1f4fa0. The first cell is red and the second blue.</p>
		<table>
			<tr style="color: #c00000"><td>Row colour</td><td style="color: #1f4fa0">Cell colour</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
