<?php

namespace Snapshots;

/**
 * A picture sized by a percentage of its cell in a rotated table, drawn where it starts and after it moves to a new page.
 *
 * @group snapshot
 */
class ImagePercentageInRotatedTableSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'image-percentage-in-rotated-table';
	}

	/**
	 * The same rotated table twice: at the top of the first page, then too low on it to fit, so it moves to the second
	 */
	public function generatePdf()
	{
		$table = '<table rotate="90">
			<tr>
				<td style="width: 70mm"><img src="img/bayeux2.jpg" style="width: 70mm"></td>
				<td style="width: 40mm" class="shaded"><img src="img/bayeux2.jpg" width="400" style="max-width: 100%"></td>
				<td>{caption}</td>
			</tr>
		</table>';

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999; padding: 0; vertical-align: top; font-size: 8pt; color: #606060; }
			td.shaded { background-color: #eef; }
			h2 { margin: 5mm 0 1mm 0; font-size: 11pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
		</style>

		<h1>A percentage on an image in a rotated table</h1>
		<p class="caption">A 70mm column and a 40mm one holding a 400px picture given max-width: 100%. The picture fills
			its 40mm column wherever the table is drawn.</p>

		<?php echo str_replace('{caption}', 'At the top of a page', $table) ?>

		<div style="height: 30mm"></div>
		<p class="caption">The same table below does not fit in what is left of this page, so it moves to the next.</p>

		<?php echo str_replace('{caption}', 'Moved to a new page', $table) ?>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
