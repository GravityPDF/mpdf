<?php

namespace Snapshots;

/**
 * A <title> or <meta> written inside a script holds no element: the Info dictionary's /Title and /Author are the
 * document's own, which the object text shows
 *
 * @group snapshot
 */
class MetaTagsInScriptSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'meta-tags-in-script';
	}

	/**
	 * A document with a script writing a <title> before its own and another writing an author <meta> after its own,
	 * under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<html><head>
			<script>document.write(\'<title>From the script</title>\');</script>
			<title>The document\'s title</title>
			<meta name="author" content="The document\'s author">
			<script>document.write(\'<meta name="author" content="From the script">\');</script>
			<style>p.caption { font-size: 8pt; color: #666666; }</style>
		</head><body>
			<p class="caption">One script writes a title before the document\'s own, another an author after it. The PDF\'s title is "The document\'s title" and its author "The document\'s author", not "From the script".</p>
			<p>The page shows only this text.</p>
		</body></html>';

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}

}
