<?php

namespace Snapshots;

/**
 * A <title> is not drawn wherever it is written: in the <head>, between blocks of the <body>, in a table cell, inside
 * a line and in a page header
 *
 * @group snapshot
 */
class TitleNotDrawnSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'title-not-drawn';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<html><head><title>The document title</title><style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
				td { border: 1px solid #000000; padding: 1mm; }
			</style></head><body>
			<h1>A &lt;title&gt; is never drawn</h1>

			<p class="caption">The page header holds a &lt;title&gt; after "Header": it reads "Header" and nothing more.</p>

			<p class="caption">A &lt;title&gt; between two paragraphs: "Before", then "After" on the next line, with no line between them.</p>
			<p>Before</p><title>Hidden between blocks</title><p>After</p>

			<p class="caption">A &lt;title&gt; in a table cell: one cell reading "Cell".</p>
			<table><tr><td>Cell<title>Hidden in a cell</title></td></tr></table>

			<p class="caption">A &lt;title&gt; holding bold markup inside a line: "Before after" on one line, nothing bold.</p>
			<p>Before <span><title>Hidden <b>bold</b></title></span>after</p>
		</body></html>';

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetHTMLHeader('<div style="font-size: 9pt">Header<title>Hidden in the header</title></div>');
		$this->mpdf->WriteHTML($html);
	}

}
