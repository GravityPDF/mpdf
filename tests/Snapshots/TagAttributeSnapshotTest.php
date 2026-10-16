<?php

namespace Snapshots;

/**
 * The page header, page footer, body and meta tags read their attributes by name
 *
 * @group snapshot
 */
class TagAttributeSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'tag-attribute';
	}

	/**
	 * A document whose header, footer, body and meta tags each carry a decoy attribute, with captions saying what
	 * should be drawn
	 */
	public function generatePdf()
	{
		$html = '<html><head>
			<title>Set a=1 first</title>
			<meta name="keywords" content="set x=1 first">
			<meta content=\'J "Q" Public\' name=\'author\'>
			<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 4mm 0 1mm 0; }
				p { margin: 0; }
				p:lang(fr) { color: #ff0000; }
				.band { background: #ccffcc; padding: 2mm; }
			</style>
		</head>
		<body data-style="color: #ff0000; background: #ffcccc" style="color: #008000" data-dir="rtl" data-lang="fr">
			<htmlpageheader data-name="decoy" name="main"><div class="band">The page header named "main"</div></htmlpageheader>
			<htmlpagefooter data-name="decoy" name=main><div class="band">The page footer named "main"</div></htmlpagefooter>
			<sethtmlpageheader name="main" value="on" show-this-page="1" />
			<sethtmlpagefooter name="main" value="on" />
			<h1>Attributes read by name</h1>
			<p class="caption">A header and footer with a data-name before their name: a green band at the top and bottom of the page.</p>
			<p class="caption">A body whose data-style is red on pink, before a style of green: the next line is green on white.</p>
			<p>Green text on white</p>
			<p class="caption">A body with data-dir="rtl" and data-lang="fr", which a p:lang(fr) rule colours red: the next line starts at the left and is green.</p>
			<p>Left-aligned green text</p>
			<p class="caption">The title "Set a=1 first", the keywords "set x=1 first" and the author J "Q" Public, single quoted with the content first, are in the document information.</p>
		</body></html>';

		$this->mpdf = $this->createMpdf(['margin_top' => 25, 'margin_bottom' => 25]);

		$this->mpdf->WriteHTML($html);
	}

}
