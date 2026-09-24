<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class PdfX4RgbAndGreySnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'pdfx4-rgb-and-grey';
	}

	/**
	 * Draws grey and RGB colour side by side in a PDF/X-4 document printing to an RGB output intent, where
	 * grey is written in the ICC-based /CSGRAY space and RGB in /CSRGB. Each grey sample has to draw as the
	 * same neutral DeviceGray would, and each RGB sample as it would with no output intent.
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table.samples { border-collapse: collapse; width: 100%; }
			table.samples td { padding: 2mm; border: 0.3mm solid #444444; vertical-align: middle; }
			td.label { font-weight: bold; width: 35mm; }
			td.swatch { height: 12mm; width: 23mm; }
		</style>

		<h1>PDF/X-4, grey and RGB</h1>

		<p style="color: #555555; border: 0.5mm solid #808080; padding: 2mm">Grey text on white, in a grey border. <span style="color: #c62828">Red text</span>
			and <span style="color: #1565c0">blue text</span> sit in the same line.</p>

		<table class="samples">
			<tr>
				<td class="label">Fills</td>
				<td class="swatch" style="background: #000000"></td>
				<td class="swatch" style="background: #808080"></td>
				<td class="swatch" style="background: #d0d0d0"></td>
				<td class="swatch" style="background: #c62828"></td>
				<td class="swatch" style="background: #2e7d32"></td>
				<td class="swatch" style="background: #1565c0"></td>
			</tr>
			<tr>
				<td class="label">Gradients</td>
				<td colspan="3" class="swatch" style="background: linear-gradient(to right, #000000, #ffffff)"></td>
				<td colspan="3" class="swatch" style="background: linear-gradient(to right, #1565c0, #e53935)"></td>
			</tr>
			<tr>
				<td class="label">Grey to colour</td>
				<td colspan="6" class="swatch" style="background: linear-gradient(to right, #808080, #2e7d32)"></td>
			</tr>
			<tr>
				<td class="label">Translucent</td>
				<td colspan="3" class="swatch" style="background: linear-gradient(to right, rgba(0, 0, 0, 1), rgba(0, 0, 0, 0))"></td>
				<td colspan="3" class="swatch" style="background: linear-gradient(to right, rgba(198, 40, 40, 1), rgba(21, 101, 192, 0.2))"></td>
			</tr>
			<tr>
				<td class="label">Images</td>
				<td colspan="2"><img src="img/grey-gradient.jpg" style="height: 10mm"></td>
				<td colspan="2"><img src="img/grey-gradient.png" style="height: 10mm"></td>
				<td colspan="2"><img src="img/tiger.jpg" style="height: 10mm"></td>
			</tr>
		</table>

		<p>&nbsp;</p>
		<div style="width: 60mm; padding: 4mm; background: #ffffff; box-shadow: 1.5mm 1.5mm 2mm #555555">A grey shadow</div>
		<p>&nbsp;</p>
		<div style="width: 60mm; padding: 4mm; background: #ffffff; box-shadow: 1.5mm 1.5mm 2mm #1565c0">A blue shadow</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['PDFX' => true, 'ICCProfile' => __DIR__ . '/../data/icc/rgb-printer-header.icc', 'PDFXauto' => true]);
		// PDF/X needs a title, and would otherwise take the name of the file it is written to
		$this->mpdf->SetTitle('PDF/X-4, grey and RGB');
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}
}
