<?php

namespace Snapshots;

/**
 * Background images named in a stylesheet's url() with spaces, whitespace inside the parentheses, or quotes inside
 * the URL
 *
 * @group snapshot
 */
class StylesheetUrlSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'stylesheet-url';
	}

	/**
	 * Boxes with a background image each, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$arrow = "data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e";

		$html = '<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.box { width: 70mm; height: 20mm; border: 0.2mm solid #999999; background-repeat: no-repeat; }
			div.spaced { background-image: url("img/bay eux.jpg"); }
			div.padded { background-image: url(  img/bay%20eux.jpg  ); }
			div.select { width: 60mm; height: 9mm; padding: 0 3mm; line-height: 9mm; background-image: url("' . $arrow . '"); background-position: right center; background-size: contain; }
			div.double { background-image: url(\'' . str_replace("'", '"', $arrow) . '\'); background-size: contain; background-position: center; }
		</style>

		<h1>url() in a stylesheet</h1>

		<p class="caption">url("img/bay eux.jpg"), with a space in the file name: the tapestry fills the box.</p>
		<div class="box spaced"></div>

		<p class="caption">url(  img/bay%20eux.jpg  ), with whitespace inside the parentheses: the same tapestry.</p>
		<div class="box padded"></div>

		<p class="caption">Bootstrap\'s form-select arrow, an SVG data URI with single quotes inside double quotes: a dark chevron at the right of the box.</p>
		<div class="box select">Choose</div>

		<p class="caption">The same SVG with double quotes inside single quotes: a large chevron in the middle of the box.</p>
		<div class="box double"></div>';

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data/');

		$this->mpdf->WriteHTML($html);
	}

}
