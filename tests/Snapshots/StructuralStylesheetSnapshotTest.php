<?php

namespace Snapshots;

/**
 * Stylesheets in the style of framework, CMS and invoice templates, mixing rules the legacy parser reads with child
 * and sibling combinators and structural pseudo-classes, chained, and competing where specificity and source order
 * decide.
 *
 * @group snapshot
 */
class StructuralStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'structural-selectors-stylesheet';
	}

	/**
	 * A card component, a CMS article, an invoice table and a set of competing rules, each under a caption saying
	 * what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2.section { margin: 3mm 0 1mm 0; font-size: 10pt; color: #000000; }
			p.caption { margin: 0 0 1.5mm 0; font-size: 8pt; color: #606060; }

			/* A card and a list group, as a CSS framework writes them */
			.card { border: 0.3mm solid #adb5bd; padding: 2mm 3mm; }
			.card-title { font-size: 11pt; font-weight: bold; margin: 0 0 1mm 0; }
			.card p { margin: 0.5mm 0; }
			.card > .card-body > p:first-child { color: #0d6efd; font-weight: bold; }
			.card > .card-body > p ~ p { color: #6c757d; }
			.card > .card-body > .card-title + p { font-style: italic; }
			ul.list-group { margin: 2mm 0 0 0; padding: 0; list-style-type: none; }
			ul.list-group > li { padding: 0.6mm 2mm; }
			ul.list-group > li + li { border-top: 0.3mm solid #adb5bd; }
			ul.list-group > li:nth-child(odd) { background-color: #e9ecef; }
			ul.list-group > li:first-child { font-weight: bold; }

			/* A theme's article styles */
			.entry-content p { margin: 1mm 0; }
			.entry-content > p:first-of-type { font-size: 12pt; }
			.entry-content > h3 { font-size: 10pt; margin: 2mm 0 0.5mm 0; }
			.entry-content > h3 + p { font-style: italic; }
			.entry-content > h3 ~ ul > li:nth-child(2n) { color: #b02a37; }
			.entry-content > ul li > ul > li:first-child { font-weight: bold; }
			.entry-content blockquote > p:nth-of-type(2) { color: #6f42c1; }

			/* An invoice template */
			table.items { border-collapse: collapse; width: 100%; }
			table.items th, table.items td { border-bottom: 0.2mm solid #adb5bd; padding: 0.6mm 2mm; }
			table.items > thead > tr > th { background-color: #343a40; color: #ffffff; text-align: left; }
			table.items > thead > tr > th + th { text-align: right; }
			table.items > tbody > tr:nth-child(even) > td { background-color: #e9ecef; }
			table.items > tbody > tr > td:first-child { font-weight: bold; }
			table.items > tbody > tr > td + td { text-align: right; }
			table.items > tfoot > tr > td { font-weight: bold; text-align: right; }
			table.items > tfoot > tr > td:first-child { text-align: left; }

			/* Competing rules */
			.pricing p { margin: 0.5mm 0; color: #198754; }
			.pricing > p { color: #dc3545; }
			.pricing > p.note { color: #0d6efd; }
			div.pricing > p { color: #fd7e14; }
			#plans > p:nth-child(3) { color: #6f42c1; }
			.pricing > p:nth-child(3) { color: #dc3545; }
			#plans > .fine { color: #6c757d; }
			.pricing > .fine { color: #dc3545; }
		</style>

		<h1>Structural selectors in real stylesheets</h1>

		<h2 class="section">A card and a list group</h2>
		<p class="caption">The card body's first paragraph is blue and bold, the title's next sibling italic, and the paragraphs after the first grey. In the list, the first item is bold, the first, third and fifth items have a grey background, and a rule separates each item from the one before it.</p>
		<div class="card">
			<div class="card-body">
				<p>Blue and bold: the first child of the card body.</p>
				<p>Grey: a paragraph after the first.</p>
				<h5 class="card-title">Card title</h5>
				<p>Grey and italic: right after the title.</p>
			</div>
			<ul class="list-group">
				<li>First item</li>
				<li>Second item</li>
				<li>Third item</li>
				<li>Fourth item</li>
				<li>Fifth item</li>
			</ul>
		</div>

		<h2 class="section">A CMS article</h2>
		<p class="caption">The first paragraph is 12pt. The paragraph right after each subheading is italic. In the list after the subheading, the second and fourth items are red, and the first item of the nested list is bold. In the quote, only the second paragraph is purple.</p>
		<div class="entry-content">
			<p>The opening paragraph, at 12pt.</p>
			<p>A second paragraph, at the normal size.</p>
			<h3>A subheading</h3>
			<p>Italic: right after the subheading.</p>
			<ul>
				<li>One</li>
				<li>Two, red</li>
				<li>Three
					<ul><li>Nested one, bold</li><li>Nested two</li></ul>
				</li>
				<li>Four, red</li>
			</ul>
			<blockquote>
				<p>The first paragraph of the quote.</p>
				<p>The second paragraph of the quote, purple.</p>
				<p>The third paragraph of the quote.</p>
			</blockquote>
		</div>

		<h2 class="section">An invoice</h2>
		<p class="caption">The header row is dark with white text, its first column left-aligned and the rest right-aligned. The body's second and fourth rows are shaded, its first column is bold, and its amounts are right-aligned. The total row is bold with its label on the left.</p>
		<table class="items">
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
				<tr><td colspan="3">Total</td><td>832.00</td></tr>
			</tfoot>
		</table>

		<h2 class="section">Competing rules</h2>
		<p class="caption">Rules the matcher reads come after the descendant rules the legacy parser reads, whatever their weight, and among themselves go by specificity, then by source order. Each paragraph says the colour it should be.</p>
		<div class="pricing" id="plans">
			<p>Orange: div.pricing &gt; p comes after .pricing &gt; p, with the same weight, and after .pricing p.</p>
			<p class="note">Blue: .pricing &gt; p.note is heavier than div.pricing &gt; p.</p>
			<p>Purple: #plans &gt; p:nth-child(3) outweighs .pricing &gt; p:nth-child(3), though it is written first.</p>
			<p class="fine">Grey: #plans &gt; .fine outweighs .pricing &gt; .fine.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
