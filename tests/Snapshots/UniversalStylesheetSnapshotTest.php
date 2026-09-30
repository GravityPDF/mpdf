<?php

namespace Snapshots;

/**
 * A page styled as a CSS framework styles one, starting from a reset that zeroes every element's margins and padding
 * with *, and component rules that space the elements again, some of them with * too. A table of contents, an index,
 * a barcode, a dot tab and a text circle sit on the page. In the standard CSS mode.
 *
 * @group snapshot
 */
class UniversalStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'universal-stylesheet';
	}

	/**
	 * The components, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			/* Reset */
			*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
			* { border-color: #dee2e6; }
			[hidden] { display: none; }

			/* Base */
			body { font-family: sans-serif; font-size: 9pt; line-height: 1.4; color: #212529; }
			h1 { font-size: 15pt; margin-bottom: 2mm; }
			h2 { font-size: 11pt; margin: 5mm 0 1.5mm 0; border-bottom: 0.3mm solid #212529; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin-bottom: 1.5mm; }
			ul, ol { padding-left: 6mm; }

			/* Components */
			.stack > * + * { margin-top: 2mm; }
			.card { border-width: 0.3mm; border-style: solid; padding: 3mm; }
			.card > * { color: #0d6efd; }
			.card > h3 { font-size: 10pt; color: #212529; }
			.muted * { color: #6c757d; }
			#featured * { font-weight: bold; }
			.table { border-collapse: collapse; width: 100%; }
			.table td, .table th { padding: 1mm 2mm; border-bottom-width: 0.3mm; border-bottom-style: solid; text-align: left; }
			.table tr > *:first-child { width: 30%; background-color: #f8f9fa; }
			.label { border: 0.3mm dashed #212529; padding: 2mm; }
			.label * + * { margin-top: 1mm; }
		</style>

		<h1>A framework's reset, with * </h1>

		<h2>Cards<tocentry content="Cards" level="0" /><indexentry content="Cards" /></h2>
		<p class="caption">The reset leaves no space between the paragraphs of a card; .stack &gt; * + * puts 2 mm back between the cards and between the parts of each, and none above the first. The first card's text is blue, from .card &gt; *, and its heading black, the heavier .card &gt; h3 winning. The muted card's text is grey, .muted * being as heavy as .card &gt; * and written after it, and the paragraph marked hidden is not drawn. The featured card's text is bold, from #featured *. The cards' borders are light grey, from * { border-color }.</p>
		<div class="stack">
			<div class="card stack">
				<h3>First card<tocentry content="First card" level="1" /></h3>
				<p>A paragraph with no margin of its own.</p>
				<p>A second paragraph, 2 mm below it.</p>
			</div>
			<div class="card stack muted">
				<h3>Muted card<tocentry content="Muted card" level="1" /><indexentry content="Cards:muted" /></h3>
				<p>Grey text, from .muted *.</p>
				<p hidden>A paragraph hidden by [hidden].</p>
				<ul><li>A list item, grey</li><li>Another</li></ul>
			</div>
			<div class="card stack" id="featured">
				<h3>Featured card<tocentry content="Featured card" level="2" /></h3>
				<p>Bold text, from #featured *.</p>
			</div>
		</div>

		<h2>Table<tocentry content="Table" level="0" /><indexentry content="Table" /></h2>
		<p class="caption">The cells keep the padding of .table td, .table th over the reset, and the light grey rule under each row takes its colour from * { border-color }, which reaches the rows too without taking the cells' rules away. The first cell of each row, a th or a td, is grey, from .table tr &gt; *:first-child.</p>
		<table class="table">
			<tr><th>Item</th><th>Amount</th></tr>
			<tr><td>First</td><td>10.00</td></tr>
			<tr><td>Second</td><td>20.00</td></tr>
		</table>

		<h2>mPDF's own tags<tocentry content="mPDF's own tags" level="0" /><indexentry content="Barcode" /></h2>
		<p class="caption">No rule names the barcode, the dot tab or the text circle, so the reset and .label * + * leave them as they are drawn without a stylesheet. The label's lines are 1 mm apart, the barcode's line included.</p>
		<div class="label">
			<p>Ship to: A. Person</p>
			<p><barcode code="978-0-9542246-0" type="ISBN" /></p>
			<p>Reference<dottab />4711</p>
			<p><textcircle r="12mm" top-text="Top of the circle" bottom-text="Bottom" style="font-size: 7pt" /></p>
		</div>

		<tocpagebreak toc-preHTML="&lt;h2&gt;Table of contents&lt;/h2&gt;&lt;p class=&quot;caption&quot;&gt;The reset reaches the lines of the table of contents, which are divs of the document, as any author rule does, so the levels are not indented. The dot leaders and page numbers are drawn.&lt;/p&gt;" />
		<h2>Index</h2>
		<p class="caption">The reset reaches the index's lines too: its letters have no space above them.</p>
		<indexinsert usedivletters="on" links="off" collation="en_US.utf8" collationgroup="English_United_States" />
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
