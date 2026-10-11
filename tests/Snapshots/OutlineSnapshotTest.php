<?php

namespace Snapshots;

/**
 * CSS outline drawn outside the border box of blocks, table cells, images and inline elements, in each style, with
 * outline-offset and around rounded corners, taking no space. The last block breaks across a page and its outline is
 * left open at the cut.
 *
 * @group snapshot
 */
class OutlineSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'outline';
	}

	/**
	 * Each outlined element under a caption saying what should be drawn around it
	 */
	public function generatePdf()
	{
		$image = __DIR__ . '/../data/img/bayeux2.jpg';

		ob_start();
		?>
		<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 4mm 0 1mm 0; }
			p.caption { margin: 3mm 0 2mm 0; color: #606060; }
			div.box { width: 70mm; padding: 1.5mm; border: 0.5mm solid #3366cc; }
			td { border: 0.5mm solid #3366cc; padding: 1.5mm; }
		</style>

		<h1>outline</h1>

		<h2>Blocks</h2>
		<p class="caption">solid 1mm red: a red band touching the outside of the blue border all round.</p>
		<div class="box" style="outline: 1mm solid #cc0000">solid</div>
		<p class="caption">outline-offset: 2mm: the red band 2mm out from the blue border, white between them.</p>
		<div class="box" style="outline: 1mm solid #cc0000; outline-offset: 2mm">offset</div>
		<p class="caption">outline-offset: -3mm on a grey block: the red band inside the box, over the grey and the blue border.</p>
		<div class="box" style="outline: 1mm solid #cc0000; outline-offset: -3mm; background: #dddddd; padding: 4mm">negative offset</div>
		<p class="caption">border-radius: 3mm: the red band rounds its corners with the blue border, its curves wider by the offset.</p>
		<div class="box" style="outline: 1mm solid #cc0000; outline-offset: 1mm; border-radius: 3mm">rounded</div>
		<p class="caption">No outline-color, on purple text: a purple band.</p>
		<div class="box" style="color: #9900cc; outline: 1mm solid">currentColor</div>
		<p class="caption">dashed and dotted: red dashes, then round red dots, all round.</p>
		<div class="box" style="outline: 1mm dashed #cc0000">dashed</div>
		<div class="box" style="outline: 1mm dotted #cc0000; margin-top: 4mm">dotted</div>
		<p class="caption">double 2.4mm: two thin red bands with white between them.</p>
		<div class="box" style="outline: 2.4mm double #cc0000">double</div>
		<p class="caption">groove, ridge, inset and outset 3mm: two tones of blue. Groove is dark outside and light inside along the top and left, the other way round along the bottom and right; ridge is its reverse. Inset is dark along the top and left; outset is dark along the bottom and right.</p>
		<div class="box" style="outline: 3mm groove #3366cc">groove</div>
		<div class="box" style="outline: 3mm ridge #3366cc; margin-top: 9mm">ridge</div>
		<div class="box" style="outline: 3mm inset #3366cc; margin-top: 9mm">inset</div>
		<div class="box" style="outline: 3mm outset #3366cc; margin-top: 9mm">outset</div>

		<h2>Across a page</h2>
		<p class="caption">A block that breaks onto the next page: the red band is open at the foot of this page and at the head of the next.</p>
		<div class="box" style="outline: 1mm solid #cc0000"><?php echo str_repeat('A line of the block that breaks across the page.<br>', 30); ?></div>

		<h2>Table cells</h2>
		<p class="caption">border-collapse: separate: the middle cell has a red band round its blue border, inside the spacing.</p>
		<table style="border-collapse: separate; border-spacing: 3mm"><tr>
			<td>one</td><td style="outline: 1mm solid #cc0000">outlined</td><td>three</td>
		</tr></table>
		<p class="caption">border-collapse: collapse: the middle cell's red band lies over the shared blue borders.</p>
		<table style="border-collapse: collapse"><tr>
			<td>one</td><td style="outline: 1mm solid #cc0000">outlined</td><td>three</td>
		</tr></table>

		<h2>Images</h2>
		<p class="caption">A red band 1mm out from each picture; the second has a rounded blue border, and the band rounds with it.</p>
		<p><img src="<?php echo $image; ?>" style="width: 40mm; outline: 1mm solid #cc0000; outline-offset: 1mm">
			&nbsp; &nbsp;
			<img src="<?php echo $image; ?>" style="width: 40mm; border: 0.5mm solid #3366cc; border-radius: 3mm; outline: 1mm solid #cc0000; outline-offset: 1mm"></p>

		<h2>Inline elements</h2>
		<p class="caption">A red box round the span, over its yellow background and blue border, with the bold word inside the same box.</p>
		<p>Before <span style="outline: 0.4mm solid #cc0000; outline-offset: 0.4mm; border: 0.3mm solid #3366cc; background: #ffff66">outlined <b>bold</b> span</span> after.</p>
		<p class="caption">A span that wraps over three lines: open on the right of the first line, open both ends on the second, open on the left of the third.</p>
		<p style="width: 70mm">Start: <span style="outline: 0.4mm solid #cc0000">this outlined span goes on long enough to wrap onto a second line, and then on further still, past the end of that line, onto a third one</span> end.</p>
		<p class="caption">Two spans side by side, outlined alike: two closed boxes.</p>
		<p><span style="outline: 0.4mm solid #cc0000">first</span><span style="outline: 0.4mm solid #cc0000">second</span></p>

		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
