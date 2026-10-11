<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A block that clips its overflow clips the shadows of the blocks inside it, follows its own rounded corners, and a
 * percentage height inside a positioned block is taken of that block's height (#704)
 *
 * @group snapshot
 */
class OverflowClipSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'overflow-clip';
	}

	/**
	 * The blocks in the flow, each with a caption saying what it should show, grouped under headings
	 *
	 * @return array
	 */
	protected function cases()
	{
		$shadowed = '<div class="inner" style="height: 10mm; box-shadow: 8mm 14mm 0 #3355cc">shadowed</div>';
		$filled = '<div style="height: 40mm; padding-top: 8mm; text-align: center; background-color: #ff6666">filled</div>';

		return [
			'Shadows inside a clip' => [
				['<div class="box" style="height: 20mm; overflow: hidden">' . $shadowed . '</div>', 'A 20mm grey box; the red box inside casts a blue shadow 14mm down and 8mm right, cut off at the grey box\'s bottom border.'],
				['<div class="box room" style="height: 20mm">' . $shadowed . '</div>', 'The same with overflow visible: the blue shadow runs 4mm below the grey box\'s bottom border.'],
			],
			'Rounded clips' => [
				['<div class="box" style="height: 20mm; overflow: hidden; border-radius: 8mm">' . $filled . '</div>', 'A 20mm box with 8mm rounded corners, filled red to its border; no red shows outside the rounded border at the corners.'],
				['<div class="box" style="height: 20mm; overflow: hidden; border-radius: 50%">' . $filled . '</div>', 'A grey-bordered ellipse filled red inside; no red shows outside it.'],
				['<div class="box" style="height: 20mm; overflow: hidden; border-radius: 8mm 0">' . $filled . '</div>', 'Rounded top left and bottom right corners only, filled red; the other two corners are square and red to the border.'],
			],
		];
	}

	/**
	 * A box for each case, under a caption saying what it should show, then two positioned boxes at the foot of the
	 * page with a block of height 50% inside each
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p { margin: 1mm 0 0.5mm 0; }
			div.box { width: 60mm; border: 0.5mm solid #666666; background-color: #e6e6e6; margin-bottom: 4mm; }
			div.room { margin-bottom: 10mm; }
			div.inner { width: 40mm; background-color: #ff9999; }
			div.positioned { position: absolute; top: 215mm; width: 70mm; background-color: #cccccc; }
			div.half { height: 50%; background-color: #ff9999; }
		</style>
		<h1>Overflow clips</h1>';

		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$html .= '<p>' . $case[1] . '</p>' . $case[0];
			}
		}

		$html .= '<h2>Percentage heights in positioned blocks</h2>
			<p>Two grey boxes at the foot of the page. The left one is 60mm tall (height: 60mm) and the red block at its top is 30mm, half of it. The right one runs from 215mm to 20mm above the foot of the page (top and bottom), 62mm, and its red block is 31mm.</p>
			<div class="positioned" style="left: 15mm; height: 60mm"><div class="half">50% of 60mm</div></div>
			<div class="positioned" style="left: 105mm; bottom: 20mm"><div class="half">50% of top to bottom</div></div>';

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
