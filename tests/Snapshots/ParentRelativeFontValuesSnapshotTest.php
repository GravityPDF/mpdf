<?php

namespace Snapshots;

/**
 * font-weight as a number, bolder and lighter, and font-size: larger and smaller, computed from the parent element's
 * values in the standard CSS mode, one case under each caption
 *
 * @group snapshot
 */
class ParentRelativeFontValuesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'parent-relative-font-values';
	}

	/**
	 * Boxes each styled with one value, under a caption saying how the text should look
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 1mm 0; }
			div.case h2 { margin: 1mm 0; font-size: 12pt; }
			table { border-collapse: collapse; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 2mm; }
		</style>

		<h1>Font values computed from the parent's</h1>

		<p class="caption">font-weight: 700, 600 and 500. The first two lines are bold; the third, below 600, is regular.</p>
		<div class="case">
			<p style="font-weight: 700">Weight 700: bold</p>
			<p style="font-weight: 600">Weight 600: bold</p>
			<p style="font-weight: 500">Weight 500: regular</p>
		</div>

		<p class="caption">bolder inside bolder, then lighter: 700, 900 and 700. All three lines are bold.</p>
		<div class="case">
			<div style="font-weight: bolder">bolder: 700, bold
				<div style="font-weight: bolder">bolder again: 900, bold
					<div style="font-weight: lighter">lighter: 700, bold</div>
				</div>
			</div>
		</div>

		<p class="caption">lighter inside 700, then lighter again: 400 and 100. Only the first words are bold.</p>
		<div class="case">
			<p style="font-weight: 700">Weight 700, <span style="font-weight: lighter">lighter: 400, <span style="font-weight: lighter">lighter again: 100</span></span></p>
		</div>

		<p class="caption">bolder inside 300, then bolder again: 400 and 700. Only the last words are bold.</p>
		<div class="case">
			<p style="font-weight: 300">Weight 300, <span style="font-weight: bolder">bolder: 400, <span style="font-weight: bolder">bolder again: 700</span></span></p>
		</div>

		<p class="caption">A heading set lighter steps down from its parent's normal weight, not from its own bold: the heading is regular.</p>
		<div class="case">
			<h2 style="font-weight: lighter">A lighter heading, regular</h2>
			<h2>A heading, bold</h2>
		</div>

		<p class="caption">b and strong are bold. font-weight: normal inside b is regular, and lighter inside strong steps down to 400, regular, with a b inside it bold again.</p>
		<div class="case">
			<p><b>Bold, <span style="font-weight: normal">normal inside b,</span> bold</b></p>
			<p><strong>Strong, <span style="font-weight: lighter">lighter inside strong, <b>b inside that</b></span></strong></p>
		</div>

		<p class="caption">A table at weight 600: the cells set lighter, a header cell and a data cell, are regular at 400, and the others bold.</p>
		<table style="font-weight: 600">
			<tr><th style="font-weight: lighter">Lighter header cell: regular</th><th>Header cell: bold</th></tr>
			<tr><td>600 from the table: bold</td><td style="font-weight: lighter">lighter: 400, regular</td></tr>
		</table>

		<p class="caption">The font shorthand's weights: 600 is bold, lighter inside it regular, and bolder inside that bold.</p>
		<div class="case">
			<div style="font: 600 9pt serif">font: 600, bold
				<p style="font: lighter 9pt serif">font: lighter, regular, <span style="font: bolder 9pt serif">font: bolder, bold</span></p>
			</div>
		</div>

		<p class="caption">font-size: larger and smaller from 10pt: each larger is 1.2 times its parent's size, each smaller its parent's divided by 1.2.</p>
		<div class="case" style="font-size: 10pt">
			10pt
			<div style="font-size: larger">larger: 12pt
				<div style="font-size: larger">larger again: 14.4pt, <span style="font-size: smaller">smaller: 12pt, <span style="font-size: smaller">smaller again: 10pt</span></span></div>
			</div>
		</div>

		<p class="caption">A table set larger, in a 9pt body: its first cell is 10.8pt, and a cell set smaller is back to 9pt.</p>
		<table style="font-size: larger">
			<tr><td>Larger table: 10.8pt</td><td style="font-size: smaller">Smaller cell: 9pt</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
