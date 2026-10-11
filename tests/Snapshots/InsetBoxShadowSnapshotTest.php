<?php

namespace Snapshots;

/**
 * Inset box-shadows, drawn inside the padding box over the background: offset, blurred, spread, rounded, and beside
 * an outer shadow
 *
 * @group snapshot
 */
class InsetBoxShadowSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'inset-box-shadow';
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
				height: 26mm;
				margin: 0 0 6mm 6mm;
				padding: 2mm;
				background-color: #eeeeff;
			}
		</style>

		<h1>mPDF</h1>
		<h2>box-shadow: inset</h2>

		<div class="box" style="box-shadow: inset 2mm 2mm 2mm #cc0000">offset and blur</div>
		<div class="box" style="box-shadow: inset 0 0 4mm #cc0000">blur alone</div>
		<div class="box" style="box-shadow: inset 0 0 3mm 2mm rgba(0, 0, 200, 0.6)">blur, spread and alpha</div>
		<div class="box" style="border: 2mm solid #000000; border-radius: 8mm; box-shadow: inset 0 0 3mm 1mm #cc0000">rounded, blurred</div>
		<div class="box" style="border-radius: 8mm; box-shadow: inset 3mm 3mm 2mm #00aa00, 3mm 3mm 2mm #0000cc">inset and outer</div>
		<div class="box" style="background-image: linear-gradient(#ffffff, #999999); box-shadow: inset 0 0 4mm #cc0000">over a gradient</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
