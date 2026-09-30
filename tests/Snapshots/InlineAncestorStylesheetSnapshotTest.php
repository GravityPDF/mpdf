<?php

namespace Snapshots;

/**
 * Stylesheets in the style of CMS themes and invoice templates whose descendant rules name inline elements and
 * blocks inside table cells as ancestors, mixed with rules matched through blocks and with child and sibling
 * combinators, in standard mode.
 *
 * @group snapshot
 */
class InlineAncestorStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inline-ancestor-selectors-stylesheet';
	}

	/**
	 * An article with inline markup, an invoice with badges and notes in its cells, and competing rules, each under a
	 * caption saying what should be seen
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

			/* A theme's article styles */
			.entry-content p { margin: 1mm 0; line-height: 1.5; }
			.entry-content a { color: #0d6efd; }
			.entry-content a strong { color: #6f42c1; }
			.entry-content .has-accent-color em { color: #b02a37; font-weight: bold; }
			.entry-content mark code { background-color: #fff3cd; color: #664d03; }
			.entry-content acronym small { color: #6c757d; }
			.entry-content > p:first-child span.dropcap b { font-size: 16pt; color: #198754; }
			.entry-content blockquote cite span { font-style: normal; color: #6c757d; }

			/* An invoice template */
			table.items { border-collapse: collapse; width: 100%; }
			table.items td, table.items th { border-bottom: 0.2mm solid #adb5bd; padding: 1mm 2mm; }
			table.items th { background-color: #343a40; color: #ffffff; text-align: left; }
			table.items span.badge b { color: #ffffff; background-color: #198754; }
			table.items span.badge.overdue b { background-color: #dc3545; }
			table.items td div.note small { color: #6c757d; font-style: italic; }
			table.items td div.note b { color: #b02a37; }
			table.items span.amount span.currency { color: #6c757d; font-size: 7pt; }
			table.items tr > td:first-child span b { color: #0d6efd; }
			table.items ul ul { margin-top: 0; margin-bottom: 0; }

			/* Competing rules */
			.pricing p { margin: 0.5mm 0; }
			.pricing b { color: #198754; }
			.pricing .deal b { color: #dc3545; }
			.pricing p .deal > b { color: #fd7e14; }
			.pricing .plain b { color: #0d6efd; }
		</style>

		<h1>Descendant rules through inline ancestors in real stylesheets</h1>

		<h2 class="section">A CMS article</h2>
		<p class="caption">The first paragraph's drop cap is large and green. Links are blue, and strong text in a link is purple. Emphasis inside the accent span is red and bold. Code inside the highlight is brown on yellow. Small text in the acronym is grey, and small text outside it black. In the citation, the span is grey and upright.</p>
		<div class="entry-content">
			<p><span class="dropcap"><b>T</b></span>his opening paragraph starts with a drop cap, and has <a href="https://example.com/">a link with <strong>strong text</strong></a> and <strong>strong text</strong> outside the link.</p>
			<p>A sentence with <span class="has-accent-color">an <em>accented</em> phrase</span>, and <em>plain emphasis</em> outside it.</p>
			<p>Run <mark>the <code>build</code> command</mark> before <code>deploy</code>.</p>
			<p>The <acronym>API <small>(interface)</small></acronym> has <small>small print</small> too.</p>
			<blockquote><p>A quoted line.</p><p><cite>A Writer, <span>in a book</span></cite></p></blockquote>
		</div>

		<h2 class="section">An invoice</h2>
		<p class="caption">Each status badge is white on green, or on red when overdue. In the note, the small text is grey and italic and the bold text red. The currency is small and grey beside each amount. The bold text in a span in the first column is blue. The nested list in the last row has no space above or below it.</p>
		<table class="items">
			<tr><th>Item</th><th>Status</th><th>Amount</th></tr>
			<tr>
				<td><span><b>INV-001</b></span> Consulting</td>
				<td><span class="badge"><b>PAID</b></span></td>
				<td><span class="amount"><span class="currency">AUD</span> 480.00</span></td>
			</tr>
			<tr>
				<td><span><b>INV-002</b></span> Design</td>
				<td><span class="badge overdue"><b>OVERDUE</b></span><div class="note"><b>Reminder sent.</b> <small>Second notice.</small></div></td>
				<td><span class="amount"><span class="currency">AUD</span> 190.00</span></td>
			</tr>
			<tr>
				<td colspan="3">
					<ul><li>Hosting
						<ul><li>Twelve months</li><li>Two domains</li></ul>
					</li><li>Support</li></ul>
				</td>
			</tr>
		</table>

		<h2 class="section">Competing rules</h2>
		<p class="caption">The rules compete by specificity, then by source order, whether a block or an inline element is the ancestor they match through. Each bold word says the colour it should be.</p>
		<div class="pricing">
			<p><b>Green</b>: .pricing b, through the div.</p>
			<p><span class="deal"><b>Orange</b></span>: .pricing p .deal &gt; b outweighs .pricing .deal b, which only the span matches.</p>
			<p><span class="plain"><b>Blue</b></span>: .pricing .plain b, which only the span matches, outweighs .pricing b.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
