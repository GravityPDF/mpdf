<?php

namespace Snapshots;

/**
 * A percentage width, min-width or max-width on an image in a table cell, resolved against the cell.
 *
 * @group snapshot
 */
class ImagePercentageInTableCellSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'image-percentage-in-table-cell';
	}

	/**
	 * Tables whose cells hold pictures sized by a percentage, each over a caption giving the widths to expect
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			table { border-collapse: collapse; margin-bottom: 2mm; }
			table.full { width: 100%; }
			td { border: 0.2mm solid #999; padding: 0; vertical-align: top; font-size: 8pt; color: #606060; }
			td.shaded { background-color: #eef; }
			h2 { margin: 5mm 0 1mm 0; font-size: 11pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
		</style>

		<h1>Percentages on an image in a table cell</h1>

		<h2>max-width: 100% keeps the declared column widths</h2>
		<p class="caption">Columns of 20%, 20% and 60%. The picture fills its 36mm column and does not widen it.</p>
		<table class="full">
			<tr>
				<td width="20%">20%</td>
				<td width="20%" class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 100%"></td>
				<td width="60%">60%</td>
			</tr>
		</table>

		<h2>max-width, width and min-width are of the cell</h2>
		<p class="caption">Three 60mm columns in each row.</p>
		<table class="full">
			<tr>
				<td width="33.3333%" class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 20%"><br>max-width: 20%</td>
				<td width="33.3333%" class="shaded"><img src="img/bayeux2.jpg" style="width: 50%"><br>width: 50%</td>
				<td class="shaded"><img src="img/bayeux2.jpg" width="100%"><br>width="100%"</td>
			</tr>
			<tr>
				<td width="33.3333%" class="shaded"><img src="img/bayeux2.jpg" style="width: 10mm; min-width: 50%"><br>width: 10mm; min-width: 50%</td>
				<td width="33.3333%" class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 20mm"><br>max-width: 20mm</td>
				<td class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 100%; border-radius: 50%"><br>max-width: 100%; border-radius: 50%</td>
			</tr>
		</table>

		<h2>In a nested table</h2>
		<p class="caption">The inner table fills the right half of the page. The picture fills the inner right cell.</p>
		<table class="full">
			<tr>
				<td width="50%">50%</td>
				<td width="50%">
					<table class="full" style="margin: 0">
						<tr>
							<td width="50%">inner 50%</td>
							<td width="50%" class="shaded"><img src="img/bayeux2.jpg" style="width: 100%"></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>

		<h2>In a table of width: auto</h2>
		<p class="caption">Sized by its content, as a table given no width is. The picture given max-width: 100% keeps its own 400px.</p>
		<table style="width: auto">
			<tr>
				<td>auto</td>
				<td class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 100%"></td>
			</tr>
		</table>

		<h2>In a table shrunk to fit its page</h2>
		<p class="caption">A 300mm column and a 60mm one, halved to fit. The picture given min-width: 100% fills the 30mm it gets.</p>
		<table>
			<tr>
				<td style="width: 300mm">300mm</td>
				<td style="width: 60mm" class="shaded"><img src="img/bayeux2.jpg" style="width: 10mm; min-width: 100%"></td>
			</tr>
		</table>

		<h2>In a table too wide for its page</h2>
		<p class="caption">Long unbreakable words in five columns. The picture with max-width: 100% is shrunk with the table rather than squeezed to nothing.</p>
		<table>
			<tr>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 100%"></td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
