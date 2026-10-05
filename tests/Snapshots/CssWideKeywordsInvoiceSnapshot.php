<?php

namespace Snapshots;

/**
 * An invoice whose theme uses the CSS-wide keywords the way reset stylesheets and themes do: links that take the
 * colour of the text around them, a heading reverted to its built-in size after a reset, a total row whose cells take
 * its background, and nested lists and tables that take their parents' styles. Written under each value of cssMode,
 * with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class CssWideKeywordsInvoiceSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function cssMode();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'css-wide-keywords-invoice-' . $this->cssMode();
	}

	/**
	 * The invoice, each part under a caption saying what the standard and the legacy mode draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			/* Reset */
			h1, h2 { font-size: 9pt; margin: 0; }
			a { color: inherit; text-decoration: inherit; }
			ul { margin: 0; }

			/* Theme */
			body { font-family: sans-serif; font-size: 9pt; color: #1f2933; }
			p.caption { font-size: 7.5pt; color: #6b7280; margin: 3mm 0 1mm 0; }
			.invoice { border: 0.5mm solid #1d4ed8; padding: 3mm; background-color: #eff6ff; color: #1e3a8a; }
			.invoice h1 { font-size: revert; margin: revert; }
			.invoice .notice { color: #b91c1c; font-weight: bold; border: 0.3mm solid #b91c1c; padding: 1mm; background-color: #fee2e2; }
			.invoice .from strong { color: initial; }
			.invoice .ref { border: 0.3mm dashed #1d4ed8; }
			.invoice .notice .ref { border: inherit; }

			table.lines { width: 100%; color: #047857; background-color: #ffffff; }
			table.lines th { background-color: #1d4ed8; color: #ffffff; padding: 1.5mm; }
			table.lines td { color: #6b7280; background-color: #ffffff; padding: 1.5mm; border-bottom: 0.2mm solid #93c5fd; }
			table.lines td.amount { text-align: right; }
			table.lines td.note { color: unset; }
			table.lines tr.total { background-color: #dbeafe; }
			table.lines tr.total td { background-color: inherit; font-weight: bold; }
			table.terms td { padding: revert; border: 0.2mm solid #93c5fd; background-color: #ffffff; }

			ul.steps { list-style: square; color: #1e3a8a; }
			ul.steps li { color: #6b7280; }
			ul.steps li.current { color: inherit; }
			ul.steps ul { list-style: inherit; }
		</style>

		<p class="caption">Written with cssMode set to <?php echo $this->cssMode(); ?>.</p>

		<p class="caption">The heading: after a reset makes every h1 small, .invoice h1 reverts its size and margin. Standard: the title is large, with its built-in space below it. Legacy: it is small, and set right against the text below it.</p>
		<p class="caption">The notice: its link takes the notice's red, bold text and drops the underline, and the reference inside it takes the notice's border. In both modes "pay online" is red, bold and not underlined: the legacy mode ignores a keyword on an inline element, which keeps the state of the text around it. Standard: "INV-0042" has a solid red border like the notice's. Legacy: it has no border.</p>
		<p class="caption">The sender: strong is color: initial. Standard: "Blue Liquid Designs" is black. Legacy: it is dark blue like the text around it.</p>
		<div class="invoice">
			<h1>Invoice INV-0042</h1>
			<p class="from">From <strong>Blue Liquid Designs</strong>, 1 Example Street.</p>
			<p class="notice">Payment is due in 14 days: <a href="https://example.com/pay">pay online</a> quoting <span class="ref">INV-0042</span>.</p>
		</div>

		<p class="caption">The lines: the notes cell is color: unset, the total row's cells take the row's background, and the terms table nested in a cell reverts its cells' padding. Standard: the note is green, the table's colour; the total row is pale blue; the terms cells have thin padding. Legacy: the note is black; the total row's cells have no background at all, so the table's white shows; the terms cells have no padding.</p>
		<table class="lines">
			<tr><th>Item</th><th>Note</th><th>Amount</th></tr>
			<tr><td>Design</td><td class="note">Two rounds of changes</td><td class="amount">1,200.00</td></tr>
			<tr><td>Hosting</td><td class="note">
				<table class="terms"><tr><td>12 months</td><td>renews in March</td></tr></table>
			</td><td class="amount">300.00</td></tr>
			<tr class="total"><td>Total</td><td></td><td class="amount">1,500.00</td></tr>
		</table>

		<p class="caption">The steps: a nested list takes its parent list's square markers, and the current step takes the list's dark blue over the grey of the items. Standard: every marker is a square, and "Approve the design" is dark blue. Legacy: the nested items have the default markers of a nested list, and "Approve the design" is black.</p>
		<ul class="steps">
			<li>Send the brief</li>
			<li class="current">Approve the design
				<ul><li>Colours</li><li>Type</li></ul>
			</li>
			<li>Launch</li>
		</ul>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
