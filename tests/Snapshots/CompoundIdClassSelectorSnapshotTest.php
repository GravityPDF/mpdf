<?php

namespace Snapshots;

/**
 * Selectors that write an id and classes as one part, such as `p#i.c`, `p.c#i`, `#i.c` and `p.a.b#i`, on their own,
 * after an ancestor, as the ancestor and in a table, each applied after `p#i` and `.c`.
 *
 * @group snapshot
 */
class CompoundIdClassSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'compound-id-class-selector';
	}

	/**
	 * Sections each styled by rules naming an id with classes, under a caption saying what should be seen
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

			.note { color: #ff0000; }
			p#one, p#two, p#three, p#four { color: #ff0000; }
			p#one.note { color: #0a7d32; font-weight: bold; }
			p.note#two { color: #1f3a93; font-style: italic; }
			#three.note { background-color: #f9e79f; color: #000000; }
			p.wide.note#four { color: #0a7d32; font-weight: bold; }

			div.chain p#five.note { color: #1f3a93; font-weight: bold; }
			div#outer.frame p { color: #0a7d32; font-style: italic; }
			table.cells td#cell.hit { background-color: #aed6f1; }
			td#host.box span { color: #0a7d32; font-weight: bold; }

			#six.x.y { color: #0a7d32; font-weight: bold; }
			p#six.x { color: #ff0000; }
		</style>

		<h1>An id written with classes</h1>

		<h2>On their own</h2>
		<p class="caption">.note and p#id make each paragraph red, and the rule naming the id with the class is applied after them.</p>
		<div class="case">
			<p id="one" class="note">p#one.note: green and bold</p>
			<p id="two" class="note">p.note#two, the class written first: blue and italic</p>
			<p id="three" class="note">#three.note, with no tag: black on yellow</p>
			<p id="four" class="note wide">p.wide.note#four, two classes: green and bold</p>
		</div>

		<h2>In descendant rules</h2>
		<p class="caption">div.chain p#five.note: the paragraph is blue and bold, not red.</p>
		<div class="case chain">
			<p id="five" class="note">A paragraph named by id and class inside div.chain</p>
		</div>

		<p class="caption gap">div#outer.frame p: the paragraph is green and italic.</p>
		<div id="outer" class="case frame">
			<p>A paragraph inside the div named by id and class</p>
		</div>

		<h2>Table cells</h2>
		<p class="caption">table.cells td#cell.hit: only the middle cell has a blue background. td#host.box span: the words in the last cell are green and bold.</p>
		<table class="cells">
			<tr><td id="miss" class="hit">td#miss.hit</td><td id="cell" class="hit">td#cell.hit</td><td id="host" class="box">plain, then <span>green words</span></td></tr>
		</table>

		<h2>More classes over a tag</h2>
		<p class="caption">#six.x.y names more classes than p#six.x, so it is applied later: the paragraph is green and bold, not red.</p>
		<div class="case">
			<p id="six" class="x y">A paragraph with two classes</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
