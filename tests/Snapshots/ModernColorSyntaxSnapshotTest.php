<?php

namespace Snapshots;

/**
 * rgb() and hsl() written with spaces, with a slash before the alpha, and with the hue as an angle, in each property
 * that takes a colour
 *
 * @group snapshot
 */
class ModernColorSyntaxSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'modern-color-syntax';
	}

	/**
	 * One case per property, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 4mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.case { padding: 2mm; margin-bottom: 2mm; }

			p.text { color: rgb(200 0 0); font-size: 12pt; }
			div.halves { background: linear-gradient(to right, #000000 50%, #ffffff 50%); border: 0.2mm solid #999999; padding: 3mm 0; }
			div.red-band { background-color: rgb(255 0 0 / 50%); height: 6mm; }
			div.green-band { background: hsl(120 100% 40% / .5); height: 6mm; }
			div.border { border: 1.5mm solid rgb(0 0 200); }
			div.sides { border: 1.5mm solid; border-color: rgb(200 0 0) hsl(120deg 100% 30%); }
			div.shadow { background-color: #ffffff; border: 0.2mm solid #999999; box-shadow: 2mm 2mm rgb(0 0 255 / 50%); width: 60mm; }
			p.text-shadow { font-size: 16pt; font-weight: bold; text-shadow: 0.6mm 0.6mm rgb(255 0 0); }
			div.gradient { background: linear-gradient(to right, rgb(255 0 0), hsl(240 100% 50%)); height: 8mm; }
			td { width: 25mm; height: 8mm; border: 0.2mm solid #999999; }
		</style>

		<h1>rgb() and hsl() written with spaces</h1>

		<h2>color</h2>
		<p class="caption">color: rgb(200 0 0). The line below is red.</p>
		<p class="text">This paragraph is red.</p>

		<h2>background-color with a slash alpha</h2>
		<p class="caption">rgb(255 0 0 / 50%) across a block that is black on the left and white on the right: the band is dark red on the left and pink on the right.</p>
		<div class="halves"><div class="red-band"></div></div>

		<p class="caption">hsl(120 100% 40% / .5) the same way: dark green on the left and light green on the right.</p>
		<div class="halves"><div class="green-band"></div></div>

		<h2>border and border-color</h2>
		<p class="caption">border: 1.5mm solid rgb(0 0 200). A blue border on all four sides.</p>
		<div class="case border">Blue border</div>

		<p class="caption">border-color: rgb(200 0 0) hsl(120deg 100% 30%). Red at the top and bottom, green at the left and right.</p>
		<div class="case sides">Red and green border</div>

		<h2>Shadows</h2>
		<p class="caption">box-shadow: 2mm 2mm rgb(0 0 255 / 50%). A light blue shadow below and to the right of the box.</p>
		<div class="case shadow">Box with a shadow</div>

		<p class="caption">text-shadow: 0.6mm 0.6mm rgb(255 0 0). Black text with a red shadow.</p>
		<p class="text-shadow">Text with a shadow</p>

		<h2>Gradient</h2>
		<p class="caption">linear-gradient(to right, rgb(255 0 0), hsl(240 100% 50%)). Red on the left, through purple, to blue on the right.</p>
		<div class="gradient"></div>

		<h2>Hue as an angle</h2>
		<p class="caption">hsl(120deg 100% 50%), hsl(0.5turn 100% 50%), hsl(4.18879rad 100% 50%) and hsl(-60 100% 50%): green, cyan, blue and magenta.</p>
		<table>
			<tr>
				<td style="background-color: hsl(120deg 100% 50%)"></td>
				<td style="background-color: hsl(0.5turn 100% 50%)"></td>
				<td style="background-color: hsl(4.18879rad 100% 50%)"></td>
				<td style="background-color: hsl(-60 100% 50%)"></td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
