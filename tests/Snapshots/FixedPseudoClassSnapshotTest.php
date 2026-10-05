<?php

namespace Snapshots;

/**
 * The pseudo-classes whose answer is fixed in a PDF, and html, one to a caption, in the flow, a table, a page header
 * and a positioned block
 *
 * @group snapshot
 */
class FixedPseudoClassSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'fixed-pseudo-classes';
	}

	/**
	 * One example for each pseudo-class, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			html { color: #1f3a93; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case p { margin: 0.5mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			div.link a:link { color: #ff0000; }
			div.link a:link b { font-size: 12pt; }
			div.any-link :any-link { color: #ff0000; }
			div.visited a:visited { color: #ff0000; }
			div.states a:hover, div.states a:focus, div.states a:active, div.states a:focus-visible, div.states a:target { color: #ff0000; background-color: #ff0000; }
			div.states p:focus-within { color: #ff0000; }
			div.not-hover a:not(:hover) { color: #008000; text-decoration: none; }
			:root div.root-ancestor p { color: #ff0000; }
			:root > div.root-parent p, body:root p { color: #ff0000; }
			html > body > div.html-body p { color: #ff0000; }
			p.rem { font-size: 2rem; }
			td:not(:hover) { background-color: #ffff99; }
			div.header a:link { color: #ff0000; }
			:root div.box p { color: #ff0000; }
		</style>

		<htmlpageheader name="h"><div class="header"><p>Header: <a href="#top">a link, red</a>, in navy text.</p></div></htmlpageheader>
		<sethtmlpageheader name="h" value="on" show-this-page="1" />

		<h1>Pseudo-classes with a fixed answer in a PDF</h1>
		<p class="caption">html { color }. The heading and every piece of text no other rule colours is navy, not black: html is body's parent.</p>

		<p class="caption">a:link, and a:link b. The link is red, and the bold word in it large. The anchor after it has only a name, so it is not a link: its bold word is small and navy.</p>
		<div class="case link"><p><a href="#top">A link with a <b>bold</b> word</a> and <a name="top"><b>an anchor</b></a>.</p></div>

		<p class="caption">:any-link. The link is red.</p>
		<div class="case any-link"><p><a href="#top">Any link</a></p></div>

		<p class="caption">a:visited. No link is visited in a PDF: the link is blue, as links are by default.</p>
		<div class="case visited"><p><a href="#top">Never visited</a></p></div>

		<p class="caption">:hover, :focus, :active, :focus-visible, :target and :focus-within. Nothing a reader does changes a PDF: the link is blue on no background, and the paragraph is navy.</p>
		<div class="case states"><p><a href="#top">Never hovered or focused</a></p></div>

		<p class="caption">a:not(:hover). Always true: the link is green and not underlined.</p>
		<div class="case not-hover"><p><a href="#top">Not hovered</a></p></div>

		<p class="caption">:root as an ancestor. The paragraph is red.</p>
		<div class="case root-ancestor"><p>Inside the root</p></div>

		<p class="caption">:root &gt; div and body:root. The root is html, not body, so neither matches: the paragraph is navy.</p>
		<div class="case root-parent"><p>Not a child of the root</p></div>

		<p class="caption">html &gt; body &gt; div. The paragraph is red.</p>
		<div class="case html-body"><p>Inside body inside html</p></div>

		<p class="caption">2rem. Twice the default font size, 22pt, although body is 9pt: rem is read against html.</p>
		<p class="rem">Two rem</p>

		<p class="caption">td:not(:hover). Every cell is pale yellow.</p>
		<table><tr><td>One</td><td>Two</td><td>Three</td></tr></table>

		<p class="caption">In the page header, the link is red. In the positioned box at the foot of the page, :root div.box p makes the text red.</p>
		<div class="box" style="position: absolute; bottom: 1mm; left: 20mm; width: 120mm; border: 0.2mm solid #999999; padding: 2mm;"><p>Positioned box: red</p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD, 'margin_top' => 28]);

		$this->mpdf->WriteHTML($html);
	}

}
