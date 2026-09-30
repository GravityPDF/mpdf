<?php

namespace Snapshots;

/**
 * The border and background shorthands reset the longhands they do not name, and a longhand after the shorthand
 * still applies
 *
 * @group snapshot
 */
class ShorthandResetsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'shorthand-resets';
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
			div.box { width: 60mm; height: 20mm; margin-bottom: 2mm; }
			div.image { height: 36mm; }
			td { width: 30mm; height: 12mm; }
			td.image { height: 36mm; }

			div.border-reset { border-top-color: #cc0000; }
			div.border-reset { border: 1mm solid #0000cc; }
			div.border-after { border: 1mm solid #0000cc; border-top-color: #cc0000; }
			div.border-radius { border-radius: 4mm; }
			div.border-radius { border: 1mm solid #0000cc; }
			span.border-reset { border-top-color: #cc0000; }
			span.border-reset { border: 0.5mm solid #0000cc; }
			td.border-reset { border-top-color: #cc0000; }
			td.border-reset { border: 1mm solid #0000cc; }
			td.border-after { border: 1mm solid #0000cc; }
			td.border-after { border-top-color: #cc0000; }

			div.background-reset { background-repeat: no-repeat; background-position: right bottom; background-size: 25%; }
			div.background-reset { background: url(img/bg.jpg); }
			div.background-after { background: #ccccff url(img/bg.jpg); background-repeat: no-repeat; background-position: right bottom; }
			div.background-resize { background-image-resize: 6; background: url(img/bg.jpg) no-repeat; }
			td.background-reset { background-repeat: no-repeat; background-position: right bottom; }
			td.background-reset { background: url(img/bg.jpg); }
		</style>

		<h1>Shorthands reset the longhands they do not name</h1>

		<h2>border after a border-top-color</h2>
		<p class="caption">border-top-color: #cc0000, then border: 1mm solid #0000cc in a later rule. All four sides blue.</p>
		<div class="box border-reset"></div>
		<p class="caption">An inline element, with a 0.5mm border. All four sides blue.</p>
		<p>Some <span class="border-reset">words</span> in a line.</p>
		<p class="caption">A table cell. All four sides blue.</p>
		<table><tr><td class="border-reset"></td></tr></table>

		<h2>border-top-color after a border</h2>
		<p class="caption">border: 1mm solid #0000cc; border-top-color: #cc0000. The top red, the other sides blue.</p>
		<div class="box border-after"></div>
		<p class="caption">A table cell, the colour in a later rule. The top red, the other sides blue.</p>
		<table><tr><td class="border-after"></td></tr></table>

		<h2>border keeps border-radius</h2>
		<p class="caption">border-radius: 4mm, then border: 1mm solid #0000cc in a later rule. A blue border with round corners.</p>
		<div class="box border-radius"></div>

		<pagebreak />

		<h2>background after its longhands</h2>
		<p class="caption">background-repeat: no-repeat; background-position: right bottom; background-size: 25%, then background: url(bg.jpg) in a later rule. The image at its natural size, tiled from the top left over the whole box.</p>
		<div class="box background-reset image"></div>
		<p class="caption">A table cell, with the repeat and position in the earlier rule. The image tiled from the top left.</p>
		<table><tr><td class="background-reset image"></td></tr></table>

		<h2>background longhands after a background</h2>
		<p class="caption">background: #ccccff url(bg.jpg); background-repeat: no-repeat; background-position: right bottom. The image once, in the bottom right corner of a light blue box.</p>
		<div class="box background-after image"></div>

		<h2>background keeps mPDF's own background properties</h2>
		<p class="caption">background-image-resize: 6; background: url(bg.jpg) no-repeat. The image stretched to fill the box.</p>
		<div class="box background-resize image"></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
