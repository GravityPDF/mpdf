<?php

namespace Snapshots;

/**
 * An element nested inside another of the same name ends at its own end tag, not the outer one's: an SVG inside an
 * inline SVG, and a table inside a cell of a table with a caption
 *
 * @group snapshot
 */
class NestedSameNameElementSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nested-same-name-element';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 6mm 0 1mm 0; }
				p { margin: 0; }
				td { padding: 2mm; }
			</style>
			<h1>Elements nested inside another of the same name</h1>

			<p class="caption">An SVG holding another SVG: a grey square with a blue square in its top left corner and a red one in its bottom right, then "After".</p>
			<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">
				<rect width="80" height="80" fill="#cccccc"/>
				<svg width="40" height="40"><rect width="40" height="40" fill="#3366cc"/></svg>
				<rect x="40" y="40" width="40" height="40" fill="red"/>
			</svg>
			<p>After</p>

			<p class="caption">A table with a table in its first cell and its caption at the bottom: "Inner" and "Outer" side by side in a bordered table, "The caption" below the whole table.</p>
			<table border="1"><caption style="caption-side: bottom">The caption</caption>
				<tr><td><table border="1"><tr><td>Inner</td></tr></table></td><td>Outer</td></tr>
			</table>

			<p class="caption">A table with a captioned table in its first cell: "Inner" with "The inner caption" above it, beside "Outer".</p>
			<table border="1">
				<tr><td><table border="1"><caption>The inner caption</caption><tr><td>Inner</td></tr></table></td><td>Outer</td></tr>
			</table>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
