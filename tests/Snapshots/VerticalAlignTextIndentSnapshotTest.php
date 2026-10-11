<?php

namespace Snapshots;

/**
 * vertical-align keywords on inline text, and text-indent in table cells and with hanging and each-line, in cssMode
 * standard
 *
 * @group snapshot
 */
class VerticalAlignTextIndentSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'vertical-align-text-indent';
	}

	/**
	 * Lines and paragraphs, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$image = __DIR__ . '/../data/img/exif-orientation-none.jpg';

		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 5mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			p.line { margin: 0 0 3mm 0; font-size: 20pt; background-color: #d6eaf8; }
			span.small { font-size: 7pt; color: #b03a2e; }
			div.narrow { width: 45mm; margin: 0 0 3mm 0; font-size: 10pt; background-color: #d6eaf8; }
			table { border-collapse: collapse; margin: 0 0 3mm 0; }
			td { border: 0.2mm solid #999999; padding: 1mm; font-size: 10pt; background-color: #d6eaf8; width: 50mm; }
		</style>

		<h1>vertical-align on inline text, and text-indent</h1>

		<h2>vertical-align keywords on 7pt red text in a 20pt line</h2>
		<p class="caption">baseline: the red text sits on the line's baseline, as the black text does.</p>
		<p class="line">Hxg <span class="small">baseline</span> Hxg</p>

		<p class="caption">middle: the middle of the red text is level with the middle of the black lowercase x.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: middle">middle</span> Hxg</p>

		<p class="caption">text-top: the top of the red text's line is level with the top of the black font's box, just above the H.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: text-top">text-top</span> Hxg</p>

		<p class="caption">text-bottom: the bottom of the red text's line is level with the bottom of the black font's box, just below the g.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: text-bottom">text-bottom</span> Hxg</p>

		<p class="caption">top: the red text is at the top of the blue line.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: top">top</span> Hxg</p>

		<p class="caption">bottom: the red text is at the bottom of the blue line.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: bottom">bottom</span> Hxg</p>

		<p class="caption">top beside a 20mm image: the red text is at the top of the image, and the black text on the baseline at its foot.</p>
		<p class="line">Hxg <img src="<?php echo $image; ?>" style="height: 20mm"> <span class="small" style="vertical-align: top">top</span> Hxg</p>

		<p class="caption">middle on a span with a yellow background and an underline: the background and the underline move up with the red text.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: middle; background-color: #f9e79f; text-decoration: underline">middle</span> Hxg</p>

		<p class="caption">middle on a span holding a 4mm image: the image moves up with the red text, its foot on the red text's baseline.</p>
		<p class="line">Hxg <span class="small" style="vertical-align: middle">middle <img src="<?php echo $image; ?>" style="height: 4mm"></span> Hxg</p>

		<h2 style="page-break-before: always">text-indent</h2>
		<p class="caption">10mm: only the first line is indented.</p>
		<div class="narrow" style="text-indent: 10mm">The first line of this paragraph is indented and the lines after it are not.</div>

		<p class="caption">10mm hanging: every line but the first is indented.</p>
		<div class="narrow" style="text-indent: 10mm hanging">The first line of this paragraph is not indented and the lines after it are.</div>

		<p class="caption">-10mm hanging, with 10mm of padding: the first line starts at the padding's inner edge and the lines after it 10mm further left, in the padding.</p>
		<div class="narrow" style="text-indent: -10mm hanging; padding-left: 10mm">The first line of this paragraph is not moved and the lines after it are moved left.</div>

		<p class="caption">10mm each-line: the first line, and the first line after the break, are indented; the lines they wrap onto are not.</p>
		<div class="narrow" style="text-indent: 10mm each-line">The first line is indented and the next is not.<br>The line after the break is indented again.</div>

		<p class="caption">20mm on a table cell: the cell's first line is indented.</p>
		<table><tr><td style="text-indent: 20mm">The first line of this cell is indented and the lines after it are not.</td></tr></table>

		<p class="caption">20mm on a right-to-left table cell: the cell's first line is indented from the right.</p>
		<table><tr><td style="text-indent: 20mm; direction: rtl">The first line of this cell is indented and the lines after it are not.</td></tr></table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
