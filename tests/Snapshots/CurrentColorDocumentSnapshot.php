<?php

namespace Snapshots;

/**
 * An invoice styled the way a CSS framework styles one: components whose borders and shadows name no colour or name
 * currentColor, a transparent button border, a hidden divider and text hidden for screen readers, in nested tables,
 * lists and inline markup. Written under each value of cssMode, with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class CurrentColorDocumentSnapshot extends Snapshot
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
		return 'current-color-document-' . $this->mode();
	}

	/**
	 * The invoice, each part under a caption saying what the standard and the legacy mode draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 1mm 0; }
			h2 { font-size: 11pt; margin: 4mm 0 1mm 0; color: #0d6efd; text-shadow: 0.3mm 0.3mm; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }
			.visually-hidden { color: transparent; }

			.masthead { color: #0d6efd; border-bottom: 0.8mm solid; padding-bottom: 1mm; }
			.btn { border: 0.4mm solid transparent; padding: 0.5mm 2mm; }
			.btn-primary { color: #ffffff; background-color: #0d6efd; border-color: #0a58ca; }
			.btn-link { color: #0d6efd; }

			.alert { border: 0.3mm solid; padding: 2mm 3mm; margin: 2mm 0; }
			.alert-danger { color: #842029; background-color: #f8d7da; }
			.badge { color: #6f42c1; box-shadow: 0.6mm 0.6mm; background-color: #f3eefc; padding: 1mm 2mm; }

			table.invoice { border-collapse: collapse; width: 100%; color: #495057; }
			.invoice th { border-bottom: 0.6mm solid currentColor; text-align: left; padding: 1mm 2mm; }
			.invoice td { border-bottom: 0.2mm solid; padding: 1mm 2mm; }
			.invoice td.amount, .invoice th.amount { text-align: right; }
			.invoice tr.total td { color: #198754; border-top: 0.8mm double; font-weight: bold; }
			table.breakdown { color: #6c757d; font-size: 8pt; }
			.breakdown td { border: 0.2mm dotted currentColor; padding: 0.5mm 1mm; }

			.divider { border-top: 1mm hidden #ff0000; margin: 2mm 0; }

			ol.steps { margin: 1mm 0; }
			.steps li { border-left: 1mm solid; padding-left: 2mm; margin-bottom: 1mm; }
			.steps li.done { color: #198754; }
			.steps li.todo { color: #adb5bd; }
		</style>

		<h1>A framework-styled invoice</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->mode(); ?>.</p>

		<p class="caption">The masthead's border-bottom names no colour. Standard: it is blue, the masthead's colour. Legacy: it is black. The words "Skip to the invoice" are transparent: in both they leave a gap before the studio's name and are not seen.</p>
		<div class="masthead"><span class="visually-hidden">Skip to the invoice</span> Example Studio, invoice 2024-117</div>

		<p class="caption">The link button's border is transparent, and the primary button's is dark blue: in both, the link button has no frame and takes the same room.</p>
		<p><span class="btn btn-primary">Pay now</span> <span class="btn btn-link">Download a copy</span></p>

		<h2>Items</h2>
		<p class="caption">The heading's text-shadow names no colour. Standard: it is blue like the heading. Legacy: it is grey.</p>
		<p class="caption">The header's line is in currentColor, grey in both. The rows' lines name no colour. Standard: they are grey, the table's colour, and the total's lines, double above, are green. Legacy: they are black. The breakdown's dotted cells are in currentColor, a lighter grey in both.</p>
		<table class="invoice">
			<tr><th>Item</th><th class="amount">Amount</th></tr>
			<tr><td>Design, with <b>two</b> rounds of <i>review</i></td><td class="amount">1,200.00</td></tr>
			<tr><td>Hosting
				<table class="breakdown"><tr><td>Server</td><td>240.00</td></tr><tr><td>Backups</td><td>60.00</td></tr></table>
			</td><td class="amount">300.00</td></tr>
			<tr class="total"><td>Total</td><td class="amount">1,500.00</td></tr>
		</table>

		<p class="caption">The divider's border is hidden: no red line and no hairline in either.</p>
		<div class="divider">Below a hidden border</div>

		<p class="caption">The alert's border names no colour. Standard: it is dark red, the alert's colour. Legacy: it is black.</p>
		<div class="alert alert-danger">Payment is overdue by <strong>14 days</strong>.</div>

		<p class="caption">The badge's box-shadow names no colour. Standard: it is purple. Legacy: it is grey.</p>
		<div class="badge">Priority customer</div>

		<h2>Next steps</h2>
		<p class="caption">Each step's left border names no colour. Standard: green for the step done, light grey for those to do. Legacy: all black.</p>
		<ol class="steps">
			<li class="done">Invoice sent</li>
			<li class="todo">Payment received</li>
			<li class="todo">Receipt issued</li>
		</ol>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->mode()]);

		$this->mpdf->WriteHTML($html);
	}

}
