<?php

namespace Snapshots;

/**
 * The child, adjacent sibling and general sibling combinators, and :first-child, :nth-child(), :first-of-type and
 * :nth-of-type() on any element, one selector to a rule and one case to a caption.
 *
 * @group snapshot
 */
class StructuralSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'structural-selectors';
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
			div.case p, div.case h3 { margin: 0.5mm 0; }
			div.case h3 { font-size: 9pt; }
			div.case ul, div.case ol { margin: 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }

			div.child > p { color: #c00000; font-weight: bold; }
			div.adjacent h3 + p { color: #c00000; font-weight: bold; }
			div.general h3 ~ p { color: #c00000; font-weight: bold; }
			div.first-child li:first-child { color: #c00000; font-weight: bold; }
			ol.even li:nth-child(2n) { background-color: #f9e79f; }
			ol.top li:nth-child(-n+2) { color: #1f3a93; font-style: italic; }
			div.first-type p:first-of-type { color: #c00000; font-weight: bold; }
			div.nth-type p:nth-of-type(2) { color: #c00000; font-weight: bold; }
			p.inline > b { color: #c00000; }
			table.cells td:first-child { background-color: #aed6f1; }
			table.cells td + td + td { color: #c00000; font-weight: bold; }
			table.bare > tbody > tr > td { background-color: #d5f5e3; }
		</style>

		<h1>Child and sibling combinators, and structural pseudo-classes</h1>

		<h2>div &gt; p</h2>
		<p class="caption">Only the paragraph directly inside the box is red and bold. The one inside the nested section stays black.</p>
		<div class="case child">
			<p>Child of the box</p>
			<section><p>Grandchild, inside a section</p></section>
		</div>

		<h2>h3 + p</h2>
		<p class="caption">Only the paragraph right after the heading is red and bold. The paragraphs before the heading and after the first one stay black.</p>
		<div class="case adjacent">
			<p>Before the heading</p>
			<h3>Heading</h3>
			<p>Right after the heading</p>
			<p>After that one</p>
		</div>

		<h2>h3 ~ p</h2>
		<p class="caption">Every paragraph after the heading is red and bold, even past the div between them. The paragraph before the heading and the one inside the div stay black.</p>
		<div class="case general">
			<p>Before the heading</p>
			<h3>Heading</h3>
			<p>First after the heading</p>
			<div><p>Inside a div after the heading</p></div>
			<p>After the div</p>
		</div>

		<h2>li:first-child</h2>
		<p class="caption">The first item of each list is red and bold, the rest black.</p>
		<div class="case first-child">
			<ul><li>First bullet</li><li>Second bullet</li><li>Third bullet</li></ul>
			<ol><li>First number</li><li>Second number</li></ol>
		</div>

		<h2>li:nth-child(2n)</h2>
		<p class="caption">The second, fourth and sixth items have a yellow background.</p>
		<div class="case">
			<ol class="even"><li>One</li><li>Two</li><li>Three</li><li>Four</li><li>Five</li><li>Six</li></ol>
		</div>

		<h2>li:nth-child(-n+2)</h2>
		<p class="caption">The first two items are blue and italic, the third and fourth black.</p>
		<div class="case">
			<ol class="top"><li>One</li><li>Two</li><li>Three</li><li>Four</li></ol>
		</div>

		<h2>p:first-of-type</h2>
		<p class="caption">The first paragraph is red and bold, though the heading comes before it. The second paragraph stays black.</p>
		<div class="case first-type">
			<h3>Heading</h3>
			<p>First paragraph</p>
			<p>Second paragraph</p>
		</div>

		<h2>p:nth-of-type(2)</h2>
		<p class="caption">Only the second paragraph is red and bold, counting past the heading between them.</p>
		<div class="case nth-type">
			<p>First paragraph</p>
			<h3>Heading</h3>
			<p>Second paragraph</p>
			<p>Third paragraph</p>
		</div>

		<h2>p &gt; b</h2>
		<p class="caption">The bold word directly in the paragraph is red. The one inside the span stays black.</p>
		<div class="case">
			<p class="inline">A <b>direct</b> bold word and <span>a <b>nested</b> one</span>.</p>
		</div>

		<h2>td:first-child and td + td + td</h2>
		<p class="caption">The first cell of each row is blue. Every cell from the third on is red and bold, and a cell spanning two columns counts as one cell: R1 C3, R1 C4 and R2 C3.</p>
		<table class="cells">
			<tr><td>R1 C1</td><td>R1 C2</td><td>R1 C3</td><td>R1 C4</td></tr>
			<tr><td colspan="2">R2 C1, two columns wide</td><td>R2 C2</td><td>R2 C3</td></tr>
		</table>

		<h2>table &gt; tbody &gt; tr &gt; td</h2>
		<p class="caption">Rows written straight into the table sit in a tbody, as in a browser: every cell is green.</p>
		<table class="bare">
			<tr><td>R1 C1</td><td>R1 C2</td></tr>
			<tr><td>R2 C1</td><td>R2 C2</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
