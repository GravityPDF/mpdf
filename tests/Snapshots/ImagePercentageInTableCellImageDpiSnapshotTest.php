<?php

namespace Snapshots;

/**
 * A percentage width, min-width or max-width on an image in a table cell in a document whose images are drawn at 300dpi,
 * where the size an image takes when given no width, and so what a percentage leaves it at, follows the DPI.
 *
 * @group snapshot
 */
class ImagePercentageInTableCellImageDpiSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'image-percentage-in-table-cell-image-dpi';
	}

	/**
	 * Tables whose cells hold pictures given no width of their own, each over a caption giving the widths to expect
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

		<h1>Percentages on an image in a table cell, at 300dpi</h1>
		<p class="caption">img_dpi is 300, so the 567px tiger is 48mm when given no width, and the 292px tapestry 24.7mm.
			Each table has three 60mm columns unless it says otherwise.</p>

		<h2>max-width narrows a picture, and never widens it</h2>
		<table class="full">
			<tr>
				<td width="33.3333%" class="shaded"><img src="img/tiger.jpg" style="max-width: 100%"><br>max-width: 100% (48mm)</td>
				<td width="33.3333%" class="shaded"><img src="img/tiger.jpg" style="max-width: 50%"><br>max-width: 50% (30mm)</td>
				<td class="shaded"><img src="img/tiger.jpg" style="min-width: 100%"><br>min-width: 100% (60mm)</td>
			</tr>
		</table>

		<h2>image-resolution overrides the document's DPI</h2>
		<table class="full">
			<tr>
				<td width="33.3333%" class="shaded"><img src="img/tiger.jpg" style="image-resolution: 72dpi; max-width: 100%"><br>72dpi, max-width: 100% (200mm, capped to 60mm)</td>
				<td width="33.3333%" class="shaded"><img src="img/bayeux2.jpg" style="image-resolution: 96dpi; max-width: 100%"><br>96dpi, max-width: 100% (77.3mm, capped to 60mm)</td>
				<td class="shaded"><img src="img/bayeux2.jpg" style="image-resolution: from-image; max-width: 100%"><br>from-image, the file's own 300dpi (24.7mm)</td>
			</tr>
		</table>

		<h2>An auto-width column takes the picture's own width</h2>
		<p class="caption">The table is given no width. The picture's column is 24.7mm and the picture given width: 100% fills it.</p>
		<table>
			<tr>
				<td>auto</td>
				<td class="shaded"><img src="img/bayeux2.jpg" style="width: 100%"></td>
			</tr>
		</table>

		<h2>In a table too wide for its page</h2>
		<p class="caption">Long unbreakable words in five columns. The picture with max-width: 100% keeps its 24.7mm and is shrunk with the table.</p>
		<table>
			<tr>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td>https://example.com/a-rather-long-unbreakable-url</td>
				<td class="shaded"><img src="img/bayeux2.jpg" style="max-width: 100%"></td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['img_dpi' => 300]);
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
