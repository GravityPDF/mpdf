<?php

namespace Snapshots;

/**
 * line-height: 0 giving lines no height rather than a normal one, a line height smaller than the font kept rather than
 * stretched down to the baseline, and a negative line-height ignored.
 *
 * @group snapshot
 */
class LineHeightZeroSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'line-height-zero';
	}

	/**
	 * Paragraphs of three short lines with a small, zero or negative line-height, each under a caption saying what
	 * should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 6mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 3mm 0; font-size: 8pt; color: #606060; }
			div.case { margin: 0 0 4mm 0; }
			p.lines { margin: 0; font-size: 12pt; background-color: #d6eaf8; }
			p.after { margin: 0; font-size: 9pt; color: #0a7d32; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 0 2mm; font-size: 12pt; background-color: #d6eaf8; }

			p.zero { line-height: 0; }
			p.zero-length { line-height: 0px; }
			div.zero-parent { line-height: 0; }
			p.one-mm { line-height: 1mm; }
			p.half { line-height: 0.5; }
			div.double { line-height: 2; }
			p.negative { line-height: -1; }
			p.negative-length { line-height: -2mm; }
		</style>

		<h1>Zero, small and negative line heights</h1>

		<h2>line-height: 0</h2>
		<p class="caption">The three lines are drawn at the same height, the blue background has no height, and the green line starts straight after it, over the lower half of the text.</p>
		<div class="case">
			<p class="lines zero">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
			<p class="after">The green line after line-height: 0</p>
		</div>

		<p class="caption">line-height: 0px: the same.</p>
		<div class="case">
			<p class="lines zero-length">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
			<p class="after">The green line after line-height: 0px</p>
		</div>

		<p class="caption">line-height: 0 on the div around the paragraph: the same.</p>
		<div class="case zero-parent">
			<p class="lines">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
		</div>
		<p class="after">The green line after the div</p>

		<h2>Smaller than the font</h2>
		<p class="caption">line-height: 1mm: each line 1mm below the one before, so they overlap, on a background 3mm tall.</p>
		<div class="case">
			<p class="lines one-mm">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
			<p class="after">The green line after line-height: 1mm</p>
		</div>

		<p class="caption">line-height: 0.5: each line half the font size (6pt) below the one before, so they overlap, on a background 18pt tall.</p>
		<div class="case">
			<p class="lines half">First<br>Second<br>Third</p>
			<p class="after">The green line after line-height: 0.5</p>
		</div>

		<h2>Negative line heights</h2>
		<p class="caption">line-height: -1 and -2mm inside a div with line-height: 2 are ignored: both paragraphs are double spaced, like the third, which sets nothing.</p>
		<div class="case double">
			<p class="lines negative">First<br>Second<br>Third</p>
		</div>
		<div class="case double">
			<p class="lines negative-length">First<br>Second<br>Third</p>
		</div>
		<div class="case double">
			<p class="lines">First<br>Second<br>Third</p>
		</div>

		<h2>Table cell</h2>
		<p class="caption">line-height: 0 in the middle cell: its lines overlap and the row is only as tall as the other cells, which are normal.</p>
		<table>
			<tr>
				<td>First<br>Second<br>Third</td>
				<td style="line-height: 0">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second</td>
				<td>One line</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
