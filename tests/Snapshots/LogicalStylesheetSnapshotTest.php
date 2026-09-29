<?php

namespace Snapshots;

/**
 * Stylesheets in the style of framework, CMS and invoice templates that use :not(), :is() and :where(), nested with
 * attribute selectors and structural pseudo-classes, in long combinator chains, through inline ancestors and table
 * cells, and competing where specificity and source order decide.
 *
 * @group snapshot
 */
class LogicalStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'logical-selectors-stylesheet';
	}

	/**
	 * Components, a CMS article, an invoice and competing rules, each under a caption saying what should be seen
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

			/* Components, as a framework writes them */
			.card { border: 0.3mm solid #adb5bd; padding: 1.5mm 3mm; margin-bottom: 1.5mm; }
			.card p { margin: 0.5mm 0; }
			.card:not(.card-plain) > .body > p:first-child { font-weight: bold; }
			.card > .body ul li:first-child + li a[href^="http"] { color: #d63384; font-weight: bold; }
			.card :is(ul, ol) { margin: 0.5mm 0; }
			.card :is(ul, ol) > li:not(.muted):nth-child(odd) { color: #0d6efd; }
			.btn { padding: 0.5mm 2mm; border: 0.3mm solid #6c757d; }
			.wp-block-button:not(.is-style-outline) > .wp-block-button__link { background-color: #0d6efd; color: #ffffff; }
			.wp-block-button.is-style-outline > .wp-block-button__link { border: 0.3mm solid #0d6efd; color: #0d6efd; }
			:is(.badge, .tag):not([data-tone="quiet"]) b { color: #ffffff; background-color: #6f42c1; }

			/* A theme's article styles */
			.entry-content p { margin: 1mm 0; }
			.entry-content :is(h3, h4) { font-size: 10pt; margin: 2mm 0 0.5mm 0; }
			.entry-content :is(h3, h4) + p:not(.note) { font-style: italic; }
			.entry-content :where(p, li) > a { color: #198754; }
			.entry-content p > a.external { color: #fd7e14; }
			.entry-content :not(pre) > code { background-color: #e9ecef; color: #b02a37; }

			/* An invoice template */
			table.items { border-collapse: collapse; width: 100%; }
			table.items td, table.items th { border-bottom: 0.2mm solid #adb5bd; padding: 1mm 2mm; text-align: left; }
			table.items > thead th:is(.qty, .amount) { text-align: right; }
			table.items > tbody > tr:not([data-state="void"]):nth-child(even) > td { background-color: #e9ecef; }
			table.items > tbody > tr[data-state="void"] > td:not(:first-child) { color: #adb5bd; }
			table.items > tbody > tr > td:is(.qty, .amount) { text-align: right; }
			table.items > tbody > tr > td:not(:is(.qty, .amount, :first-child)) { color: #6c757d; }

			/* Competing rules */
			.pricing p { margin: 0.5mm 0; }
			:where(#plans) > p { color: #dc3545; }
			.pricing > p:where(.basic) { color: #dc3545; }
			.pricing > p { color: #fd7e14; }
			.pricing > p:is(.pro, #enterprise) { color: #0d6efd; }
			.pricing > p:not(.pro, .basic) { color: #198754; }
		</style>

		<h1>:not(), :is() and :where() in real stylesheets</h1>

		<h2 class="section">Components</h2>
		<p class="caption">In the first card, the opening paragraph is bold and the link in the second list item is pink and bold. In each list, the odd items are blue unless muted. The plain card's opening paragraph is not bold. The filled button is white on blue, the outline button blue with a border. The badge and the loud tag are white on purple; the quiet tag is not.</p>
		<div class="card">
			<div class="body">
				<p>Opening paragraph, bold.</p>
				<ul>
					<li>First item, blue</li>
					<li>Second item with <a href="https://example.com/">a pink link</a></li>
					<li class="muted">Third item, muted</li>
					<li>Fourth item</li>
					<li>Fifth item, blue</li>
				</ul>
				<ol><li>One, blue</li><li>Two</li><li>Three, blue</li><li>Four</li></ol>
			</div>
		</div>
		<div class="card card-plain">
			<div class="body"><p>A plain card's opening paragraph.</p></div>
		</div>
		<p>
			<span class="wp-block-button"><span class="wp-block-button__link btn">Filled button</span></span>
			<span class="wp-block-button is-style-outline"><span class="wp-block-button__link btn">Outline button</span></span>
			<span class="badge"><b>BADGE</b></span>
			<span class="tag"><b>LOUD TAG</b></span>
			<span class="tag" data-tone="quiet"><b>QUIET TAG</b></span>
		</p>

		<h2 class="section">A CMS article</h2>
		<p class="caption">The paragraph after each subheading is italic, unless it is a note. Links in paragraphs and list items are green, and the external one orange. Inline code is red on grey, and code in the pre block is not.</p>
		<div class="entry-content">
			<h3>A subheading</h3>
			<p>Italic, with <a href="https://example.com/a">a green link</a> and <a class="external" href="https://example.org/">an orange one</a>.</p>
			<h4>Another subheading</h4>
			<p class="note">A note: not italic, with <code>inline code</code>.</p>
			<ul><li><a href="https://example.com/b">A green link in a list</a></li></ul>
			<pre><code>code in a pre block</code></pre>
		</div>

		<h2 class="section">An invoice</h2>
		<p class="caption">The quantity and amount columns are right-aligned, headings too. Even rows are shaded, unless void. The void row is grey after its first cell. The descriptions, which are neither the first cell nor a number, are grey.</p>
		<table class="items">
			<thead><tr><th>Item</th><th>Description</th><th class="qty">Qty</th><th class="amount">Amount</th></tr></thead>
			<tbody>
				<tr><td>A-100</td><td>Consulting</td><td class="qty">4</td><td class="amount">480.00</td></tr>
				<tr><td>A-200</td><td>Design</td><td class="qty">2</td><td class="amount">190.00</td></tr>
				<tr data-state="void"><td>A-300</td><td>Hosting</td><td class="qty">12</td><td class="amount">102.00</td></tr>
				<tr data-state="void"><td>A-400</td><td>Support</td><td class="qty">1</td><td class="amount">60.00</td></tr>
			</tbody>
		</table>

		<h2 class="section">Competing rules</h2>
		<p class="caption">:is() and :not() weigh as their heaviest argument, :where() as nothing. Each paragraph says the colour it should be.</p>
		<div class="pricing" id="plans">
			<p class="basic">Orange: .pricing &gt; p outweighs :where(#plans) &gt; p, and comes after .pricing &gt; p:where(.basic), which weighs the same.</p>
			<p class="pro">Blue: .pricing &gt; p:is(.pro, #enterprise) weighs as an id.</p>
			<p class="team">Green: .pricing &gt; p:not(.pro, .basic) outweighs .pricing &gt; p.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
