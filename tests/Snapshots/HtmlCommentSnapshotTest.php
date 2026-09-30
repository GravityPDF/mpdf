<?php

namespace Snapshots;

/**
 * HTML comments removed with nothing left in their place, as a browser removes them, and the mPDF comment markers
 * removed with their content kept.
 *
 * @group snapshot
 */
class HtmlCommentSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'html-comment';
	}

	/**
	 * Words split by a comment, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$html = '
		<style>
			body { font-size: 11pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 5mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 1mm 0; }
			pre { margin: 1mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 1mm 3mm; }
		</style>

		<h1>HTML comments</h1>

		<h2>Between two words</h2>
		<p class="caption">Reads "Bookkeeper", with no space.</p>
		<div class="case"><p>Book<!-- a comment -->keeper</p></div>

		<h2>Between inline elements</h2>
		<p class="caption">Reads "Bookkeeper", with "Book" in bold and "keeper" in italics, and no space between them.</p>
		<div class="case"><p><b>Book</b><!-- a comment --><i>keeper</i></p></div>

		<h2>In a table cell</h2>
		<p class="caption">The cell reads "Bookkeeper", with no space.</p>
		<table><tr><td>Book<!-- a comment -->keeper</td></tr></table>

		<h2>In preformatted text</h2>
		<p class="caption">Three lines: "Bookkeeper" with no space, an empty line, and "keeper".</p>
		<div class="case"><pre>Book<!-- a comment -->keeper
<!-- a comment on its own line -->
keeper</pre></div>

		<h2>A conditional comment</h2>
		<p class="caption">Reads "Bookkeeper", with no space and no "hidden".</p>
		<div class="case"><p>Book<!--[if mso]>hidden<![endif]-->keeper</p></div>

		<h2>A comment marker in a script</h2>
		<p class="caption">Reads "Bookkeeper", with no space and none of the script.</p>
		<script>var marker = "<!--";</script>
		<div class="case"><p>Book<!-- a comment -->keeper</p></div>

		<h2>mPDF content</h2>
		<p class="caption">Reads "Shown only by mPDF, and Bookkeeper": the markers go and their content stays, and the comment inside it leaves no space.</p>
		<div class="case"><!--mpdf <p>Shown only by mPDF, and Book<!-- a comment -->keeper</p> mpdf--></div>
		';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
