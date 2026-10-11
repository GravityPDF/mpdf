<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * Table columns sized by <col> and <colgroup>, and tables with table-layout: fixed, which keep their width and size
 * their columns from those and their first row whatever their content. Each table has a grey bar above it as wide as
 * it should be, split where its columns should meet.
 *
 * @group snapshot
 */
class TableColumnWidthsSnapshotTest extends Snapshot
{

	/** Too long to break between words */
	const LONG_WORD = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'table-column-widths';
	}

	/**
	 * The tables, each with a caption saying how its columns should be sized and the widths they should come to, in mm
	 *
	 * @return array[]
	 */
	protected function cases()
	{
		$fixed = '<table style="table-layout: fixed; width: 100mm">';

		return [
			[
				'table-layout: fixed, width 100mm, a first cell of 20mm holding one long word: the first column 20mm with the word broken over several lines, the second 80mm.',
				$fixed . '<tr><td style="width: 20mm">' . self::LONG_WORD . '</td><td>b</td></tr></table>',
				[20, 80],
			],
			[
				'The same table laid out by its content (no table-layout): the first column widens for the word, so the table runs past the end of the bar.',
				'<table style="width: 100mm"><tr><td style="width: 20mm">' . self::LONG_WORD . '</td><td>b</td></tr></table>',
				[20, 80],
			],
			[
				'<col style="width: 80mm"><col style="width: 20mm">: columns of 80mm and 20mm.',
				'<table>
					<col style="width: 80mm">
					<col style="width: 20mm">
					<tr><td>a</td><td>b</td></tr>
					<tr><td>c</td><td>d</td></tr>
				</table>',
				[80, 20],
			],
			[
				'<colgroup> holding <col width="80mm"><col width="20mm">: columns of 80mm and 20mm.',
				'<table>
					<colgroup>
						<col width="80mm">
						<col width="20mm">
					</colgroup>
					<tr><td>a</td><td>b</td></tr>
				</table>',
				[80, 20],
			],
			[
				'<col span="2" width="30mm"><col width="40mm">: columns of 30, 30 and 40mm.',
				'<table><col span="2" width="30mm"><col width="40mm"><tr><td>a</td><td>b</td><td>c</td></tr></table>',
				[30, 30, 40],
			],
			[
				'<colgroup span="3" width="25mm"> with no col in it and its end tag left out: three columns of 25mm.',
				'<table><colgroup span="3" width="25mm"><tbody><tr><td>a</td><td>b</td><td>c</td></tr></tbody></table>',
				[25, 25, 25],
			],
			[
				'Columns of 30 and 50mm under a first row whose one cell spans both, then a row with a cell in each.',
				'<table><col width="30mm"><col width="50mm"><tr><td colspan="2">a cell spanning both columns</td></tr><tr><td>a</td><td>b</td></tr></table>',
				[30, 50],
			],
			[
				'table-layout: fixed, width 100mm, <col width="40mm"> and a first row asking for 70mm for that column: the col wins; the other two columns share the 60mm left, 30mm each.',
				$fixed . '<col width="40mm"><tr><td style="width: 70mm">a</td><td>b</td><td>c</td></tr><tr><td>d</td><td style="width: 60mm">e</td><td>f</td></tr></table>',
				[40, 30, 30],
			],
			[
				'table-layout: fixed, width 100mm, a first row of 10% and 40%: columns of 10, 40 and 50mm.',
				$fixed . '<tr><td style="width: 10%">a</td><td style="width: 40%">b</td><td>c</td></tr></table>',
				[10, 40, 50],
			],
			[
				'table-layout: fixed, width 100mm, no widths and a long word in each cell: four columns of 25mm, each word broken.',
				$fixed . '<tr><td>' . self::LONG_WORD . '</td><td>' . self::LONG_WORD . '</td><td>' . self::LONG_WORD . '</td><td>' . self::LONG_WORD . '</td></tr></table>',
				[25, 25, 25, 25],
			],
		];
	}

	/**
	 * Each table under its caption and a bar as wide as each of its columns should be
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 3mm 0 1mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #000000; padding: 0; }
			div.bar { height: 2mm; margin-bottom: 0.5mm; }
			div.bar div { float: left; height: 2mm; }
		</style>
		<h1>Table column widths</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . htmlspecialchars($case[0]) . '</p><div class="bar">';
			foreach ($case[2] as $i => $width) {
				$html .= '<div style="width: ' . $width . 'mm; background: ' . ($i % 2 ? '#999999' : '#cccccc') . '"></div>';
			}
			$html .= '</div>' . $case[1];
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
