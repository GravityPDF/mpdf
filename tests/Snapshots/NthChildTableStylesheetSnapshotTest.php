<?php

namespace Snapshots;

/**
 * Table stylesheets in the style of CSS frameworks and invoice templates, mixing tr, td and th:nth-child rules the
 * legacy parser reads with rules the matcher reads, competing where specificity and source order decide, in tables
 * with row headers, spanned cells and a header repeated on the next page.
 *
 * @group snapshot
 */
class NthChildTableStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nth-child-table-stylesheet';
	}

	/**
	 * A framework's striped table, an invoice, a set of competing rules and a long striped table, each under a caption
	 * saying what should be seen
	 */
	public function generatePdf()
	{
		$rows = '';
		for ($row = 1; $row <= 44; $row++) {
			$rows .= '<tr><th>' . $row . '</th><td>Entry ' . $row . '</td><td>' . ($row * 7) . '.00</td></tr>';
		}

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2.section { margin: 3mm 0 1mm 0; font-size: 10pt; color: #000000; }
			p.caption { margin: 0 0 1.5mm 0; font-size: 8pt; color: #606060; }

			/* A framework's tables */
			.table { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
			.table th, .table td { padding: 0.8mm 2mm; border-top: 0.2mm solid #dee2e6; text-align: left; }
			.table thead th { border-bottom: 0.4mm solid #212529; }
			.table-bordered th, .table-bordered td { border: 0.2mm solid #dee2e6; }
			.table-striped tbody tr:nth-child(odd) td, .table-striped tbody tr:nth-child(odd) th { background-color: #e9ecef; }
			.table-dark thead th:nth-child(even) { background-color: #495057; color: #ffffff; }
			.table-dark thead th:nth-child(odd) { background-color: #212529; color: #ffffff; }
			.table > tbody > tr:nth-child(3n) > td { color: #0d6efd; }
			.table > tbody > tr > td.table-warning { background-color: #fff3cd; }
			.table-numeric td:nth-child(n+3) { text-align: right; }

			/* An invoice template */
			table.invoice { width: 100%; border-collapse: collapse; }
			table.invoice th, table.invoice td { padding: 0.8mm 2mm; border-bottom: 0.2mm solid #adb5bd; }
			table.invoice thead th { background-color: #343a40; color: #ffffff; text-align: left; }
			table.invoice thead th:nth-child(n+2) { text-align: right; }
			table.invoice tbody td:nth-child(n+2) { text-align: right; }
			table.invoice tbody tr:nth-child(even) td { background-color: #f1f3f5; }
			table.invoice tbody td:first-child { font-weight: bold; }
			table.invoice tfoot td { text-align: right; }
			table.invoice tfoot td:nth-child(2) { font-weight: bold; color: #198754; }
			table.invoice tfoot tr:first-child td { border-top: 0.5mm solid #343a40; }

			/* Competing rules */
			table.compete td { padding: 0.8mm 2mm; }
			table.compete td:nth-child(2) { color: #198754; }
			table.compete td.muted { color: #6c757d; }
			table.compete tr:nth-child(2) td { background-color: #d1e7dd; }
			#compete > tbody > tr:nth-child(2) > td:first-child { background-color: #f8d7da; }
		</style>

		<h1>tr, td and th:nth-child in real stylesheets</h1>

		<h2 class="section">A framework's striped table with row headers</h2>
		<p class="caption">The header cells are dark, the even ones a lighter grey. The first, third and fifth body rows are shaded grey, row headers included. The text of the third and sixth rows' cells is blue. The warning cell in row 5 is yellow: its rule weighs as much as the striping and comes after it. td:nth-child(n+3) right-aligns the amounts and the statuses, as the row header counts as the first cell.</p>
		<table class="table table-striped table-dark table-numeric">
			<thead>
				<tr><th>#</th><th>Name</th><th>Amount</th><th>Status</th></tr>
			</thead>
			<tbody>
				<tr><th>1</th><td>Alpha</td><td>10.00</td><td>Open</td></tr>
				<tr><th>2</th><td>Bravo</td><td>20.00</td><td>Open</td></tr>
				<tr><th>3</th><td>Charlie, blue</td><td>30.00</td><td>Closed</td></tr>
				<tr><th>4</th><td>Delta</td><td>40.00</td><td>Open</td></tr>
				<tr><th>5</th><td>Echo</td><td>50.00</td><td class="table-warning">Warning, yellow</td></tr>
				<tr><th>6</th><td>Foxtrot, blue</td><td>60.00</td><td>Closed</td></tr>
			</tbody>
		</table>

		<h2 class="section">An invoice</h2>
		<p class="caption">The header is dark with the columns after the first right-aligned. The second and fourth item rows are shaded, and the item names bold. In the totals, each amount is the second cell of its row, after a label spanning three columns, and is bold and green. A heavy rule sits above the first total.</p>
		<table class="invoice">
			<thead>
				<tr><th>Item</th><th>Quantity</th><th>Price</th><th>Amount</th></tr>
			</thead>
			<tbody>
				<tr><td>Consulting</td><td>4</td><td>120.00</td><td>480.00</td></tr>
				<tr><td>Design</td><td>2</td><td>95.00</td><td>190.00</td></tr>
				<tr><td>Hosting</td><td>12</td><td>8.50</td><td>102.00</td></tr>
				<tr><td>Support</td><td>1</td><td>60.00</td><td>60.00</td></tr>
			</tbody>
			<tfoot>
				<tr><td colspan="3">Subtotal</td><td>832.00</td></tr>
				<tr><td colspan="3">Tax</td><td>83.20</td></tr>
				<tr><td colspan="3">Total</td><td>915.20</td></tr>
			</tfoot>
		</table>

		<h2 class="section">Competing rules</h2>
		<p class="caption">td.muted weighs as much as td:nth-child(2) and comes after it, so every muted cell is grey, and only the second cell that is not muted is green. The second row is shaded green, but its first cell is red: the rule naming the table's id outweighs the row's.</p>
		<table class="table-bordered compete" id="compete">
			<tbody>
				<tr><td class="muted">Grey</td><td class="muted">Grey</td><td>Black</td></tr>
				<tr><td>Red background</td><td class="muted">Grey</td><td class="muted">Grey</td></tr>
				<tr><td>Black</td><td>Green</td><td class="muted">Grey</td></tr>
			</tbody>
		</table>

		<h2 class="section">A long striped table with its header repeated</h2>
		<p class="caption">The rows run onto the next page, where the header is repeated. The odd rows are shaded and every third row's cells are blue on both pages, counted from the first body row whatever page it falls on.</p>
		<table class="table table-striped table-dark table-numeric">
			<thead>
				<tr><th>#</th><th>Entry</th><th>Amount</th></tr>
			</thead>
			<tbody>
				<?php echo $rows; ?>
			</tbody>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
