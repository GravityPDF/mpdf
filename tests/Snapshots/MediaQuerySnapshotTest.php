<?php

namespace Snapshots;

/**
 * @media blocks and media attributes matched against the print medium and the page's size and orientation: a
 * portrait A4 page, then a landscape one.
 *
 * @group snapshot
 */
class MediaQuerySnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'media-queries';
	}

	/**
	 * Paragraphs each styled by an @media block, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 4mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 1mm 0; }

			@media print { p.print { color: #0a7d32; font-weight: bold; } }
			@media not screen { p.not-screen { color: #0a7d32; font-weight: bold; } }
			@media not print { p.not-print { color: #ff0000; font-size: 16pt; } }
			@media screen { p.screen { color: #ff0000; font-size: 16pt; } }

			@media print and (orientation: portrait) { p.portrait { color: #0a7d32; font-weight: bold; } }
			@media print and (orientation: landscape) { p.landscape { color: #ff0000; font-size: 16pt; } }

			@media (min-width: 768px) { p.min-768 { color: #0a7d32; font-weight: bold; } }
			@media (700px <= width <= 800px) { p.range { color: #0a7d32; font-weight: bold; } }
			@media (max-width: 768px) { p.max-768 { color: #ff0000; font-size: 16pt; } }
			@media (min-width: 1000px) { p.min-1000 { color: #ff0000; font-size: 16pt; } }

			@media (hover: hover) { p.hover { color: #ff0000; font-size: 16pt; } }
		</style>
		<style media="not print">
			p.attribute { color: #ff0000; font-size: 16pt; }
		</style>

		<h1>Media queries on a portrait A4 page</h1>

		<h2>Media types</h2>
		<p class="caption">print and not screen apply: the first two paragraphs are green and bold. not print and screen do not: the last two are black at the normal size.</p>
		<div class="case">
			<p class="print">@media print</p>
			<p class="not-screen">@media not screen</p>
			<p class="not-print">@media not print</p>
			<p class="screen">@media screen</p>
		</div>

		<h2>Orientation</h2>
		<p class="caption">The page is portrait: the first paragraph is green and bold, the second black at the normal size.</p>
		<div class="case">
			<p class="portrait">@media print and (orientation: portrait)</p>
			<p class="landscape">@media print and (orientation: landscape)</p>
		</div>

		<h2>Width</h2>
		<p class="caption">The page is 210mm, about 794px, wide: the first two paragraphs are green and bold, the last two black at the normal size.</p>
		<div class="case">
			<p class="min-768">@media (min-width: 768px)</p>
			<p class="range">@media (700px &lt;= width &lt;= 800px)</p>
			<p class="max-768">@media (max-width: 768px)</p>
			<p class="min-1000">@media (min-width: 1000px)</p>
		</div>

		<h2>Unknown features and media attributes</h2>
		<p class="caption">A feature mPDF does not know makes its query fail, and a &lt;style media="not print"&gt; block is left out: both paragraphs are black at the normal size.</p>
		<div class="case">
			<p class="hover">@media (hover: hover)</p>
			<p class="attribute">&lt;style media="not print"&gt;</p>
		</div>
		<?php
		$portrait = ob_get_clean();

		ob_start();
		?>
		<style>
			@media print and (orientation: landscape) { p.landscape-page { color: #0a7d32; font-weight: bold; } }
			@media (min-width: 1000px) { p.wide-page { color: #0a7d32; font-weight: bold; } }
			@media print and (orientation: portrait) { p.portrait-page { color: #ff0000; font-size: 16pt; } }
		</style>

		<h1>Media queries on a landscape A4 page</h1>
		<p class="caption">This page's stylesheet is matched against this page, 297mm (about 1123px) wide: the first two paragraphs are green and bold, the third black at the normal size.</p>
		<div class="case">
			<p class="landscape-page">@media print and (orientation: landscape)</p>
			<p class="wide-page">@media (min-width: 1000px)</p>
			<p class="portrait-page">@media print and (orientation: portrait)</p>
		</div>
		<?php
		$landscape = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($portrait);
		$this->mpdf->AddPage('L');
		$this->mpdf->WriteHTML($landscape);
	}

}
