<?php

namespace Snapshots;

/**
 * The HTML presentational attributes under the standard cascade: each read on the elements HTML gives it to, over
 * the built-in defaults and under any stylesheet rule. One attribute per case, under a caption saying what should be
 * seen; green is what the standard cascade draws where the legacy cascade drew otherwise.
 *
 * @group snapshot
 */
class PresentationalAttributesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'presentational-attributes';
	}

	/**
	 * One attribute per case, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$image = __DIR__ . '/../data/img/bg.jpg';

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 2.5mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }
			div.case p { margin: 0.5mm 0; }
			table.grid td { border: 0.2mm solid #999999; }

			p.left { text-align: left; }
			td.ruled { text-align: left; }
			table.ruled { border: 0.8mm solid #0a7d32; }
		</style>

		<h1>Presentational attributes</h1>

		<p class="caption">&lt;hr color&gt; beats the grey of the built-in defaults: the rule is green.</p>
		<div class="case"><hr color="#0a7d32" /></div>

		<p class="caption">&lt;hr width="50%"&gt; beats the full width of the built-in defaults: the rule is half as wide as the box, and centred.</p>
		<div class="case"><hr width="50%" /></div>

		<p class="caption">&lt;hr align="left"&gt; puts a half-width rule at the left of the box.</p>
		<div class="case"><hr width="50%" align="left" /></div>

		<p class="caption">&lt;hr size="6"&gt; draws the rule 6px thick.</p>
		<div class="case"><hr size="6" /></div>

		<p class="caption">&lt;font color&gt; colours its text: "font" is green.</p>
		<div class="case"><p><font color="#0a7d32">font</font> and text around it</p></div>

		<p class="caption">A span, a paragraph and a link have no color attribute: "span" and "paragraph" are black, and the link is blue.</p>
		<div class="case"><p><span color="#ff0000">span</span> and <a href="#x" color="#ff0000">a link</a></p><p color="#ff0000">paragraph</p></div>

		<p class="caption">&lt;p align="right"&gt; puts its text at the right.</p>
		<div class="case"><p align="right">Aligned right</p></div>

		<p class="caption">A stylesheet rule beats &lt;p align="right"&gt;: the text is at the left.</p>
		<div class="case"><p class="left" align="right">A rule against the attribute</p></div>

		<p class="caption">&lt;div align="center"&gt; centres the paragraphs in it.</p>
		<div class="case"><div align="center"><p>A paragraph in a centred div</p></div></div>

		<p class="caption">&lt;td align&gt; and &lt;td valign&gt; place the text of a cell: "right" at the bottom right; a rule beats the align of "ruled", which is at the left.</p>
		<table class="grid" width="100%"><tr><td height="12mm" align="right" valign="bottom">right</td><td align="right" class="ruled">ruled</td></tr></table>

		<p class="caption">&lt;td nowrap&gt; keeps a narrow cell's text on one line.</p>
		<table class="grid" width="20mm"><tr><td nowrap="nowrap">one line of text</td></tr></table>

		<p class="caption">&lt;td bgcolor&gt; fills the cell: the cell is green.</p>
		<table class="grid"><tr><td bgcolor="#0a7d32">&nbsp;&nbsp;&nbsp;&nbsp;</td></tr></table>

		<p class="caption">&lt;table border="1"&gt; draws a thin black border around the table and each cell, and a rule for the table's border beats it: the outer border is thick and green, the cells' thin and black.</p>
		<table border="1"><tr><td>plain</td><td>plain</td></tr></table>
		<table border="1" class="ruled"><tr><td>ruled</td><td>ruled</td></tr></table>

		<p class="caption">&lt;table border="3"&gt; draws a 3px border around the table.</p>
		<table border="3"><tr><td>three pixels</td></tr></table>

		<p class="caption">&lt;caption align="bottom"&gt; puts the caption below its table.</p>
		<table class="grid"><caption align="bottom">The caption</caption><tr><td>The table</td></tr></table>

		<pagebreak />

		<p class="caption">&lt;li type="square"&gt; beats the disc the item inherits from its list: the first marker is a square, the second a disc.</p>
		<div class="case"><ul><li type="square">A square</li><li>A disc</li></ul></div>

		<p class="caption">&lt;img vspace hspace&gt; beats the image's default margin of 0: the image stands 20px clear of the text on each side.</p>
		<div class="case">before <img src="<?php echo $image; ?>" width="30" vspace="20" hspace="20" /> after</div>

		<p class="caption">&lt;img align="right"&gt; floats the image to the right, and the text flows at its left.</p>
		<div class="case"><img src="<?php echo $image; ?>" width="30" align="right" />Text beside the image.</div>

		<p class="caption">&lt;img border="3"&gt; draws a 3px black border around the image.</p>
		<div class="case"><img src="<?php echo $image; ?>" width="30" border="3" /></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
