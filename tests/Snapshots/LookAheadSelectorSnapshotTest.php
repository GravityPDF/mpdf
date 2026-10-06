<?php

namespace Snapshots;

/**
 * :last-child, :nth-last-child(), :only-child, :last-of-type, :nth-last-of-type(), :only-of-type and :empty, one to a
 * caption, in lists, paragraphs, table rows and cells, a page header and a positioned block
 *
 * @group snapshot
 */
class LookAheadSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'look-ahead-selectors';
	}

	/**
	 * One example for each pseudo-class, each under a caption saying what should be red
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 1mm 0; font-size: 8pt; color: #606060; }
			ul, ol { margin: 0; }
			div.case p, div.case h5 { margin: 0.5mm 0; font-size: 9pt; }
			table { border-collapse: collapse; }
			td, th { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			ul.last li:last-child { color: #ff0000; }
			ol.from-end li:nth-last-child(2) { color: #ff0000; }
			ul.only li:only-child { color: #ff0000; }
			div.of-type p:last-of-type { color: #ff0000; }
			div.from-end-of-type p:nth-last-of-type(2) { color: #ff0000; }
			div.only-of-type h5:only-of-type { color: #ff0000; }
			table.empty td:empty { background-color: #ff0000; }
			div.after-empty p:empty + p { color: #ff0000; }
			table.rows tr:last-child td { background-color: #ff9999; }
			table.cells td:last-child { color: #ff0000; }
			ul.not-last li:not(:last-child) { color: #ff0000; }
			div.header p:last-child { color: #ff0000; }
			div.box p:last-child { color: #ff0000; }
		</style>

		<htmlpageheader name="h"><div class="header"><p>Header, first line</p><p>Header, last line: red</p></div></htmlpageheader>
		<sethtmlpageheader name="h" value="on" show-this-page="1" />

		<h1>Pseudo-classes that look at what follows an element</h1>

		<p class="caption">:last-child. The third item is red; the first two are black.</p>
		<ul class="last"><li>First item</li><li>Second item</li><li>Third item</li></ul>

		<p class="caption">:nth-last-child(2). The third of four items is red.</p>
		<ol class="from-end"><li>One</li><li>Two</li><li>Three</li><li>Four</li></ol>

		<p class="caption">:only-child. The item alone in its list is red; the two items of the second list are black.</p>
		<ul class="only"><li>Alone</li></ul>
		<ul class="only"><li>One of two</li><li>Two of two</li></ul>

		<p class="caption">:last-of-type. The second paragraph is red, although a heading follows it.</p>
		<div class="case of-type"><p>First paragraph</p><p>Second paragraph</p><h5>A heading after them</h5></div>

		<p class="caption">:nth-last-of-type(2). The first of the two paragraphs is red; the heading between them does not count.</p>
		<div class="case from-end-of-type"><p>First paragraph</p><h5>A heading between</h5><p>Last paragraph</p></div>

		<p class="caption">:only-of-type. The heading alone of its tag is red; the paragraphs around it are black.</p>
		<div class="case only-of-type"><p>A paragraph</p><h5>The only heading</h5><p>Another paragraph</p></div>

		<p class="caption">:empty. The second cell, with nothing in it, is red. The third, holding a non-breaking space, and the fourth, holding an empty span, are not. The paragraph after an empty one is red; the one after a paragraph holding a space is black, as white space is content in a browser.</p>
		<table class="empty"><tr><td>Text</td><td></td><td>&nbsp;</td><td><span></span></td></tr></table>
		<div class="case after-empty"><p></p><p>After an empty paragraph: red</p><p> </p><p>After a paragraph holding a space: black</p></div>

		<p class="caption">tr:last-child td. The last row, written without a tbody, is pink.</p>
		<table class="rows"><tr><td>Row one</td><td>1</td></tr><tr><td>Row two</td><td>2</td></tr><tr><td>Row three</td><td>3</td></tr></table>

		<p class="caption">td:last-child. The last cell of each row is red, whatever the number of cells in the row.</p>
		<table class="cells"><tr><td>a1</td><td>a2</td><td>a3</td></tr><tr><td colspan="2">b1, two columns</td><td>b2</td></tr><tr><td colspan="3">c1, alone</td></tr></table>

		<p class="caption">:not(:last-child). Every item but the last is red.</p>
		<ul class="not-last"><li>First item</li><li>Second item</li><li>Last item</li></ul>

		<p class="caption">In the page header, the second line is red. In the positioned box at the foot of the page, its last line is red.</p>
		<div class="box" style="position: absolute; line-height: 1.33; bottom: 1mm; left: 20mm; width: 120mm; border: 0.2mm solid #999999; padding: 2mm;"><p>Positioned box, first line</p><p>Positioned box, last line: red</p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD, 'margin_top' => 28]);

		$this->mpdf->WriteHTML($html);
	}

}
