<?php

namespace Snapshots;

/**
 * border-radius on table cells: in the separated borders model a cell's backgrounds are clipped to its rounded border
 * box and its border follows the curve, whatever the border's style. Under border-collapse: collapse the radius is
 * ignored and the cell stays square.
 *
 * @group snapshot
 */
class TableCellBorderRadiusSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'table-cell-border-radius';
	}

	/**
	 * The cells, each as [its style, its text, a caption saying what it should look like]
	 *
	 * @return array
	 */
	private function cases()
	{
		return [
			['border: 1mm solid #00f; border-radius: 3mm; background: #0f0', 'solid', 'A green cell with a blue border, all four corners rounded by 3 mm.'],
			['border: 1mm solid #00f; border-radius: 50%; background: #0f0', '50%', 'An ellipse: green inside a blue border, as wide and tall as the cell.'],
			['border: 1mm solid #00f; border-radius: 6mm 0; background: #0f0', '6mm 0', 'The top left and bottom right corners rounded, the other two square.'],
			['border: 1mm dashed #00f; border-radius: 4mm; background: #0f0', 'dashed', 'A blue dashed border running round the curves of the corners.'],
			['border: 1mm dotted #00f; border-radius: 4mm; background: #0f0', 'dotted', 'A blue dotted border running round the curves of the corners.'],
			['border: 2.4mm double #00f; border-radius: 5mm; background: #0f0', 'double', 'Two blue lines round the corners, green between them and inside.'],
			['border: 3mm groove #36c; border-radius: 5mm; background: #ff0', 'groove', 'Rounded and two-tone: dark outside and light inside at the top and left, the other way round at the bottom and right.'],
			['border: 3mm ridge #36c; border-radius: 5mm; background: #ff0', 'ridge', 'Rounded and two-tone: light outside and dark inside at the top and left, the other way round at the bottom and right.'],
			['border: 3mm inset #36c; border-radius: 5mm; background: #ff0', 'inset', 'Rounded and two-tone, the same tones as ridge.'],
			['border: 3mm outset #36c; border-radius: 5mm; background: #ff0', 'outset', 'Rounded and two-tone, the same tones as groove.'],
			['border: 1mm solid #00f; border-radius: 5mm; background: linear-gradient(to right, #f00, #00f)', 'gradient', 'A red to blue gradient clipped to the rounded corners.'],
			['border-radius: 5mm', 'row', 'The pink of the row clipped to the cell\'s rounded corners; no border.'],
		];
	}

	/**
	 * Each case in a table of its own under a caption saying what it should look like, then a collapsed table whose
	 * cell keeps its corners square
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 1.5mm 0 0.5mm 0; }
			table { border-collapse: separate; border-spacing: 1mm; }
			td { width: 60mm; height: 10mm; }
			tr { background: #fcc; }
		</style>
		<h1>border-radius on table cells</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . $case[2] . '</p><table><tr><td style="' . $case[0] . '">' . $case[1] . '</td></tr></table>';
		}

		$html .= '<p>border-collapse: collapse ignores the radius: a square green cell with a blue border.</p>'
			. '<table style="border-collapse: collapse"><tr style="background: none"><td style="border: 1mm solid #00f; border-radius: 3mm; background: #0f0">collapse</td></tr></table>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
