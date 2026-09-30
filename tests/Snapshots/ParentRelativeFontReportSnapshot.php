<?php

namespace Snapshots;

/**
 * A report styled with a theme that sets weights as numbers, bolder and lighter, and sizes as larger and smaller:
 * headings, a summary table with a nested breakdown, a list and inline markup. Written under each value of cssMode,
 * with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class ParentRelativeFontReportSnapshot extends Snapshot
{

	/**
	 * @return string A CssMode constant
	 */
	abstract protected function cssMode();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'parent-relative-font-report-' . $this->cssMode();
	}

	/**
	 * The report, each part under a caption saying what the standard and the legacy mode draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			h1 { font-size: 16pt; font-weight: 700; margin: 0 0 1mm 0; }
			h1 small { font-size: smaller; font-weight: lighter; color: #6c757d; }
			h2 { font-size: 11pt; font-weight: 600; margin: 4mm 0 1mm 0; }
			h3 { font-weight: lighter; font-size: larger; margin: 2mm 0 1mm 0; }
			p.caption { font-size: 7.5pt; font-weight: normal; color: #6c757d; margin: 0 0 1.5mm 0; }
			.lead { font-size: larger; font-weight: 300; }
			.lead strong { font-weight: bolder; }
			.lead em { font-weight: bolder; font-style: normal; }
			.lead em b { font-weight: bolder; }

			table.summary { border-collapse: collapse; width: 100%; font-weight: 500; }
			table.summary th { font-weight: 700; background-color: #e9ecef; }
			table.summary td, table.summary th { border: 0.2mm solid #ced4da; padding: 1mm 2mm; }
			table.summary td.total { font-weight: bolder; }
			table.summary td.note { font-size: smaller; font-weight: lighter; color: #6c757d; }
			table.breakdown { border-collapse: collapse; font-size: smaller; }
			table.breakdown td { border: 0.2mm solid #dee2e6; padding: 0.5mm 1mm; }
			table.breakdown td.key { font-weight: bolder; }

			ul.highlights { font-weight: 600; }
			ul.highlights li.minor { font-weight: lighter; }
			ul.highlights li span.tag { font-size: smaller; font-weight: lighter; color: #0d6efd; }
		</style>

		<h1>Quarterly report <small>third quarter</small></h1>
		<p class="caption">h1 at weight 700, its small set smaller and lighter. Standard: the title is bold and "third quarter" regular at 13.3pt. Legacy: both are regular at 16pt, the number replacing the heading's bold without being read, and so are the h2 headings below, at weight 600.</p>

		<p class="lead">Revenue grew <strong>twelve percent</strong> over the quarter, and <em>margins held <b>steady</b></em>.</p>
		<p class="caption">The lead paragraph is larger and at weight 300; strong and em inside it are bolder, and b in em bolder again. Standard: the paragraph is 10.8pt, "twelve percent" and "margins held" regular at 400, and "steady" bold at 700. Legacy: the paragraph is 9pt and all of it regular.</p>

		<h2>Summary</h2>
		<p class="caption">The table is at weight 500, its header cells at 700. The total cell is bolder, the notes smaller and lighter. A table nested in a cell is smaller, and its key cells bolder. Standard: the header cells and the total are bold and other cells regular; the notes and the breakdown are 7.5pt, the breakdown's keys bold. Legacy: every cell is regular at 9pt.</p>
		<table class="summary">
			<tr><th>Line</th><th>Amount</th><th>Breakdown</th></tr>
			<tr>
				<td>Services</td>
				<td>84,000</td>
				<td>
					<table class="breakdown">
						<tr><td class="key">Consulting</td><td>52,000</td></tr>
						<tr><td class="key">Support</td><td>32,000</td></tr>
					</table>
				</td>
			</tr>
			<tr><td>Licences</td><td>41,500</td><td class="note">Renewals due in October</td></tr>
			<tr><td>Total</td><td class="total">125,500</td><td class="note">Before tax</td></tr>
		</table>

		<h2>Highlights</h2>
		<p class="caption">The list is at weight 600, a minor item lighter, and the tags smaller and lighter. Standard: the items are bold, the minor item regular, and each tag regular at 7.5pt. Legacy: every item and tag is regular at 9pt.</p>
		<ul class="highlights">
			<li>Two new enterprise customers <span class="tag">sales</span></li>
			<li>Support response time halved <span class="tag">operations</span></li>
			<li class="minor">Office move completed <span class="tag">facilities</span></li>
		</ul>

		<h3>Outlook</h3>
		<p class="caption">h3 set lighter and larger. Standard: "Outlook" is regular at 10.8pt, lighter stepping down from the body's normal weight rather than from the heading's bold. Legacy: "Outlook" is regular at 9pt.</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
