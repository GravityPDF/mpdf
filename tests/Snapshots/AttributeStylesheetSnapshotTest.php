<?php

namespace Snapshots;

/**
 * Stylesheets in the style of framework, CMS and invoice templates that select by attribute and by language, mixed
 * with rules the legacy parser reads and with combinators and structural pseudo-classes, chained, and competing
 * where specificity and source order decide.
 *
 * @group snapshot
 */
class AttributeStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'attribute-selectors-stylesheet';
	}

	/**
	 * A grid and link styles as a framework writes them, a multilingual article, an invoice table styled by state,
	 * and competing rules, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2.section { margin: 3mm 0 1mm 0; font-size: 10pt; color: #000000; }
			p.caption { margin: 0 0 1.5mm 0; font-size: 8pt; color: #606060; }

			/* Framework utilities */
			.row { border: 0.2mm solid #adb5bd; padding: 1mm; margin-bottom: 1mm; }
			[class^="col-"], [class*=" col-"] { background-color: #e7f1ff; border-left: 1mm solid #0d6efd; padding: 0.5mm 2mm; margin: 0.5mm 0; }
			[class*="col-"][class*="-6"] { background-color: #d1e7dd; border-left-color: #198754; }
			a[href^="mailto:"] { color: #6f42c1; }
			a[href^="tel:"] { color: #fd7e14; }
			a[target="_blank"][rel~="noopener"] { font-style: italic; }
			a[href$=".pdf" i]:first-child { font-weight: bold; }

			/* A multilingual article */
			.entry-content p { margin: 1mm 0; }
			.entry-content :lang(fr) { color: #1f3a93; }
			.entry-content p:lang(de) { color: #b02a37; }
			.entry-content [lang|="en"] > b { text-decoration: underline; }
			.entry-content blockquote[cite] > p:first-child { font-style: italic; }
			.entry-content [lang] em { font-weight: bold; }

			/* An invoice template */
			table.items { border-collapse: collapse; width: 100%; }
			table.items td, table.items th { border-bottom: 0.2mm solid #adb5bd; padding: 1mm 2mm; }
			table[data-theme="dark"] > thead > tr > th { background-color: #343a40; color: #ffffff; text-align: left; }
			table.items tr[data-state="void"] > td { color: #adb5bd; text-decoration: line-through; }
			table.items tr[data-state="overdue"] > td:first-child { border-left: 1mm solid #dc3545; }
			table.items td[data-type="amount"] { text-align: right; }
			table.items tr:not-supported, table.items tr[data-state="paid"] td[data-type="amount"] { color: #198754; font-weight: bold; }

			/* Competing rules */
			.pricing p { margin: 0.5mm 0; }
			.pricing > p[data-plan] { color: #fd7e14; }
			.pricing > p.featured { color: #6f42c1; }
			.pricing > p[data-plan="pro"][data-billing] { color: #0d6efd; }
			#plans > [data-plan="team"] { color: #198754; }
			.pricing > p[data-plan="team"].featured { color: #dc3545; }
		</style>

		<h1>Attribute selectors in real stylesheets</h1>

		<h2 class="section">Framework utilities</h2>
		<p class="caption">Every column, whether its col- class comes first or later, has a blue bar and a light blue background, except the half-width ones, which are green. The email link is purple, the phone link orange, the link opening a new window italic, and the PDF link, first in its paragraph, bold.</p>
		<div class="row">
			<div class="col-12">col-12: first class</div>
			<div class="card col-md-4">card col-md-4: a later class</div>
			<div class="col-6">col-6: half width</div>
			<div class="colour">colour: no column class</div>
		</div>
		<p><a href="https://example.com/terms.PDF">Terms (PDF)</a> · <a href="mailto:office@example.com">office@example.com</a> · <a href="tel:+61200000000">+61 2 0000 0000</a> · <a href="https://example.com/" target="_blank" rel="noopener noreferrer">New window</a></p>

		<h2 class="section">A multilingual article</h2>
		<p class="caption">French text is blue, the German paragraph red. Bold text directly inside English is underlined. The first paragraph of the quote that names its source is italic. Emphasis inside anything marked with a language is bold.</p>
		<div class="entry-content">
			<p lang="en">An English paragraph with <b>underlined bold</b> and <em>bold emphasis</em>.</p>
			<p lang="fr">Un paragraphe français, avec <span>un span qui en hérite</span>.</p>
			<p lang="de">Ein deutscher Absatz.</p>
			<p>A paragraph with no language of its own, <span lang="fr">sauf ici</span>, and <em>plain emphasis</em>.</p>
			<blockquote cite="https://example.com/source"><p>The first paragraph of a sourced quote.</p><p>Its second paragraph.</p></blockquote>
		</div>

		<h2 class="section">An invoice</h2>
		<p class="caption">The dark theme gives the header row white text on dark grey. The void row is grey and struck through. The overdue row has a red bar at its left. Amounts are right-aligned, and the paid amount is green and bold.</p>
		<table class="items" data-theme="dark">
			<thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th></tr></thead>
			<tbody>
				<tr data-state="paid"><td>INV-001</td><td>Acme</td><td data-type="amount">480.00</td></tr>
				<tr data-state="void"><td>INV-002</td><td>Initech</td><td data-type="amount">190.00</td></tr>
				<tr data-state="overdue"><td>INV-003</td><td>Globex</td><td data-type="amount">102.00</td></tr>
			</tbody>
		</table>

		<h2 class="section">Competing rules</h2>
		<p class="caption">Attribute selectors weigh as much as classes. Each paragraph says the colour it should be.</p>
		<div class="pricing" id="plans">
			<p data-plan="basic">Orange: .pricing &gt; p[data-plan].</p>
			<p data-plan="plus" class="featured">Purple: .pricing &gt; p.featured weighs the same as .pricing &gt; p[data-plan] and comes later.</p>
			<p data-plan="pro" data-billing="yearly">Blue: two attributes outweigh one.</p>
			<p data-plan="team" class="featured">Green: #plans &gt; [data-plan="team"] outweighs .pricing &gt; p[data-plan="team"].featured.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
