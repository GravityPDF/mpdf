<?php

namespace Snapshots;

/**
 * Stylesheet rules with a descendant selector that has a part the legacy parser cannot read, matched whole or dropped
 * whole rather than cut short and applied to whichever element the parts before it name, and rules with two
 * nth-child parts keeping both.
 *
 * @group snapshot
 */
class UnmatchableDescendantSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'unmatchable-descendant-selector';
	}

	/**
	 * Sections each styled by rules that must not reach them, or must reach only part of them, under a caption
	 * saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 4mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			p.gap { margin-top: 3mm; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 1mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			div.combinators p > span { color: #ff0000; font-size: 16pt; }
			div.combinators p + p { color: #ff0000; font-size: 16pt; }
			div.combinators p ~ p { color: #ff0000; font-size: 16pt; }

			nav ul li:last-child { color: #ff0000; font-weight: bold; }
			div.links p a:hover { background-color: #ff0000; }
			div.quotes p::before { color: #ff0000; font-size: 16pt; }

			div.mixed p > span, div.mixed p.keep { color: #0a7d32; font-weight: bold; }
			div.valid div p { color: #1f3a93; font-style: italic; }

			table.second tr:nth-child(2n) td:nth-child(2) { background-color: #f9e79f; }
			table.spaced tr:nth-child(2n + 1) td:nth-child(odd) { background-color: #aed6f1; }
			table.absent tr:nth-child(2n) td:nth-child(5) { background-color: #ff0000; }
		</style>

		<h1>Descendant rules the legacy parser cannot read</h1>

		<h2>Child and sibling combinators</h2>
		<p class="caption">Rules using &gt;, + and ~ apply to the element their last part names and to nothing before it. The span, and the second and third paragraphs, are red at 16pt. The rest of the first paragraph is black at the normal size.</p>
		<div class="case combinators">
			<p>First paragraph, with <span>a span</span></p>
			<p>Second paragraph</p>
			<p>Third paragraph</p>
		</div>

		<h2>Pseudo-classes and pseudo-elements</h2>
		<p class="caption">Rules using :last-child, :hover and ::before are dropped. Nothing on this list or these paragraphs is red or bold, and no paragraph has a red background.</p>
		<nav class="case">
			<ul>
				<li>First item</li>
				<li>Second item</li>
				<li>Last item</li>
			</ul>
		</nav>
		<div class="case links">
			<p>A paragraph with <a href="https://example.com/">a link</a> in it</p>
		</div>
		<div class="case quotes">
			<p>A paragraph with no generated content</p>
		</div>

		<h2>A selector the legacy parser cannot read beside one it can</h2>
		<p class="caption">Both selectors apply: the span and the second paragraph are green and bold. The rest of the first paragraph is black.</p>
		<div class="case mixed">
			<p>First paragraph, with <span>a span</span></p>
			<p class="keep">Second paragraph</p>
		</div>

		<h2>A rule mPDF can match</h2>
		<p class="caption">div.valid div p applies: the paragraph is blue and italic.</p>
		<div class="case valid">
			<div><p>A paragraph two blocks down</p></div>
		</div>

		<h2>Two nth-child parts</h2>
		<p class="caption">Yellow in the second cell of the second and fourth rows only, not across the whole row.</p>
		<table class="second">
			<?php for ($row = 1; $row <= 4; $row++) { ?>
			<tr><td>R<?php echo $row; ?> C1</td><td>R<?php echo $row; ?> C2</td><td>R<?php echo $row; ?> C3</td></tr>
			<?php } ?>
		</table>

		<p class="caption gap">tr:nth-child(2n + 1) td:nth-child(odd), written with spaces: blue in the first and third cells of the first and third rows.</p>
		<table class="spaced">
			<?php for ($row = 1; $row <= 4; $row++) { ?>
			<tr><td>R<?php echo $row; ?> C1</td><td>R<?php echo $row; ?> C2</td><td>R<?php echo $row; ?> C3</td></tr>
			<?php } ?>
		</table>

		<p class="caption gap">td:nth-child(5) names a column this table does not have: no cell is red.</p>
		<table class="absent">
			<?php for ($row = 1; $row <= 4; $row++) { ?>
			<tr><td>R<?php echo $row; ?> C1</td><td>R<?php echo $row; ?> C2</td><td>R<?php echo $row; ?> C3</td></tr>
			<?php } ?>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
