<?php

namespace Snapshots;

/**
 * The CSS outline properties do not stroke an element's text, and mPDF's own text-outline properties still do.
 *
 * @group snapshot
 */
class OutlinePropertiesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'outline-properties';
	}

	/**
	 * Text styled with each set of outline properties, under a caption saying how it should look
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
			div.sample { font-size: 20pt; margin-bottom: 2mm; }
		</style>

		<h1>Outline properties</h1>

		<h2>CSS outline</h2>
		<p class="caption">outline-style, outline-width and outline-color: the text is plain black, with no red stroke.</p>
		<div class="sample" style="outline-style: solid; outline-width: 1mm; outline-color: #cc0000">Outlined box</div>

		<p class="caption">outline-width: thick on its own: the text is plain black.</p>
		<div class="sample" style="outline-width: thick">Thick outline</div>

		<h2>mPDF's text-outline</h2>
		<p class="caption">text-outline-width and text-outline-color: the letters are stroked in red.</p>
		<div class="sample" style="text-outline-width: 0.3mm; text-outline-color: #cc0000">Stroked text</div>

		<p class="caption">The text-outline shorthand: the letters are stroked in blue.</p>
		<div class="sample" style="text-outline: 0.3mm #0033cc">Stroked text</div>

		<p class="caption">text-outline-color with outline-color beside it: the letters are stroked in red, not green.</p>
		<div class="sample" style="text-outline-width: 0.3mm; text-outline-color: #cc0000; outline-color: #00aa00">Stroked text</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
