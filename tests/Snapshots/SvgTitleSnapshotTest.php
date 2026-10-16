<?php

namespace Snapshots;

/**
 * An inline SVG's <title> names the image, not the document: a document with no title of its own has no /Title in
 * its Info dictionary, which the object text shows
 *
 * @group snapshot
 */
class SvgTitleSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'svg-title';
	}

	/**
	 * An untitled document holding an SVG with a title, under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<html><head><style>p.caption { font-size: 8pt; color: #666666; }</style></head><body>
			<p class="caption">An inline SVG titled "The chart\'s title": a blue square, then "After the chart". The document has no title of its own, so the PDF has none.</p>
			<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><title>The chart\'s title</title><rect width="40" height="40" fill="#3366cc"/></svg>
			<p>After the chart</p>
		</body></html>';

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}

}
