<?php

namespace Snapshots;

/**
 * Linear gradient backgrounds run the way CSS Images gives: "to" names the side the gradient ends at, and angles point
 * up at 0deg and run clockwise. Prefixed functions keep the legacy reading, where a keyword names the side the
 * gradient starts from and angles run counter-clockwise from pointing right.
 *
 * @group snapshot
 */
class LinearGradientDirectionSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'linear-gradient-direction';
	}

	/**
	 * A red to blue gradient box for each direction, under a caption saying where the red should be
	 */
	public function generatePdf()
	{
		$cases = [
			'Keywords' => [
				['linear-gradient(to bottom, red, blue)', 'to bottom: red at the top, blue at the bottom.'],
				['linear-gradient(to top, red, blue)', 'to top: red at the bottom, blue at the top.'],
				['linear-gradient(to right, red, blue)', 'to right: red at the left, blue at the right.'],
				['linear-gradient(to left, red, blue)', 'to left: red at the right, blue at the left.'],
				['linear-gradient(to bottom right, red, blue)', 'to bottom right: red in the top left corner, blue in the bottom right, and the other two corners the same purple.'],
				['linear-gradient(to top left, red, blue)', 'to top left: red in the bottom right corner, blue in the top left, and the other two corners the same purple.'],
			],
			'Angles' => [
				['linear-gradient(0deg, red, blue)', '0deg: red at the bottom, blue at the top.'],
				['linear-gradient(90deg, red, blue)', '90deg: red at the left, blue at the right.'],
				['linear-gradient(180deg, red, blue)', '180deg: red at the top, blue at the bottom.'],
				['linear-gradient(270deg, red, blue)', '270deg: red at the right, blue at the left.'],
				['linear-gradient(45deg, red, blue)', '45deg: red in the bottom left corner, blue in the top right.'],
				['linear-gradient(135deg, red, blue)', '135deg: red in the top left corner, blue in the bottom right.'],
				['linear-gradient(0.25turn, red, blue)', '0.25turn: red at the left, blue at the right.'],
			],
			'Prefixed functions' => [
				['-moz-linear-gradient(left, red, blue)', '-moz- left: red at the left, blue at the right.'],
				['-webkit-linear-gradient(top, red, blue)', '-webkit- top: red at the top, blue at the bottom.'],
				['-webkit-linear-gradient(90deg, red, blue)', '-webkit- 90deg: red at the bottom, blue at the top.'],
			],
		];

		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p { margin: 1mm 0 0.5mm 0; }
			div.box { width: 60mm; height: 8mm; border: 0.2mm solid #999999; }
		</style>
		<h1>linear-gradient() directions</h1>';

		foreach ($cases as $heading => $gradients) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($gradients as $case) {
				$html .= '<p>' . $case[1] . '</p><div class="box" style="background: ' . $case[0] . '"></div>';
			}
		}

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
