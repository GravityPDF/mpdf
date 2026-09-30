<?php

namespace Snapshots;

/**
 * Hex colours with an alpha, channels and alphas outside their range, and rebeccapurple
 *
 * @group snapshot
 */
class ColorValueSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'color-value';
	}

	/**
	 * One case per value, under a caption saying what should be seen
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
			p.text { font-size: 12pt; margin: 0 0 2mm 0; }
			div.halves { background: linear-gradient(to right, #000000 50%, #ffffff 50%); border: 0.2mm solid #999999; padding: 3mm 0; margin-bottom: 2mm; }
			div.band { height: 6mm; }
		</style>

		<h1>Colour values</h1>

		<h2>Hex colours with an alpha</h2>
		<p class="caption">background: #f008, red at 53% opacity, across a block that is black on the left and white on the right: dark red on the left, pink on the right.</p>
		<div class="halves"><div class="band" style="background: #f008"></div></div>

		<p class="caption">background: #0000ff80, blue at 50% opacity: dark blue on the left, light blue on the right.</p>
		<div class="halves"><div class="band" style="background: #0000ff80"></div></div>

		<h2>Channels outside their range</h2>
		<p class="caption">rgb(255.5, 0, 0) and rgb(300, -20, 0) are clamped to red. Both lines are red.</p>
		<p class="text" style="color: rgb(255.5, 0, 0)">rgb(255.5, 0, 0) is red</p>
		<p class="text" style="color: rgb(300, -20, 0)">rgb(300, -20, 0) is red</p>

		<p class="caption">rgba(0, 0, 255, 1.5), an alpha above 1, is clamped to 1: opaque blue across both halves.</p>
		<div class="halves"><div class="band" style="background-color: rgba(0, 0, 255, 1.5)"></div></div>

		<h2>Named colours</h2>
		<p class="caption">rebeccapurple is purple, and violetred, which mPDF has long accepted, is still pink.</p>
		<p class="text" style="color: rebeccapurple">rebeccapurple</p>
		<p class="text" style="color: violetred">violetred</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
