<?php

namespace Snapshots;

/**
 * Groove, ridge, inset and outset borders on blocks, inline elements and images, drawn in two tones lit from the top
 * left as a table cell's are, next to a table cell drawn by the table code for comparison.
 *
 * @group snapshot
 */
class ThreeDBorderSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'three-d-borders';
	}

	/**
	 * Each bordered element under a caption saying which side takes which tone
	 */
	public function generatePdf()
	{
		$image = __DIR__ . '/../data/img/bayeux2.jpg';

		ob_start();
		?>
		<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p.caption { margin: 2mm 0 1.5mm 0; color: #606060; }
			div.box { width: 70mm; padding: 1mm; margin-bottom: 1.5mm; }
		</style>

		<h1>groove, ridge, inset, outset</h1>

		<h2>Blocks</h2>
		<p class="caption">3mm in #3366cc, a dark grey-blue and a pale blue. Groove: dark outside and pale inside along the top and left, the other way round along the bottom and right. Ridge: the reverse. Inset: dark along the top and left, pale along the bottom and right. Outset: the reverse. The tones meet on the diagonals of the corners.</p>
		<div class="box" style="border: 3mm groove #3366cc">groove</div>
		<div class="box" style="border: 3mm ridge #3366cc">ridge</div>
		<div class="box" style="border: 3mm inset #3366cc">inset</div>
		<div class="box" style="border: 3mm outset #3366cc">outset</div>
		<p class="caption">The same groove in a table cell, drawn by the table code: the same two tones on the same sides, its corners overlapping rather than meeting on the diagonals.</p>
		<table style="border-collapse: separate"><tr><td style="border: 3mm groove #3366cc; padding: 1.5mm; width: 70mm">groove cell</td></tr></table>
		<p class="caption">border-radius: 5mm, 4mm groove: both rings follow the rounded corners.</p>
		<div class="box" style="border: 4mm groove #3366cc; border-radius: 5mm">rounded groove</div>
		<p class="caption">Inset in red, 1mm top, 3mm right, 5mm bottom, 2mm left: each side as wide as given, the corners cut on the line between the outer and inner corner.</p>
		<div class="box" style="border-style: inset; border-color: #cc3333; border-width: 1mm 3mm 5mm 2mm">uneven inset</div>
		<p class="caption">Outset in rgba(51, 102, 204, 0.4) on a yellow block: the yellow shows through both tones, the pale one a light yellow and the dark one an olive.</p>
		<div style="background: #ffdd00; padding: 2mm; width: 80mm">
			<div class="box" style="border: 3mm outset rgba(51, 102, 204, 0.4); margin: 0">translucent outset</div>
		</div>
		<p class="caption">A fieldset with a groove border: its legend sits in a gap in the top border.</p>
		<fieldset style="border: 3mm groove #3366cc; width: 70mm"><legend>Legend</legend>fieldset</fieldset>

		<h2>Inline elements</h2>
		<p class="caption">Ridge 1mm round a span on a yellow background, and outset 1mm round a span that wraps: no left side on the second line and no right side at the end of the first.</p>
		<p style="width: 80mm; line-height: 2">Before <span style="border: 1mm ridge #cc6600; background: #ffff99">ridge</span> and <span style="border: 1mm outset #339933">an outset span that goes on long enough to wrap</span> after.</p>

		<h2>Images</h2>
		<p class="caption">A 3mm groove round the first picture and a 3mm outset round the second, rounded 5mm.</p>
		<p><img src="<?php echo $image; ?>" style="width: 30mm; border: 3mm groove #3366cc">
			&nbsp; &nbsp;
			<img src="<?php echo $image; ?>" style="width: 30mm; border: 3mm outset #3366cc; border-radius: 5mm"></p>

		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
