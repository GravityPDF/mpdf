<?php

namespace Snapshots;

/**
 * Pages sized by the @page size property: page-size names set the sheet, with or without an orientation, and a page
 * box given as two lengths with no margin keeps the default margins inside the box.
 *
 * @group snapshot
 */
class PageSizeNameSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'page-size-names';
	}

	/**
	 * One page per size, each with a caption saying what should be seen and a bordered block that fills the page area
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@page { size: A5; }
			@page wide { size: A5 landscape; }
			@page usletter { size: letter; margin: 1in; }
			@page box { size: 100mm 120mm; marks: crop; }

			body { font-size: 9pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			div.area { border: 0.3mm solid #1f3a93; background-color: #eaf2fb; padding: 2mm; }
			div.wide { page: wide; }
			div.usletter { page: usletter; }
			div.box { page: box; }
		</style>

		<div class="area">
			<h1>size: A5</h1>
			<p>This sheet is A5 portrait, 148 mm wide and 210 mm tall. The blue box fills the page area inside the default margins, 15 mm from each side.</p>
		</div>

		<div class="wide area">
			<h1>size: A5 landscape</h1>
			<p>This sheet is A5 landscape, 210 mm wide and 148 mm tall. The blue box is 15 mm from each side.</p>
		</div>

		<div class="usletter area">
			<h1>size: letter; margin: 1in</h1>
			<p>This sheet is US letter, 8.5 by 11 inches. The blue box is one inch from each side.</p>
		</div>

		<div class="box area">
			<h1>size: 100mm 120mm</h1>
			<p>A 100 by 120 mm page box, marked by crop marks, centred on the A5 sheet. With no margin given, the blue box is 15 mm inside the page box on each side: 39 mm from the sheet edges.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
