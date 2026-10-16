<?php

namespace Snapshots;

/**
 * A run of spaces inside a quoted attribute value is kept, while the spaces between tags are still collapsed to one
 *
 * @group snapshot
 */
class AttributeWhitespaceSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'attribute-whitespace';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
				input { width: 70mm; font-family: courier; }
			</style>
			<h1>Spaces inside an attribute value</h1>

			<p class="caption">Text fields, each showing its value with the gaps as written: one, three and six spaces.</p>
			<p><input type="text" value="one space"></p>
			<p><input type="text" value="three   spaces"></p>
			<p><input type="text" value="six      spaces"></p>

			<p class="caption">A text field holding a total aligned with spaces: "Total:" and "42" with five spaces between them.</p>
			<p><input type="text" value="Total:     42"></p>

			<p class="caption">Text between tags written with runs of spaces, still drawn with one space between each word.</p>
			<p>collapsed     to     single     spaces</p>

			<p class="caption">A pre block keeping its runs of spaces.</p>
			<pre>kept     as     written</pre>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
