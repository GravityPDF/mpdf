<?php

namespace Snapshots;

/**
 * Stylesheet rules that name sup and sub, each case under a caption saying what it draws. The rules apply in both CSS
 * modes and take over from mPDF's own size and raise. Legacy reads no child selectors, so its table cell case looks
 * like the first.
 *
 * @group snapshot
 */
abstract class SupSubRulesSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function mode();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'sup-sub-rules-' . $this->mode();
	}

	/**
	 * One rule per case, each scoped to its paragraph, under a caption
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			p.case { font-size: 20pt; margin: 0; }

			p.colour sup, p.colour sub { color: #c00000; }
			p.size sup, p.size sub { font-size: 75%; }
			p.baseline sup, p.baseline sub { vertical-align: baseline; }
			p.normalize sub, p.normalize sup { font-size: 75%; line-height: 0; position: relative; vertical-align: baseline; }
			p.swapped sup { vertical-align: sub; }
			p.swapped sub { vertical-align: super; }
			td > sup { color: #c00000; font-size: 75%; }
			.classed { color: #0050c0; }
		</style>

		<h1>Rules naming sup and sub</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->mode(); ?>.</p>

		<p class="caption">No rule: drawn at 55%, raised by half the size of the text around it and dropped by a fifth of it.</p>
		<p class="case">E = mc<sup>2</sup> and H<sub>2</sub>O</p>

		<p class="caption">p.colour sup, p.colour sub { color: #c00000 }: red.</p>
		<p class="case colour">E = mc<sup>2</sup> and H<sub>2</sub>O</p>

		<p class="caption">font-size: 75%: larger than the first case and raised and dropped as far.</p>
		<p class="case size">E = mc<sup>2</sup> and H<sub>2</sub>O</p>

		<p class="caption">vertical-align: baseline: at 55% but on the baseline.</p>
		<p class="case baseline">E = mc<sup>2</sup> and H<sub>2</sub>O</p>

		<p class="caption">normalize.css's rule for sub and sup: at 75% and on the baseline.</p>
		<p class="case normalize">E = mc<sup>2</sup> and H<sub>2</sub>O</p>

		<p class="caption">sup { vertical-align: sub } and sub { vertical-align: super }: the 2 of mc2 dropped and the 2 of H2O raised, each moved once.</p>
		<p class="case swapped">E = mc<sup>2</sup> and H<sub>2</sub>O</p>

		<p class="caption">td &gt; sup { color: #c00000; font-size: 75% } in a table cell: red and larger under standard. Legacy reads no child selectors.</p>
		<table><tr><td style="font-size: 20pt">E = mc<sup>2</sup></td></tr></table>

		<p class="caption">A class rule, .classed { color: #0050c0 }: blue.</p>
		<p class="case">E = mc<sup class="classed">2</sup> and H<sub class="classed">2</sub>O</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->mode()]);
		$this->mpdf->WriteHTML($html);
	}

}
