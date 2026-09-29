<?php

namespace Snapshots;

/**
 * The background shorthand with its parts in any order, a size after the position, and dropped when a part is none
 * of its parts
 *
 * @group snapshot
 */
class BackgroundShorthandSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'background-shorthand';
	}

	/**
	 * One case per form, under a caption saying what should be seen
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
			div.box { width: 60mm; height: 25mm; border: 0.2mm solid #999999; margin-bottom: 2mm; }
			div.kept { background: #99ff99; }
			div.kept.bogus { background: url(img/bg.jpg) bogus; }
		</style>

		<h1>Background shorthand</h1>

		<h2>A colour after the image</h2>
		<p class="caption">background: url(bg.jpg) no-repeat #99ff99. The image at the top left, once, on a light green box.</p>
		<div class="box" style="background: url(img/bg.jpg) no-repeat #99ff99"></div>

		<h2>A size after the position</h2>
		<p class="caption">background: url(bg.jpg) no-repeat center / cover. The image fills the whole box, cut at its top and bottom.</p>
		<div class="box" style="background: url(img/bg.jpg) no-repeat center / cover"></div>
		<p class="caption">background: #ffcc99 right bottom / 25% url(bg.jpg) no-repeat. A small image, a quarter of the box wide, in the bottom right corner of an orange box.</p>
		<div class="box" style="background: #ffcc99 right bottom / 25% url(img/bg.jpg) no-repeat"></div>

		<h2>Origin and clip</h2>
		<p class="caption">background: content-box #ffcc99 url(bg.jpg) no-repeat, on a box with 5mm padding. The orange and the image start 5mm inside the grey border, leaving a white frame.</p>
		<div class="box" style="padding: 5mm; background: content-box #ffcc99 url(img/bg.jpg) no-repeat"></div>

		<h2>Two layers</h2>
		<p class="caption">background: url(bg.jpg) no-repeat, #ccccff. mPDF draws only the first layer, over the colour of the last: the image at the top left, once, on a light blue box.</p>
		<div class="box" style="background: url(img/bg.jpg) no-repeat, #ccccff"></div>

		<h2>A background that is not a background is dropped</h2>
		<p class="caption">background: url(bg.jpg) bogus is dropped, so the light green of the earlier rule stays, with no image.</p>
		<div class="box kept bogus"></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
