<?php

namespace Snapshots;

/**
 * @media blocks and media attributes on a portrait A4 page, then a landscape one, written under a value of cssMode
 *
 * @group snapshot
 */
abstract class MediaQuerySnapshot extends Snapshot
{

	/**
	 * @return string A CssMode value
	 */
	abstract protected function mode();

	/**
	 * @return string[] The caption over each case, saying what should be seen in this mode, keyed by types,
	 *                  orientation, width, unknown and landscape
	 */
	abstract protected function captions();

	/**
	 * Paragraphs each styled by an @media block, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$caption = $this->captions();

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
		<p class="caption"><?php echo $caption['types'] ?></p>
		<div class="case">
			<p class="print">@media print</p>
			<p class="not-screen">@media not screen</p>
			<p class="not-print">@media not print</p>
			<p class="screen">@media screen</p>
		</div>

		<h2>Orientation</h2>
		<p class="caption"><?php echo $caption['orientation'] ?></p>
		<div class="case">
			<p class="portrait">@media print and (orientation: portrait)</p>
			<p class="landscape">@media print and (orientation: landscape)</p>
		</div>

		<h2>Width</h2>
		<p class="caption"><?php echo $caption['width'] ?></p>
		<div class="case">
			<p class="min-768">@media (min-width: 768px)</p>
			<p class="range">@media (700px &lt;= width &lt;= 800px)</p>
			<p class="max-768">@media (max-width: 768px)</p>
			<p class="min-1000">@media (min-width: 1000px)</p>
		</div>

		<h2>Unknown features and media attributes</h2>
		<p class="caption"><?php echo $caption['unknown'] ?></p>
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
		<p class="caption"><?php echo $caption['landscape'] ?></p>
		<div class="case">
			<p class="landscape-page">@media print and (orientation: landscape)</p>
			<p class="wide-page">@media (min-width: 1000px)</p>
			<p class="portrait-page">@media print and (orientation: portrait)</p>
		</div>
		<?php
		$landscape = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->mode()]);

		$this->mpdf->WriteHTML($portrait);
		$this->mpdf->AddPage('L');
		$this->mpdf->WriteHTML($landscape);
	}

}
