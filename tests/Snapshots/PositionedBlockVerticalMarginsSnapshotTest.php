<?php

namespace Snapshots;

/**
 * A positioned block's top and bottom margins each count once when its top, height or bottom is solved from the
 * others, so margin-top moves a box without stretching it, as in a browser
 *
 * @group snapshot
 */
class PositionedBlockVerticalMarginsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'positioned-block-vertical-margins';
	}

	/**
	 * The positioned blocks, each as its style and a caption saying where its box should be. Every one has
	 * margin-top: 20mm and margin-bottom: 5mm.
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['position: absolute; left: 10mm; bottom: 42mm; height: 30mm', 'absolute; bottom: 42mm; height: 30mm. A 30mm box resting on the lower line.'],
			['position: fixed; left: 48mm; bottom: 26mm; height: 30mm', 'fixed; bottom: 26mm; height: 30mm. A 30mm box resting on the lower line.'],
			['position: absolute; left: 86mm; top: 80mm; height: 30mm', 'absolute; top: 80mm; height: 30mm. A 30mm box hanging from the upper line.'],
			['position: absolute; left: 124mm; top: 80mm; height: 30mm; bottom: 42mm', 'absolute; top: 80mm; height: 30mm; bottom: 42mm. Bottom is ignored: a 30mm box hanging from the upper line.'],
			['position: absolute; left: 162mm; top: 80mm; bottom: 42mm', 'absolute; top: 80mm; bottom: 42mm. A box filling the space between the two lines.'],
		];
	}

	/**
	 * Two blue guide lines across the page, and a grey box for each case between or against them
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 9pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			div.box { width: 34mm; margin-top: 20mm; margin-bottom: 5mm; background-color: #bbbbbb; font-size: 7pt; }
			div.guide { position: absolute; left: 0; width: 210mm; height: 0.3mm; background-color: #0000ff; }
		</style>
		<h1>Top and bottom margins on a positioned block</h1>
		<p>Every grey box has margin-top: 20mm and margin-bottom: 5mm. The blue lines are at 100mm and 250mm from the top of the page.
		No box should reach past a line it touches, and every box with a height of 30mm should be 30mm tall.</p>
		<div class="guide" style="top: 99.85mm"></div>
		<div class="guide" style="top: 249.85mm"></div>';

		foreach ($this->cases() as $case) {
			$html .= '<div class="box" style="' . $case[0] . '">' . $case[1] . '</div>';
		}

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
