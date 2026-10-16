<?php

namespace Snapshots;

/**
 * Several <style> blocks and <link> tags, read for the print medium: those for other media are left out, HTML comment
 * markers in a <style> block keep its CSS, a <style> in an HTML comment is not read, and one in an <!--mpdf ... mpdf-->
 * comment is
 *
 * @group snapshot
 */
class StyleAndLinkBlocksSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'style-and-link-blocks';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<html><head>
			<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
			</style>
			<style media="print">.print-style { color: #0000cc; }</style>
			<style media="screen">.screen-style { color: #cc0000; }</style>
			<style title="a > b" media="print and (orientation: portrait)">.portrait { color: #008800; }</style>
			<link rel="stylesheet" media="print" href="data:text/css,.print-link { color: %23cc6600; }">
			<link media="screen" href="data:text/css,.screen-link { color: %23cc0000; }" rel="stylesheet">
			<style><!--
				.marked { color: #880088; }
				/* .commented { color: #cc0000; } */
			--></style>
			<!-- <style>.hidden { color: #cc0000; }</style> -->
			<!--mpdf <style>.mpdf-comment { color: #008888; }</style> mpdf-->
			<style>
				@import url("data:text/css,.imported-print{color:%23000088}") print;
				@import url("data:text/css,.imported-screen{color:%23cc0000}") screen;
			</style>
		</head><body>
			<h1>&lt;style&gt; and &lt;link&gt; read for print</h1>

			<p class="caption">A &lt;style media="print"&gt;: the next line is blue.</p>
			<p class="print-style">Blue text</p>

			<p class="caption">A &lt;style media="screen"&gt;: the next line is black.</p>
			<p class="screen-style">Black text</p>

			<p class="caption">A &lt;style&gt; with a "&gt;" in its title, for print in portrait: the next line is green.</p>
			<p class="portrait">Green text</p>

			<p class="caption">A &lt;link media="print"&gt;: the next line is orange.</p>
			<p class="print-link">Orange text</p>

			<p class="caption">A &lt;link media="screen"&gt;, with rel after href: the next line is black.</p>
			<p class="screen-link">Black text</p>

			<p class="caption">A &lt;style&gt; wrapped in HTML comment markers: the next line is purple.</p>
			<p class="marked">Purple text</p>

			<p class="caption">A rule in a CSS comment in that &lt;style&gt;: the next line is black.</p>
			<p class="commented">Black text</p>

			<p class="caption">A &lt;style&gt; in an HTML comment: the next line is black.</p>
			<p class="hidden">Black text</p>

			<p class="caption">A &lt;style&gt; in an &lt;!--mpdf ... mpdf--&gt; comment: the next line is teal.</p>
			<p class="mpdf-comment">Teal text</p>

			<p class="caption">An @import for print: the next line is navy.</p>
			<p class="imported-print">Navy text</p>

			<p class="caption">An @import for screen: the next line is black.</p>
			<p class="imported-screen">Black text</p>
		</body></html>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
