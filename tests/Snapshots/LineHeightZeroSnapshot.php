<?php

namespace Snapshots;

/**
 * Zero, small and negative line heights, written under a value of cssMode
 *
 * @group snapshot
 */
abstract class LineHeightZeroSnapshot extends Snapshot
{

	/**
	 * @return string A CssMode value
	 */
	abstract protected function mode();

	/**
	 * @return string[] The caption over each case, saying what should be seen in this mode, keyed by zero, zero-length,
	 *                  zero-parent, one-mm, half, negative and cell
	 */
	abstract protected function captions();

	/**
	 * Paragraphs of three short lines with a small, zero or negative line-height, each under a caption saying what
	 * should be seen
	 */
	public function generatePdf()
	{
		$caption = $this->captions();

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
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
		<p class="caption"><?php echo $caption['zero'] ?></p>
		<div class="case">
			<p class="lines zero">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
			<p class="after">The green line after line-height: 0</p>
		</div>

		<p class="caption"><?php echo $caption['zero-length'] ?></p>
		<div class="case">
			<p class="lines zero-length">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
			<p class="after">The green line after line-height: 0px</p>
		</div>

		<p class="caption"><?php echo $caption['zero-parent'] ?></p>
		<div class="case zero-parent">
			<p class="lines">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
		</div>
		<p class="after">The green line after the div</p>

		<h2>Smaller than the font</h2>
		<p class="caption"><?php echo $caption['one-mm'] ?></p>
		<div class="case">
			<p class="lines one-mm">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Third</p>
			<p class="after">The green line after line-height: 1mm</p>
		</div>

		<p class="caption"><?php echo $caption['half'] ?></p>
		<div class="case">
			<p class="lines half">First<br>Second<br>Third</p>
			<p class="after">The green line after line-height: 0.5</p>
		</div>

		<h2>Negative line heights</h2>
		<p class="caption"><?php echo $caption['negative'] ?></p>
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
		<p class="caption"><?php echo $caption['cell'] ?></p>
		<table>
			<tr>
				<td>First<br>Second<br>Third</td>
				<td style="line-height: 0">First<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Second</td>
				<td>One line</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->mode()]);

		$this->mpdf->WriteHTML($html);
	}

}
