<?php

namespace Snapshots;

/**
 * Inline styles with !important drawn as they are without it: the flag is stripped rather than counted as one of a
 * shorthand's values.
 *
 * @group snapshot
 */
class InlineImportantSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inline-important';
	}

	/**
	 * Pairs of blocks, the first styled inline with !important and the second with the same declarations without it,
	 * under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$image = __DIR__ . '/../data/img/bg.jpg';

		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 4mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.pair { background-color: #eeeeee; margin-bottom: 1mm; }
		</style>

		<h1>!important in an inline style</h1>

		<h2>A border shorthand</h2>
		<p class="caption">Both blocks have a red border 1mm wide all round.</p>
		<div style="border: 1mm solid #ff0000 !important; margin-bottom: 2mm">With !important</div>
		<div style="border: 1mm solid #ff0000; margin-bottom: 2mm">Without</div>

		<h2>A two-value padding shorthand</h2>
		<p class="caption">Both yellow blocks have 4mm of padding above and below the text and 15mm either side.</p>
		<div style="background-color: #f9e79f; padding: 4mm 15mm !important; margin-bottom: 2mm">With !important</div>
		<div style="background-color: #f9e79f; padding: 4mm 15mm; margin-bottom: 2mm">Without</div>

		<h2>A four-value margin shorthand</h2>
		<p class="caption">Both blue blocks are inset 10mm on the left and 30mm on the right of the grey band.</p>
		<div class="pair"><div style="background-color: #aed6f1; margin: 2mm 30mm 2mm 10mm !important">With !important</div></div>
		<div class="pair"><div style="background-color: #aed6f1; margin: 2mm 30mm 2mm 10mm">Without</div></div>

		<h2>A font size and a colour</h2>
		<p class="caption">Both lines are green and set at 20pt.</p>
		<p style="font-size: 20pt !important; color: #0a7d32 !important; margin: 1mm 0">With !important</p>
		<p style="font-size: 20pt; color: #0a7d32; margin: 1mm 0">Without</p>

		<h2>An image height and vertical alignment</h2>
		<p class="caption">Both images are 12mm wide and 4mm tall, centred on the line.</p>
		<p style="margin: 1mm 0">With !important <img src="<?php echo $image; ?>" style="width: 12mm; height: 4mm !important; vertical-align: middle !important"> after</p>
		<p style="margin: 1mm 0">Without <img src="<?php echo $image; ?>" style="width: 12mm; height: 4mm; vertical-align: middle"> after</p>

		<h2>The flag in upper case, with no space before it</h2>
		<p class="caption">Both blocks have a dashed purple border 0.5mm wide and 3mm of padding all round.</p>
		<div style="border: 0.5mm dashed #8e44ad!IMPORTANT; padding: 3mm  !Important ; margin-bottom: 2mm">With !IMPORTANT</div>
		<div style="border: 0.5mm dashed #8e44ad; padding: 3mm; margin-bottom: 2mm">Without</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
