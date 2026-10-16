<?php

namespace Snapshots;

/**
 * SVG <use>, <tref> and gradient references found by their id alone, with a ">" in another attribute or a data-id
 * standing before the id they name
 *
 * @group snapshot
 */
class SvgReferenceSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'svg-references';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$open = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="300" height="60">';

		$html = '<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
			</style>
			<h1>SVG references with a ">" in another attribute</h1>

			<p class="caption">A &lt;use&gt; with a "&gt;" in a data attribute: a red square at the left, and a copy of it moved to the right.</p>
			' . $open . '<rect id="r" width="40" height="40" fill="#cc0000"/>'
			. '<use data-note="a > b" xlink:href="#r" x="120"/></svg>

			<p class="caption">A &lt;use&gt; with an end tag and a "&gt;" after its reference: a green square in the middle.</p>
			' . $open . '<defs><rect id="s" width="40" height="40" fill="#009900"/></defs>'
			. '<use xlink:href="#s" data-note="a > b" x="120"></use></svg>

			<p class="caption">A &lt;use&gt; of "#b", after a red rectangle with data-id="b": a blue square, and no red.</p>
			' . $open . '<defs><rect data-id="b" width="300" height="60" fill="#cc0000"/>'
			. '<rect id="b" width="40" height="40" fill="#0000cc"/></defs><use xlink:href="#b"/></svg>

			<p class="caption">A gradient that links to another, with a "&gt;" in a data attribute: a bar shading from red on the left to blue on the right.</p>
			' . $open . '<defs><linearGradient id="g1"><stop offset="0" stop-color="#cc0000"/><stop offset="1" stop-color="#0000cc"/></linearGradient>'
			. '<linearGradient data-note="a > b" id="g2" xlink:href="#g1"/></defs><rect width="300" height="40" fill="url(#g2)"/></svg>

			<p class="caption">A &lt;tref&gt; with a "&gt;" in a data attribute, of "#t", after a text with data-id="t": the word "Right" only.</p>
			' . $open . '<defs><text data-id="t">Wrong</text><text id="t">Right</text></defs>'
			. '<text x="2" y="30" font-size="24"><tref data-note="a > b" xlink:href="#t"/></text></svg>

			<p class="caption">An empty &lt;markers/&gt; and an empty &lt;pattern/&gt; before a red square, then a &lt;marker&gt;: a red square.</p>
			' . $open . '<markers/><pattern id="p"/><rect width="40" height="40" fill="#cc0000"/>'
			. '<marker id="m"><rect width="5" height="5" fill="#0000cc"/></marker></svg>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
