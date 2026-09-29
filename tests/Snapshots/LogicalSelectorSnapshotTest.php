<?php

namespace Snapshots;

/**
 * :not(), :is() and :where(), one selector to a rule and one case to a caption.
 *
 * @group snapshot
 */
class LogicalSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'logical-selectors';
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
			div.case p, div.case h3, div.case h4 { margin: 0.5mm 0; }
			div.case h3, div.case h4 { font-size: 9pt; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }

			div.not-class p:not(.a) { color: #c00000; font-weight: bold; }
			div.not-list p:not(.a, .b) { color: #c00000; font-weight: bold; }
			div.not-first li:not(:first-child) { color: #c00000; }
			div.not-complex p:not(section > p) { color: #c00000; font-weight: bold; }
			div.is-sibling :is(h3, h4) + p { color: #1f3a93; font-weight: bold; }
			div.is-element p:is(.lead, #intro) { color: #1f3a93; font-weight: bold; }
			div.where :where(ul, ol) > li { color: #0a7d32; }
			table.not-cell td:not(:first-child) { background-color: #f9e79f; }
		</style>

		<h1>:not(), :is() and :where()</h1>

		<h2>p:not(.a)</h2>
		<p class="caption">Every paragraph but the one with class a is red and bold.</p>
		<div class="case not-class">
			<p class="a">Class a</p>
			<p class="b">Class b</p>
			<p>No class</p>
		</div>

		<h2>p:not(.a, .b)</h2>
		<p class="caption">Only the paragraph with neither class is red and bold.</p>
		<div class="case not-list">
			<p class="a">Class a</p>
			<p class="b">Class b</p>
			<p class="c">Class c</p>
		</div>

		<h2>li:not(:first-child)</h2>
		<p class="caption">Every item but the first is red.</p>
		<div class="case not-first">
			<ul><li>First</li><li>Second</li><li>Third</li></ul>
		</div>

		<h2>p:not(section &gt; p)</h2>
		<p class="caption">The paragraph directly inside the section stays black; the others are red and bold.</p>
		<div class="case not-complex">
			<p>In the box</p>
			<section><p>Directly in the section</p><div><p>In a div in the section</p></div></section>
		</div>

		<h2>:is(h3, h4) + p</h2>
		<p class="caption">The paragraphs right after the h3 and the h4 are blue and bold. The one after the h5 and the one after a paragraph stay black.</p>
		<div class="case is-sibling">
			<h3>An h3</h3><p>After the h3</p>
			<h4>An h4</h4><p>After the h4</p>
			<h5>An h5</h5><p>After the h5</p>
			<p>After a paragraph</p>
		</div>

		<h2>p:is(.lead, #intro)</h2>
		<p class="caption">The paragraph with class lead and the one with id intro are blue and bold. The third stays black.</p>
		<div class="case is-element">
			<p class="lead">Class lead</p>
			<p id="intro">Id intro</p>
			<p class="note">Class note</p>
		</div>

		<h2>:where(ul, ol) &gt; li</h2>
		<p class="caption">The items of both lists are green.</p>
		<div class="case where">
			<ul><li>Bullet</li></ul>
			<ol><li>Number</li></ol>
		</div>

		<h2>td:not(:first-child)</h2>
		<p class="caption">Every cell but the first of each row is yellow.</p>
		<table class="not-cell">
			<tr><td>R1 C1</td><td>R1 C2</td><td>R1 C3</td></tr>
			<tr><td>R2 C1</td><td>R2 C2</td><td>R2 C3</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
