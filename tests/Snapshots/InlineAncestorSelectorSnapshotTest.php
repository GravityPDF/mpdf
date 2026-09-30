<?php

namespace Snapshots;

/**
 * Descendant rules matched through inline elements, blocks inside a table cell and a tbody a table implies, in
 * standard mode. One selector to a rule and one case to a caption.
 *
 * @group snapshot
 */
class InlineAncestorSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inline-ancestor-selectors';
	}

	/**
	 * Cases each styled by one rule, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 3mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 0.5mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }

			span.quote em { color: #c00000; font-weight: bold; }
			.flag b { color: #c00000; }
			a span { color: #1f3a93; background-color: #d6eaf8; }
			div.mixed span b { color: #c00000; }
			span.x em.y strong { color: #c00000; }
			td span.badge b { color: #ffffff; background-color: #c00000; }
			td div.note i { color: #c00000; font-weight: bold; }
			table.bare tbody td { background-color: #d5f5e3; }
		</style>

		<h1>Descendant rules through inline ancestors</h1>

		<h2>span em</h2>
		<p class="caption">Only the emphasis inside the quote span is red and bold. The emphasis outside it stays black.</p>
		<div class="case">
			<p>He said <span class="quote">it was <em>never</em> finished</span>, and <em>meant</em> it.</p>
		</div>

		<h2>.flag b, with the class on a span and on a paragraph</h2>
		<p class="caption">The bold words inside the span and inside the paragraph with the class are red. The bold word outside both stays black.</p>
		<div class="case">
			<p>Plain text, <span class="flag">a <b>flagged</b> span</span>, and a <b>plain</b> bold word.</p>
			<p class="flag">A flagged paragraph with a <b>bold</b> word.</p>
		</div>

		<h2>a span</h2>
		<p class="caption">The span inside the link is blue on a light blue background. The span outside the link has neither.</p>
		<div class="case">
			<p><a href="https://example.com/">A link with <span>a span</span> in it</a>, then <span>a span</span> outside.</p>
		</div>

		<h2>div span b, through a block and an inline element</h2>
		<p class="caption">Only the bold word inside the span is red. The bold word directly in the paragraph stays black.</p>
		<div class="case mixed">
			<p>Direct <b>bold</b>, then <span>a span with <b>bold</b> in it</span>.</p>
		</div>

		<h2>span.x em.y strong, two inline ancestors</h2>
		<p class="caption">The strong text inside both is red. The strong text in em.y without the span stays black.</p>
		<div class="case">
			<p><span class="x">One <em class="y">two <strong>three</strong></em></span> and <em class="y"><strong>four</strong></em>.</p>
		</div>

		<h2>td span.badge b, an inline ancestor in a table cell</h2>
		<p class="caption">The bold text in the badge is white on red. The bold text outside the badge stays black.</p>
		<table>
			<tr><td>Status: <span class="badge"><b>OVERDUE</b></span></td><td><b>Plain bold</b></td></tr>
		</table>

		<h2>td div.note i, a block inside a table cell</h2>
		<p class="caption">The italic text inside the note is red and bold. The italic text beside it stays black.</p>
		<table>
			<tr><td><div class="note">A note with <i>italic</i> text.</div><i>Italic</i> outside the note.</td></tr>
		</table>

		<h2>table tbody td, with the tbody left out</h2>
		<p class="caption">Rows written straight into the table sit in a tbody: every cell is green.</p>
		<table class="bare">
			<tr><td>R1 C1</td><td>R1 C2</td></tr>
			<tr><td>R2 C1</td><td>R2 C2</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
