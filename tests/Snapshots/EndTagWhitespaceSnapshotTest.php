<?php

namespace Snapshots;

/**
 * End tags with a space, a newline or an attribute before their ">" end their elements as plain ones do: the page
 * header and footer, <style>, <pre>, <textarea>, table captions, and inline SVG and its gradients
 *
 * @group snapshot
 */
class EndTagWhitespaceSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'end-tag-whitespace';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<htmlpageheader name="h"><p class="header">Page header</p></htmlpageheader >
			<htmlpagefooter name="f"><p class="footer">Page footer</p></htmlpagefooter
			>
			<sethtmlpageheader name="h" value="on" show-this-page="1" /><sethtmlpagefooter name="f" value="on" />
			<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
				.header { text-align: center; color: #3366cc; }
				.footer { text-align: center; color: #cc6600; }
			</style >
			<style>.blue { color: blue; }</style class="x">
			<style>.green { color: green; }</style>

			<h1>End tags with whitespace or an attribute before their "&gt;"</h1>

			<p class="caption">A page header and footer ending "&lt;/htmlpageheader &gt;" and "&lt;/htmlpagefooter" then a newline: "Page header" in blue centred at the top and "Page footer" in orange centred at the bottom, and neither here.</p>

			<p class="caption">Three stylesheets, ending "&lt;/style &gt;", "&lt;/style class="x"&gt;" and "&lt;/style&gt;": no CSS drawn as text, then a blue line and a green line.</p>
			<p class="blue">Blue text</p>
			<p class="green">Green text</p>

			<p class="caption">A &lt;pre&gt; ending "&lt;/pre &gt;": three lines, the third indented.</p>
			<pre>line 1
line 2
    indented</pre >

			<p class="caption">A &lt;textarea&gt; ending "&lt;/textarea &gt;": a box holding three lines, then an empty box ending "&lt;/textarea &gt;".</p>
			<form><textarea rows="4" cols="20">line 1
line 2
line 3</textarea ><br /><textarea name="empty" rows="1" cols="20"></textarea ></form>

			<p class="caption">A table whose caption ends "&lt;/caption &gt;": "The caption" above a table of one cell.</p>
			<table border="1"><caption>The caption</caption ><tr><td>cell</td></tr></table>

			<p class="caption">An inline SVG ending "&lt;/svg &gt;", then a line, then another SVG: a red square, "Middle", a blue square.</p>
			<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"><rect width="30" height="30" fill="red"/></svg >
			<p>Middle</p>
			<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"><rect width="30" height="30" fill="blue"/></svg>

			<p class="caption">An SVG gradient ending "&lt;/linearGradient &gt;" whose stops a second gradient borrows: a rectangle shading from red to blue.</p>
			<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="120" height="30"><defs><linearGradient id="a"><stop offset="0" stop-color="red"/><stop offset="1" stop-color="blue"/></linearGradient ><linearGradient id="b" xlink:href="#a"/></defs><rect width="120" height="30" fill="url(#b)"/></svg>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
