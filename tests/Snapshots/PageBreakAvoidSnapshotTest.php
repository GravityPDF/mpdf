<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class PageBreakAvoidSnapshotTest extends Snapshot
{
	public function getId()
	{
		return 'page-break-avoid';
	}

	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			p.filler { margin: 0 0 2mm 0; }

			table.panel { border-collapse: collapse; width: 100%; margin-bottom: 4mm; }
			table.panel td { padding: 3mm; border: 0.2mm solid #404040; }

			td.red { background-color: #d02020; color: #ffffff; }
			td.blue { background-color: #2040c0; color: #ffffff; }

			div.together { border: 0.4mm dashed #808080; padding: 3mm; }
		</style>

		<h1>mPDF</h1>
		<h2>page-break-inside: avoid</h2>

		<p class="filler">The block below is too tall for what is left of this page, so it moves to the
			next one whole. Its coloured panels move with it, and nothing of them is left behind here.
			The plain table above is what puts the backgrounds on the page in the first place.</p>

		<table class="panel">
			<tr><td>A table before the block, with no background of its own</td></tr>
		</table>

		<?php for ($i = 0; $i < 24; $i++) { ?>
			<p class="filler">Filler line <?= $i + 1 ?>, here to push the block over the page boundary.</p>
		<?php } ?>

		<div class="together" style="page-break-inside: avoid">
			<table class="panel">
				<tr><td class="red">Red panel, inside the block</td></tr>
			</table>

			<table class="panel">
				<tr><td class="blue">Blue panel, inside the block</td></tr>
			</table>

			<?php for ($i = 0; $i < 10; $i++) { ?>
				<p class="filler">The block carries this line too, and all of it stays together.</p>
			<?php } ?>
		</div>

		<p class="filler">After the block.</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = new \Mpdf\Mpdf();
		$this->mpdf->WriteHTML($html);
	}
}
