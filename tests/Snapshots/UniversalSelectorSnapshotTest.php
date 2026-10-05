<?php

namespace Snapshots;

/**
 * The universal selector *, one form to a rule and one case to a caption, in the standard CSS mode.
 *
 * @group snapshot
 */
class UniversalSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'universal-selector';
	}

	/**
	 * Cases each styled by one rule, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; color: #000000; }
			h2 { margin: 3mm 0 1mm 0; font-size: 10pt; color: #000000; }
			p.caption { margin: 0 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p, div.case h4, div.case ul { margin: 0.5mm 0; }
			div.case h4 { font-size: 9pt; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }

			* { color: #1f3a93; }
			*.hl { color: #c00000; font-weight: bold; }
			*#pick { background-color: #f9e79f; }
			*[title] { color: #0a7d32; font-weight: bold; }
			div.inside * { color: #c00000; }
			div.deep * em { color: #c00000; font-weight: bold; }
			div.children > * { border-left: 1mm solid #c00000; padding-left: 1mm; }
			div.after * + p { color: #c00000; font-weight: bold; }
			div.later h4 ~ * { color: #c00000; }
			div.is > :is(*):first-child { color: #c00000; }
			div.never p:not(*) { color: #c00000; }
			table.first tr > *:first-child { background-color: #f9e79f; }
			ul.rest > * + * { color: #c00000; font-weight: bold; }
		</style>

		<h1>The universal selector</h1>

		<h2>*</h2>
		<p class="caption">Text no other rule colours is navy: * reaches every element, and every other rule on this page outweighs it. The captions stay grey and the headings black, from their own rules.</p>
		<div class="case">
			<p>A paragraph</p>
			<ul><li>A list item</li></ul>
		</div>

		<h2>*.hl</h2>
		<p class="caption">The paragraph and the span with class hl are red and bold. The paragraph without it stays navy.</p>
		<div class="case">
			<p class="hl">With the class</p>
			<p>Without the class, <span class="hl">a span with it</span></p>
		</div>

		<h2>*#pick</h2>
		<p class="caption">Only the paragraph with id pick has a yellow background.</p>
		<div class="case">
			<p id="pick">With the id</p>
			<p>Without the id</p>
		</div>

		<h2>*[title]</h2>
		<p class="caption">The paragraph and the item with a title are green and bold; the others stay navy.</p>
		<div class="case">
			<p title="a">With a title</p>
			<p>Without a title</p>
			<ul><li title="b">An item with a title</li><li>An item without</li></ul>
		</div>

		<h2>div.inside *</h2>
		<p class="caption">Everything inside the box is red: the paragraph, the span in it and the list item. The paragraph after the box stays navy.</p>
		<div class="case inside">
			<p>A paragraph with <span>a span</span></p>
			<ul><li>A list item</li></ul>
		</div>
		<p>After the box</p>

		<h2>div.deep * em</h2>
		<p class="caption">The emphasis inside the paragraph is red and bold. The one written straight into the box has no element between it and the box, and stays navy.</p>
		<div class="case deep">
			<em>Straight in the box</em>
			<p>In a paragraph, <em>emphasised</em></p>
		</div>

		<h2>div.children &gt; *</h2>
		<p class="caption">The box's two children have a red bar on their left. The span inside the paragraph does not.</p>
		<div class="case children">
			<p>A child with <span>a span in it</span></p>
			<h4>Another child</h4>
		</div>

		<h2>div.after * + p</h2>
		<p class="caption">The paragraphs after another element are red and bold. The first paragraph stays navy.</p>
		<div class="case after">
			<p>The first paragraph</p>
			<p>After a paragraph</p>
			<h4>A heading</h4>
			<p>After the heading</p>
		</div>

		<h2>div.later h4 ~ *</h2>
		<p class="caption">Everything after the heading is red. The paragraph before it and the heading stay navy.</p>
		<div class="case later">
			<p>Before the heading</p>
			<h4>The heading</h4>
			<p>After the heading</p>
			<p>After that</p>
		</div>

		<h2>div.is &gt; :is(*):first-child</h2>
		<p class="caption">The box's first child is red. The second stays navy.</p>
		<div class="case is">
			<p>The first child</p>
			<p>The second child</p>
		</div>

		<h2>div.never p:not(*)</h2>
		<p class="caption">Every element is an element, so :not(*) matches nothing: the paragraph stays navy.</p>
		<div class="case never">
			<p>A paragraph</p>
		</div>

		<h2>table.first tr &gt; *:first-child</h2>
		<p class="caption">The first cell of each row is yellow, whether it is a header or a data cell.</p>
		<table class="first">
			<tr><th>Head</th><th>Head 2</th></tr>
			<tr><td>R1 C1</td><td>R1 C2</td></tr>
		</table>

		<h2>ul.rest &gt; * + *</h2>
		<p class="caption">Every item but the first is red and bold.</p>
		<div class="case">
			<ul class="rest"><li>First</li><li>Second</li><li>Third</li></ul>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
