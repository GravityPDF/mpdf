<?php

namespace Snapshots;

/**
 * A contract amendment marked up the way an editor marks changes: overlined section headings with plain codes in them,
 * a withdrawn clause struck through in red with links and notes set to no decoration, and an inserted clause
 * underlined in green holding a list, a floated margin note and a table of fees. Written under each value of cssMode,
 * with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class DecorationsAmendmentSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function cascade();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'decorations-amendment-' . $this->cascade();
	}

	/**
	 * The amendment, each part under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: serif; font-size: 10pt; color: #2b2b2b; }
			h1 { font-size: 16pt; margin: 0 0 1mm 0; }
			h2 { font-size: 12pt; margin: 6mm 0 1mm 0; color: #34495e; text-decoration: overline; }
			h2 .code { font-family: monospace; font-size: 10pt; color: #7f8c8d; text-decoration: none; }
			p.caption { font-family: sans-serif; font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; text-decoration: none; }
			a { color: #1f5fbf; text-decoration: none; }

			.withdrawn { color: #b33939; text-decoration: line-through; }
			.withdrawn .note { color: #555555; text-decoration: none; font-style: italic; }
			.inserted { color: #2d7d46; text-decoration: underline; }
			.inserted li { margin: 0.5mm 0; }
			.margin-note { float: right; width: 55mm; margin: 0 0 2mm 3mm; padding: 1.5mm; background-color: #f3f7f4; border: 0.2mm solid #2d7d46; font-size: 8pt; }

			table.fees { border-collapse: collapse; margin-top: 2mm; }
			table.fees td, table.fees th { border: 0.2mm solid #9fbfa8; padding: 1mm 2mm; }
		</style>

		<h1>Amendment No. 3 to the Supply Agreement</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>

		<h2>Clause 4.2 <span class="code">[ref 4.2-b]</span></h2>
		<p class="caption">The heading is overlined and the code in it sets text-decoration: none. Standard: the heading's line runs over the code too. Legacy: it stops at the code.</p>

		<p class="caption">The withdrawn clause is struck through in red. Its link and its italic note set text-decoration: none. Standard: both are struck through with the clause's red line. Legacy: neither is struck through.</p>
		<div class="withdrawn">
			<p>The Supplier shall deliver within fourteen days as set out in the <a href="https://example.com/schedule">delivery schedule</a>.</p>
			<p class="note">Withdrawn by agreement of both parties.</p>
		</div>

		<h2>Clause 4.3 <span class="code">[new]</span></h2>
		<p class="caption">The inserted clause is underlined in green. Standard: its paragraphs and list items are underlined in the clause's green, the link too, and the floated margin note and the fees table are not. Legacy: the paragraphs and items are underlined, the link is not, and the margin note is.</p>
		<div class="inserted">
			<div class="margin-note">Margin note: this clause replaces 4.2 from 1 October.</div>
			<p>The Supplier shall deliver within <strong>ten working days</strong> of each order, as set out in the <a href="https://example.com/schedule">revised schedule</a>.</p>
			<ul>
				<li>Orders placed before noon count from that day</li>
				<li>Public holidays are not working days</li>
			</ul>
			<table class="fees">
				<tr><th>Delivery</th><th>Fee</th></tr>
				<tr><td>Standard</td><td>included</td></tr>
				<tr><td>Express</td><td>45.00</td></tr>
			</table>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
