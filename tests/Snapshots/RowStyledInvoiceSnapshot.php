<?php

namespace Snapshots;

/**
 * An invoice whose stylesheet styles the text of its tables through their rows and row groups: a dark thead in white
 * capitals, striped rows by class, an overdue row in red, a tfoot of bold totals, a row of discounts with a nested
 * breakdown, and a table of payment terms whose rows sit straight in the table. Written under each value of cssMode,
 * with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class RowStyledInvoiceSnapshot extends Snapshot
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
		return 'row-styled-invoice-' . $this->cascade();
	}

	/**
	 * The invoice, each part under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-family: sans-serif; font-size: 9pt; color: #2d3436; }
			h1 { font-size: 16pt; margin: 0 0 1mm 0; color: #0c4a6e; }
			h2 { font-size: 11pt; margin: 5mm 0 1mm 0; color: #0c4a6e; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }

			table.lines { border-collapse: collapse; width: 100%; }
			table.lines td, table.lines th { border-bottom: 0.2mm solid #cbd5e1; padding: 1.2mm 2mm; }
			table.lines thead { color: #ffffff; text-transform: uppercase; letter-spacing: 0.3mm; font-size: 8pt; }
			table.lines thead tr { background-color: #0c4a6e; }
			table.lines thead th.amount { text-align: right; }
			table.lines tr.even { background-color: #f1f5f9; color: #334155; }
			table.lines tr.overdue { color: #b91c1c; font-style: italic; }
			table.lines tr.discount { color: #15803d; font-size: 90%; }
			table.lines td.amount { text-align: right; font-family: monospace; }
			table.lines tfoot { font-weight: bold; text-align: right; color: #0c4a6e; }
			table.lines tfoot td.label { text-align: left; }

			table.breakdown td { border: 0; padding: 0.3mm 1mm; }

			table.terms tbody { color: #475569; font-size: 8pt; }
			table.terms th { width: 35mm; }
		</style>

		<h1>Invoice INV-2026-0931</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>

		<h2>Items</h2>
		<p class="caption">The thead sets white 8pt capitals, spaced 0.3mm, and its row is dark blue. Standard: the headers are white capitals. Legacy: they are dark grey, 9pt and as written, on the dark blue. In both, the th with class amount is right-aligned by its own rule and the others are centred.</p>
		<p class="caption">Rows with class even are slate on grey, the overdue row is red italic, and the discount row is green at 90%, with a nested breakdown in its cell. Standard: each row's text takes its colour, style and size, and so does the nested table. Legacy: only the grey stripes show; the text is the body's.</p>
		<table class="lines">
			<thead>
				<tr><th>Description</th><th>Qty</th><th class="amount">Amount</th></tr>
			</thead>
			<tbody>
				<tr><td>Warehouse rental, September</td><td>1</td><td class="amount">4,200.00</td></tr>
				<tr class="even"><td>Pallet handling</td><td>312</td><td class="amount">1,560.00</td></tr>
				<tr class="overdue"><td>Pallet handling, August (overdue)</td><td>280</td><td class="amount">1,400.00</td></tr>
				<tr class="even"><td>Cold storage surcharge</td><td>30</td><td class="amount">450.00</td></tr>
				<tr class="discount">
					<td>Volume discount
						<table class="breakdown">
							<tr><td>Handling</td><td>-3%</td></tr>
							<tr><td>Storage</td><td>-2%</td></tr>
						</table>
					</td>
					<td>1</td>
					<td class="amount">-380.00</td>
				</tr>
			</tbody>
			<tfoot>
				<tr><td class="label">Subtotal</td><td></td><td class="amount">7,230.00</td></tr>
				<tr><td class="label">Total due</td><td></td><td class="amount">7,953.00</td></tr>
			</tfoot>
		</table>
		<p class="caption">The tfoot sets bold dark blue text aligned right. Standard: the totals are bold and blue. Legacy: they are the body's colour and not bold. In both, the amounts are aligned right and the labels left by their own rule.</p>

		<h2>Payment terms</h2>
		<p class="caption">The rows sit straight in the table, and a rule for tbody sets slate 8pt text. Standard: the rows are in a tbody all the same, so the terms are slate and 8pt, and the bold th keep their centring. Legacy: they are the body's colour and size.</p>
		<table class="terms">
			<tr><th>Due</th><td>30 days from the invoice date</td></tr>
			<tr><th>Late fee</th><td>1.5% a month on the overdue balance</td></tr>
			<tr><th>Pay to</th><td>Account 12-3456-7890123-00</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
