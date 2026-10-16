<?php

namespace Snapshots;

/**
 * A ">" inside a quoted attribute value does not end the tag where mPDF reads tags before WriteHTML() does: the
 * <html>, <link> and <style> tags, tables with captions, inline SVG, SVG text and index entries
 *
 * @group snapshot
 */
class AngleBracketInTagSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'angle-bracket-in-tag';
	}

	/**
	 * Each case under a caption saying what it should show, then the index the entries make
	 */
	public function generatePdf()
	{
		$svg = '<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;8&quot; height=&quot;8&quot;>'
			. '<rect width=&quot;4&quot; height=&quot;4&quot; fill=&quot;#3366cc&quot;/></svg>';

		$html = '<html title="a > b" lang="fr"><head>
			<link title="a > b" href="data:text/css,.linked { color: green; }" rel="stylesheet">
			<style title="a > b" media="print" data-media="screen">.styled { color: blue; }</style>
			<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
				p:lang(fr) .french { color: #cc6600; }
				.square { width: 20mm; }
			</style>
		</head><body>
			<h1>A ">" in the attributes of tags read before the document is drawn</h1>

			<p class="caption">An &lt;html&gt; with a ">" in its title before lang="fr": the next line is orange.</p>
			<p><span class="french">Orange text</span></p>

			<p class="caption">A stylesheet linked by a &lt;link&gt; with a ">" in its title, and rel after href: the next line is green.</p>
			<p class="linked">Green text</p>

			<p class="caption">A &lt;style&gt; with a ">" in its title, for print, with a data-media of screen after it: the next line is blue.</p>
			<p class="styled">Blue text</p>

			<p class="caption">A table with a ">" in its summary: the caption "The caption" above a table of one cell.</p>
			<table border="1" summary="a > b"><caption>The caption</caption><tr><td>cell</td></tr></table>

			<p class="caption">A link titled "draw &lt;svg&gt;", then a line and an inline SVG: "link", "Middle", a red square and "After".</p>
			<p><a href="#x" title="draw <svg>">link</a></p><p>Middle</p>
			<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="red"/></svg>
			<p>After</p>

			<p class="caption">A block with an SVG in a data URI as its background: a box of small blue squares reading "box", and nothing else.</p>
			<div style="background-image: url(\'data:image/svg+xml;utf8,' . $svg . '\'); padding: 2mm">box</div>

			<p class="caption">SVG text with a ">" in an attribute: "Hello", then a Hebrew word in a Hebrew font.</p>
			<svg xmlns="http://www.w3.org/2000/svg" width="200" height="30"><text x="2" y="20" font-size="16" data-note="a > b">Hello שלום</text></svg>

			<p class="caption">An index entry whose span has a title holding "1:2": the index lists "Mammals", then "- Elephants 1" under it.</p>
			<p><indexentry content="&lt;span title=&quot;ratio &gt; 1:2&quot;&gt;Mammals&lt;/span&gt;:Elephants" />Entry</p>
			<indexinsert usedivletters="off" />
		</body></html>';

		$this->mpdf = $this->createMpdf(['autoScriptToLang' => true, 'autoLangToFont' => true, 'svgAutoFont' => true]);

		$this->mpdf->WriteHTML($html);
	}

}
