<?php

namespace Snapshots;

/**
 * nth-child rules on table cells followed by parts that, with the formula, name no cell, or that mPDF cannot match.
 * Each shades no cell, rather than every cell the formula names.
 *
 * @group snapshot
 */
class NthChildArgumentSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nth-child-argument';
	}

	/**
	 * Tables each under a rule that must shade none of their cells, with a caption saying why
	 */
	public function generatePdf()
	{
		$rules = [
			'td:nth-child(2):not(.x)' => 'names only second cells without the class x, and every second cell has it',
			'td:nth-child(2):nth-child(odd)' => 'names a cell that is both second and odd',
			'td:nth-child(2 of .x)' => 'names the second cell with the class x, and each row has only one',
			'tr:nth-child(2) td:hover' => 'has a part that never matches in a PDF, so not even the second row is shaded',
		];

		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 1mm 3mm; }
			<?php $i = 0; foreach ($rules as $selector => $caption) { ?>
			table.case<?php echo $i++; ?> <?php echo $selector; ?> { background-color: #ff0000; }
			<?php } ?>
		</style>

		<h1>An nth-child argument and what follows it</h1>

		<?php $i = 0; foreach ($rules as $selector => $caption) { ?>
		<p class="caption"><?php echo $selector; ?> <?php echo $caption; ?>: no cell is red.</p>
		<table class="case<?php echo $i++; ?>">
			<?php for ($row = 1; $row <= 3; $row++) { ?>
			<tr>
				<td>R<?php echo $row; ?> C1</td>
				<td class="x">R<?php echo $row; ?> C2 (x)</td>
				<td>R<?php echo $row; ?> C3</td>
			</tr>
			<?php } ?>
		</table>
		<?php } ?>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
