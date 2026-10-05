<?php

namespace Snapshots;

/**
 * A themed report whose stylesheet leans on inheritance: a shadowed callout with paragraphs and a list, tables that
 * set the transform and variant of their cells, a panel cell holding a nested table, a positioned stamp, and headings
 * in capitals with codes left as written. Written under each value of cssMode, with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class InheritedReportSnapshot extends Snapshot
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
		return 'inherited-report-' . $this->cascade();
	}

	/**
	 * The report, each part under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-family: sans-serif; font-size: 9pt; color: #243447; }
			h1 { font-size: 16pt; margin: 0 0 1mm 0; color: #1e5f74; }
			h2 { font-size: 11pt; margin: 5mm 0 1mm 0; text-transform: uppercase; color: #1e5f74; }
			h2 .code { text-transform: none; color: #b33939; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; text-shadow: none; }

			.callout { background-color: #f1f6fa; border-left: 1mm solid #1e5f74; padding: 2mm 3mm; text-shadow: 0.35mm 0.35mm #b8cde0; }
			.callout p { margin: 0 0 1mm 0; }
			.callout li { margin: 0.5mm 0; }

			table { border-collapse: collapse; }
			td, th { border: 0.2mm solid #b8cde0; padding: 1mm 2mm; }
			table.codes { text-transform: uppercase; }
			table.figures { font-variant: small-caps; text-shadow: 0.3mm 0.3mm #e1b12c; }
			td.panel { color: #1e5f74; font-weight: bold; font-family: monospace; font-size: 10pt; vertical-align: top; }
			td.notes { vertical-align: top; }

			.stamp { position: absolute; top: 12mm; left: 140mm; width: 55mm; border: 0.5mm solid #b33939; color: #b33939; font-size: 12pt; word-spacing: 4mm; text-align: center; }
		</style>

		<div class="stamp"><p>DRAFT FOR REVIEW</p></div>

		<h1>Quarterly operations report</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>
		<p class="caption">The red stamp, top right, is a positioned block with word-spacing: 4mm. Standard: its words are spread 4mm apart. Legacy: they are set with ordinary spaces.</p>

		<h2>Highlights for <span class="code">q3-2026</span></h2>
		<p class="caption">The heading is in capitals and the code in it sets text-transform: none. Standard: the code is red and in lower case, as written. Legacy: it is red and in capitals.</p>
		<p class="caption">The callout sets a pale blue text shadow. Standard: its paragraphs and list items are shadowed. Legacy: none of its text is, the shadow stopping at its child blocks.</p>
		<div class="callout">
			<p>Throughput rose for the <em>third</em> quarter running, and <strong>two</strong> sites met every target.</p>
			<ul>
				<li>Order backlog cleared by week 9</li>
				<li>Returns down to 1.8% of orders</li>
			</ul>
		</div>

		<h2>Site codes</h2>
		<p class="caption">The table sets text-transform: uppercase. Standard: every code is in capitals. Legacy: the codes are as written.</p>
		<table class="codes">
			<tr><td>north-01</td><td>north-02</td><td>coast-07</td><td>inland-12</td></tr>
		</table>

		<h2>Key figures</h2>
		<p class="caption">The table sets small capitals and a gold text shadow. Standard: the labels are in small capitals and every cell is shadowed. Legacy: the cells are as written, with no shadow.</p>
		<table class="figures">
			<tr><td>Orders shipped</td><td>48,210</td></tr>
			<tr><td>Average lead time</td><td>2.4 days</td></tr>
			<tr><td>Customer rating</td><td>4.7 of 5</td></tr>
		</table>

		<h2>Site panel</h2>
		<p class="caption">The panel cell is teal, bold, 10pt and monospaced, and holds a nested table. Standard: the nested table's cells are teal, bold, 10pt and monospaced too. Legacy: they are in the body's font, size and colour, and not bold.</p>
		<table>
			<tr>
				<td class="panel">
					North region
					<table>
						<tr><td>north-01</td><td>12,400</td></tr>
						<tr><td>north-02</td><td>9,850</td></tr>
					</table>
				</td>
				<td class="notes">Both northern sites ran a second shift from July. The panel beside this note repeats their order counts.</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
