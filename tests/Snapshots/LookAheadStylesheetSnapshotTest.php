<?php

namespace Snapshots;

/**
 * A page styled as a CSS framework styles one with :last-child, :first-child, :only-child and :empty: card lists that
 * leave the border off their last item, a list with a single item, empty paragraphs hidden, tables whose last row has
 * no rule under it, and rules competing with them on specificity and source order, in the standard CSS mode.
 *
 * @group snapshot
 */
class LookAheadStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'look-ahead-stylesheet';
	}

	/**
	 * The components, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$rows = '';
		for ($i = 1; $i <= 40; $i++) {
			$rows .= '<tr><td>Line ' . $i . '</td><td class="num">' . number_format($i * 12.5, 2) . '</td></tr>';
		}

		ob_start();
		?>
		<style>
			/* Base */
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 4mm 0 1mm 0; color: #000000; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }

			/* Card list */
			.card { border: 0.3mm solid #ced4da; margin-bottom: 2mm; }
			.card-list { margin: 0; padding: 0; list-style-type: none; }
			.card-list .item { padding: 1.5mm 3mm; border-bottom: 0.6mm solid #0d6efd; }
			.card-list .item:last-child { border-bottom: 0; }
			.card-list .item:first-child { font-weight: bold; }

			/* Lists */
			.menu li { color: #0d6efd; }
			.menu li:only-child { color: #198754; list-style-type: none; }
			.steps li:first-child { color: #198754; }
			.steps li:last-child { color: #dc3545; }
			.tags li:last-child { color: #6c757d; }
			.tags li.highlight { color: #fd7e14; }

			/* Content from an editor */
			.content p { background-color: #e7f1ff; margin: 1mm 0; padding: 1mm 2mm; }
			.content p:empty { display: none; }

			/* Alert */
			.alert { border: 0.3mm solid #f5c2c7; background-color: #f8d7da; padding: 2mm 3mm; }
			.alert p { margin: 0 0 2mm 0; }
			.alert p:last-child { margin-bottom: 0; color: #842029; }
			#notice p { color: #0d6efd; }

			/* Table */
			table.table { border-collapse: collapse; width: 100%; }
			.table td, .table th { padding: 1mm 2mm; border-bottom: 0.3mm solid #adb5bd; text-align: left; }
			.table td.num, .table th.num { text-align: right; }
			.table tr:last-child td { border-bottom: 0; }
			.table thead tr:last-child th { border-bottom: 0.6mm solid #212529; }
			.table tfoot td { font-weight: bold; }
			.table td:only-child { color: #6c757d; font-style: italic; }
		</style>

		<h1>A framework's stylesheet, with :last-child and :empty</h1>

		<h2>Card list</h2>
		<p class="caption">.card-list .item:last-child against .card-list .item, as heavy and written later: a blue rule under every item but the last, which sits on the card's grey border alone. The first item is bold.</p>
		<div class="card">
			<ul class="card-list">
				<li class="item">First item</li>
				<li class="item">Second item</li>
				<li class="item">Third item</li>
			</ul>
		</div>
		<div class="card">
			<ul class="card-list">
				<li class="item">A single item, bold and with no blue rule</li>
			</ul>
		</div>

		<h2>Menus and steps</h2>
		<p class="caption">li:only-child. The menu with a single entry is green with no bullet; the entries of the menu of two are blue with bullets.</p>
		<ul class="menu"><li>Only entry</li></ul>
		<ul class="menu"><li>First entry</li><li>Second entry</li></ul>
		<p class="caption">:first-child then :last-child. The first step is green and the last red; a list with one step is red, the later rule winning.</p>
		<ol class="steps"><li>Start</li><li>Middle</li><li>Finish</li></ol>
		<ol class="steps"><li>A single step</li></ol>
		<p class="caption">li.highlight written after li:last-child, as heavy. The highlighted last tag is orange, the later rule winning.</p>
		<ul class="tags"><li>First tag</li><li class="highlight">Highlighted last tag</li></ul>

		<h2>Content from an editor</h2>
		<p class="caption">p:empty { display: none }. No blue strip between the first two paragraphs, where the editor left an empty paragraph. The paragraph holding a space, between the second and third, is not empty, as in a browser, and shows as a thin blue strip.</p>
		<div class="content">
			<p>First paragraph.</p>
			<p></p>
			<p>Second paragraph.</p>
			<p> </p>
			<p>Third paragraph.</p>
		</div>

		<h2>Alert</h2>
		<p class="caption">#notice p against .alert p:last-child. Both lines are blue, the id outweighing the pseudo-class, and the last line has no space under it.</p>
		<div id="notice" class="alert"><p>The first line of the notice.</p><p>The last line of the notice.</p></div>

		<h2>Table</h2>
		<p class="caption">.table tr:last-child td against .table td. A grey rule under each body row but the last, a black rule under the last header row, on this page and the next, and no rule under the total. The note spanning the row is grey and italic.</p>
		<table class="table">
			<thead><tr><th colspan="2">Statement</th></tr><tr><th>Item</th><th class="num">Amount</th></tr></thead>
			<tfoot><tr><td>Total</td><td class="num">10,250.00</td></tr></tfoot>
			<tbody><?php echo $rows; ?><tr><td colspan="2">A note spanning the row</td></tr></tbody>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
