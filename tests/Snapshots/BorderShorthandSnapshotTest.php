<?php

namespace Snapshots;

/**
 * The border shorthand with its width, style and colour in any order, and dropped when a part is none of them
 *
 * @group snapshot
 */
class BorderShorthandSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'border-shorthand';
	}

	/**
	 * One case per order, under a caption saying what should be seen
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
			div.box { padding: 2mm; margin-bottom: 2mm; }
			div.kept { border: 2mm solid #cc0000; }
			div.kept.bogus { border: 1px solid bogus; }
			td.cell { border: solid 1mm #3366cc; padding: 2mm; }
		</style>

		<h1>Border shorthand</h1>

		<h2>Width, style and colour in any order</h2>
		<p class="caption">Each box has a 2mm solid red border on all four sides.</p>
		<div class="box" style="border: 2mm solid #cc0000">border: 2mm solid #cc0000</div>
		<div class="box" style="border: solid #cc0000 2mm">border: solid #cc0000 2mm</div>
		<div class="box" style="border: 2mm #cc0000 solid">border: 2mm #cc0000 solid</div>
		<div class="box" style="border: #cc0000 2mm solid">border: #cc0000 2mm solid</div>
		<div class="box" style="border: solid 2mm #cc0000">border: solid 2mm #cc0000</div>
		<div class="box" style="border: red 2mm solid">border: red 2mm solid</div>

		<h2>One side, and a colour function first</h2>
		<p class="caption">A 1mm dashed blue line under the text only.</p>
		<div class="box" style="border-bottom: dashed blue 1mm">border-bottom: dashed blue 1mm</div>
		<p class="caption">A 1.5mm double green border on all four sides.</p>
		<div class="box" style="border: rgb(0 128 0) 1.5mm double">border: rgb(0 128 0) 1.5mm double</div>

		<h2>Table cells and inline text</h2>
		<p class="caption">Each cell has a 1mm solid blue border. The word "boxed" has a 0.5mm dotted red border.</p>
		<table><tr><td class="cell">solid 1mm #3366cc</td><td class="cell">solid 1mm #3366cc</td></tr></table>
		<p>Text with a <span style="border: dotted #cc0000 0.5mm">boxed</span> word.</p>

		<h2>A border that is not a border is dropped</h2>
		<p class="caption">border: 1px solid bogus is dropped, so the 2mm solid red border of the earlier rule stays.</p>
		<div class="box kept bogus">border: 1px solid bogus</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
