<?php

namespace Snapshots;

/**
 * Blocks with a blurred outer and inset box-shadow that break across pages. Under box-decoration-break: slice, the
 * default, the shadows are cut off at the break; under clone each part of the block on a page is shadowed all round.
 *
 * @group snapshot
 */
class BoxShadowSplitSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'box-shadow-split';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$lines = str_repeat('A line of the block<br>', 14);

		ob_start();
		?>
		<style>
			div.box {
				margin: 6mm 10mm 0 10mm;
				padding: 4mm;
				background-color: #eeeeff;
				border-radius: 4mm;
				box-shadow: 3mm 3mm 5mm #444444, inset 0 0 5mm 1mm #cc0000;
			}
		</style>

		<p>box-decoration-break: slice (the default). The grey shadow and the red inset shadow stop at the foot of this page and start again at the head of the next with no edge, as one box cut in two.</p>
		<div class="box"><?php echo $lines; ?></div>

		<p style="page-break-before: always">box-decoration-break: clone. Each part of the block is shadowed all round: the grey shadow and the red inset shadow are drawn along the foot of this page and the head of the next as well.</p>
		<div class="box" style="box-decoration-break: clone"><?php echo $lines; ?></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['format' => [120, 110], 'margin_top' => 12, 'margin_bottom' => 12]);
		$this->mpdf->WriteHTML($html);
	}
}
