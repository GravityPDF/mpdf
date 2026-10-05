<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * line-height on inline elements in cssMode standard: each inline element's box is as high as its line-height, and a
 * line grows to hold the tallest box on it, while the block's own line height stays the least a line can be.
 *
 * @group snapshot
 */
class InlineLineHeightSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inline-line-height';
	}

	/**
	 * Paragraphs of two lines, the first holding an inline element with a line-height, each under a caption saying
	 * what should be seen
	 */
	public function generatePdf()
	{
		$image = __DIR__ . '/../data/img/exif-orientation-none.jpg';

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 5mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			p.lines { margin: 0 0 3mm 0; font-size: 12pt; background-color: #d6eaf8; }
			span.mark { color: #b03a2e; }
			table { border-collapse: collapse; margin: 0 0 3mm 0; }
			td { border: 0.2mm solid #999999; padding: 0 2mm; font-size: 12pt; background-color: #d6eaf8; }
		</style>

		<h1>line-height on inline elements</h1>

		<h2>Values</h2>
		<p class="caption">A normal first line, for comparison: each line is about 5.6mm high.</p>
		<p class="lines">First line<br>Second line</p>

		<p class="caption">line-height: 20mm on the red span: the first line is 20mm high, with the text in its middle; the second line is normal.</p>
		<p class="lines">First <span class="mark" style="line-height: 20mm">line</span><br>Second line</p>

		<p class="caption">line-height: 3 on the red span: the first line is three times the 12pt font, 36pt (12.7mm), high.</p>
		<p class="lines">First <span class="mark" style="line-height: 3">line</span><br>Second line</p>

		<p class="caption">line-height: 250% on the red span: the first line is 30pt (10.6mm) high.</p>
		<p class="lines">First <span class="mark" style="line-height: 250%">line</span><br>Second line</p>

		<p class="caption">line-height: normal on the red span, in a paragraph with line-height: 0.5: the first line is normal, the second is half the font size, so its text runs over the bottom of the blue background.</p>
		<p class="lines" style="line-height: 0.5">First <span class="mark" style="line-height: normal">line</span><br>Second line</p>

		<h2>Taller and shorter than the block</h2>
		<p class="caption">A paragraph with line-height: 10mm and a red span with 20mm: the first line is 20mm high.</p>
		<p class="lines" style="line-height: 10mm">First <span class="mark" style="line-height: 20mm">line</span><br>Second line</p>

		<p class="caption">The same paragraph with a red span of 2mm, alone on its line: the first line stays 10mm high, like the second.</p>
		<p class="lines" style="line-height: 10mm"><span class="mark" style="line-height: 2mm">First line</span><br>Second line</p>

		<p class="caption">The same with line-height: 0 on the red span: both lines are 10mm high.</p>
		<p class="lines" style="line-height: 10mm"><span class="mark" style="line-height: 0">First line</span><br>Second line</p>

		<h2 style="page-break-before: always">Inheritance and mixed sizes</h2>
		<p class="caption">line-height: 2 on a span holding a red 20pt span: the number is inherited as a number, so the first line is 40pt (14.1mm) high.</p>
		<p class="lines">First <span style="line-height: 2"><span class="mark" style="font-size: 20pt">line</span></span><br>Second line</p>

		<p class="caption">line-height: 2em on the same span: the length is worked out at the outer span's 12pt and inherited as 24pt, so the first line is 24pt (8.5mm) high.</p>
		<p class="lines">First <span style="line-height: 2em"><span class="mark" style="font-size: 20pt">line</span></span><br>Second line</p>

		<p class="caption">A red 30pt span with line-height: 1: the first line is 30pt (10.6mm) high, shorter than a normal 30pt line, so the large text reaches the top of the blue background.</p>
		<p class="lines">First <span class="mark" style="font-size: 30pt; line-height: 1">line</span><br>Second line</p>

		<p class="caption">A 20pt span, a red span with line-height: 20mm and a small span on one line: the first line is 20mm high.</p>
		<p class="lines">First <span style="font-size: 20pt">big</span> <span class="mark" style="line-height: 20mm">tall</span> <small>small</small><br>Second line</p>

		<h2>vertical-align</h2>
		<p class="caption">The red span has line-height: 10mm and vertical-align: 50%, so it is raised 5mm above the baseline, and the first line grows to hold it.</p>
		<p class="lines">First <span class="mark" style="line-height: 10mm; vertical-align: 50%">raised</span><br>Second line</p>

		<h2>An image</h2>
		<p class="caption">A small image in a span with line-height: 20mm: the first line is 20mm high, and the image sits on the baseline.</p>
		<p class="lines">First <span style="line-height: 20mm"><img src="<?php echo $image; ?>" style="width: 4mm; height: 4mm"></span><br>Second line</p>

		<h2>Table cell</h2>
		<p class="caption">line-height: 20mm on the red span in the first cell: its first line is 20mm high, and the whole row grows with it.</p>
		<table>
			<tr>
				<td>First <span class="mark" style="line-height: 20mm">line</span><br>Second line</td>
				<td>Other cell</td>
			</tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
