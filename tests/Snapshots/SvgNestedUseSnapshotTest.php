<?php

namespace Snapshots;

/**
 * An SVG <use> of an element holding others of its own name copies the whole of it
 *
 * @group snapshot
 */
class SvgNestedUseSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'svg-nested-use';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$open = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="300" height="50">';
		$red = '<rect width="40" height="40" fill="#cc0000"/>';
		$blue = '<rect x="60" width="40" height="40" fill="#0000cc"/>';

		$html = '<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
			</style>
			<h1>SVG &lt;use&gt; of a &lt;g&gt; holding another &lt;g&gt;</h1>

			<p class="caption">A &lt;use&gt; of a &lt;g&gt; holding a &lt;g&gt;: a red square and a blue square beside it.</p>
			' . $open . '<defs><g id="a"><g>' . $red . '</g>' . $blue . '</g></defs><use xlink:href="#a"/></svg>

			<p class="caption">A &lt;use&gt; of a &lt;g&gt; holding a &lt;g&gt; two levels deep, with x="100": a red square and a blue square beside it, further right than above.</p>
			' . $open . '<defs><g id="b"><g><g>' . $red . '</g></g>' . $blue . '</g></defs><use xlink:href="#b" x="100"/></svg>

			<p class="caption">A &lt;use&gt; of a &lt;g&gt; holding a &lt;g&gt;, then another &lt;g&gt; after the &lt;use&gt;: a red and a blue square, and a green square at the right.</p>
			' . $open . '<defs><g id="c"><g>' . $red . '</g>' . $blue . '</g></defs><use xlink:href="#c"/>'
			. '<g><rect x="200" width="40" height="40" fill="#009900"/></g></svg>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
