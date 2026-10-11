<?php

namespace Snapshots;

/**
 * A background with several comma-separated layers draws every layer, the first on top and the last at the bottom,
 * each with its own repeat, position and size, over the colour the last layer names
 *
 * @group snapshot
 */
class MultipleBackgroundsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'multiple-backgrounds';
	}

	/**
	 * One box per case on the first page, under a caption saying what should be seen, and a page box with two
	 * layers on the second
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@page layered { background: url(img/bg.jpg) no-repeat center, linear-gradient(#ffffcc, #99ccff); }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 2mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.box { width: 60mm; height: 25mm; border: 0.2mm solid #999999; }
			td.box { width: 60mm; height: 25mm; border: 0.2mm solid #999999; }
			div.page { page: layered; }
		</style>

		<h1>Multiple backgrounds</h1>

		<p class="caption">background: url(bg.jpg) no-repeat, linear-gradient(red, blue). The image once at the top left, over a red to blue gradient.</p>
		<div class="box" style="background: url(img/bg.jpg) no-repeat, linear-gradient(red, blue)"></div>

		<p class="caption">background: linear-gradient(red, blue) 0 0 / 50% 100%, url(bg.jpg). A red to blue gradient over the left half, covering the tiled image, which shows on the right half.</p>
		<div class="box" style="background: linear-gradient(red, blue) 0 0 / 50% 100%, url(img/bg.jpg)"></div>

		<p class="caption">background-image: url(bg.jpg), url(bg.jpg); background-repeat: no-repeat; background-position: left top, right bottom. The image twice, once in the top left corner and once in the bottom right.</p>
		<div class="box" style="background-image: url(img/bg.jpg), url(img/bg.jpg); background-repeat: no-repeat; background-position: left top, right bottom"></div>

		<p class="caption">background: url(bg.jpg) no-repeat, #ccccff. The image once at the top left, on a light blue box: the colour is the last layer's.</p>
		<div class="box" style="background: url(img/bg.jpg) no-repeat, #ccccff"></div>

		<p class="caption">background-image: linear-gradient(to right, red, blue), radial-gradient(yellow, green); background-size: 50% 100%, auto. A red to blue gradient over the left half; the right half shows the rest of a yellow to green radial gradient, yellow at the middle of the box and green at its right edge.</p>
		<div class="box" style="background-image: linear-gradient(to right, red, blue), radial-gradient(yellow, green); background-size: 50% 100%, auto"></div>

		<p class="caption">Three images, background-repeat: no-repeat, repeat-x; background-position: right top, left bottom, left top; background-size: 10mm. A small image once at the top right, a row of them along the bottom, and one at the top left, which takes the first repeat again.</p>
		<div class="box" style="background-image: url(img/bg.jpg), url(img/bg.jpg), url(img/bg.jpg); background-repeat: no-repeat, repeat-x; background-position: right top, left bottom, left top; background-size: 10mm"></div>

		<p class="caption">A table cell with background: url(bg.jpg) no-repeat, linear-gradient(red, blue). The image once at the top left, over a red to blue gradient.</p>
		<table><tr><td class="box" style="background: url(img/bg.jpg) no-repeat, linear-gradient(red, blue)"></td></tr></table>

		<div class="page">
			<p class="caption">This page's @page rule has background: url(bg.jpg) no-repeat center, linear-gradient(#ffffcc, #99ccff). The image once in the middle of the page, over a pale yellow to pale blue gradient.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
