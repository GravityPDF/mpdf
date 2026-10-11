<?php

namespace Snapshots;

/**
 * Outer box-shadows whose corners the blur, the spread and the radii shape: square corners rounded to the blur, a
 * spread rounding a square corner, a negative spread, elliptical and uneven radii, a blur wider than the box, and a
 * shadow cut out of a padding-box background
 *
 * @group snapshot
 */
class OuterBoxShadowSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'outer-box-shadow';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			div.box {
				width: 60mm;
				height: 14mm;
				margin: 0 0 10mm 10mm;
				padding: 2mm;
				background-color: #eeeeff;
			}
		</style>

		<h1>mPDF</h1>
		<h2>box-shadow</h2>

		<div class="box" style="box-shadow: 3mm 3mm 4mm #cc0000">square corners, rounded by the 4 mm blur</div>
		<div class="box" style="box-shadow: 0 0 2mm 3mm #cc0000">square corners, rounded by a 3 mm spread</div>
		<div class="box" style="box-shadow: 5mm 5mm 4mm -3mm #0000cc">negative spread, offset clear of the box</div>
		<div class="box" style="border-radius: 6mm; box-shadow: 2mm 2mm 4mm 2mm rgba(0, 0, 200, 0.6)">6 mm radius, blur, spread and alpha</div>
		<div class="box" style="border-radius: 12mm / 2mm; box-shadow: 0 0 6mm #00aa00">12 mm / 2 mm radius, 6 mm blur</div>
		<div class="box" style="border-radius: 14mm 2mm 14mm 2mm; box-shadow: 3mm 3mm 6mm #00aa00">14 mm and 2 mm radii, 6 mm blur</div>
		<div class="box" style="height: 4mm; box-shadow: 0 0 16mm -6mm #cc0000">blur wider than the box, negative spread</div>
		<div class="box" style="border: 3mm solid rgba(0, 0, 0, 0.2); background-clip: padding-box; box-shadow: 3mm 3mm 3mm #cc0000">padding-box background under a translucent border</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
