<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class TableBackgroundSnapshotTest extends Snapshot
{
	public function getId()
	{
		return 'table-background';
	}

	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			p.note { margin: 0 0 3mm 0; }

			table.panel { border-collapse: collapse; width: 100%; margin-bottom: 4mm; }
			table.panel td { padding: 3mm; border: 0.2mm solid #404040; }

			table.plain { background: none; }
			table.red { background: rgba(208, 32, 32, 0.35); }
			table.blue { background: rgba(32, 64, 192, 0.35); }

			td.tint { background: rgba(32, 128, 32, 0.35); }
		</style>

		<h1>mPDF</h1>
		<h2>Table backgrounds</h2>

		<p class="note">Each pair below is the same colour written twice. The second of each pair has a
			table with no background of its own in front of it, which used to leave the pair's second
			half painted on top of itself and so darker than the first.</p>

		<table class="panel red">
			<tr><td>Translucent red, with nothing in front of it</td></tr>
		</table>

		<table class="panel plain">
			<tr><td>A table with no background of its own</td></tr>
		</table>

		<table class="panel red">
			<tr><td>Translucent red again, and the same shade as the first</td></tr>
		</table>

		<table class="panel plain">
			<tr><td>Another table with no background of its own</td></tr>
		</table>

		<table class="panel blue">
			<tr><td>Translucent blue, one table with no background behind it</td></tr>
		</table>

		<table class="panel plain">
			<tr><td>A third table with no background of its own</td></tr>
		</table>

		<table class="panel">
			<tr><td class="tint">A translucent cell rather than a translucent table</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = new \Mpdf\Mpdf();
		$this->mpdf->WriteHTML($html);
	}
}
