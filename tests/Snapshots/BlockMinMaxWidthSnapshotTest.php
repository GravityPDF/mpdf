<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * min-width and max-width hold the width of a block, so max-width with margin: auto centres it, as in a browser.
 * The page area is 180mm wide, from 15mm to 195mm.
 *
 * @group snapshot
 */
class BlockMinMaxWidthSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-min-max-width';
	}

	/**
	 * The blocks in the flow, each as its style and a caption saying how wide the box should be and where
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['max-width: 100mm; margin: 0 auto', 'max-width: 100mm; margin: 0 auto: a 100mm box centred, 40mm in from each side.'],
			['width: 150mm; max-width: 100mm', 'width: 150mm; max-width: 100mm: a 100mm box at the left.'],
			['width: 50mm; min-width: 80mm', 'width: 50mm; min-width: 80mm: an 80mm box at the left.'],
			['max-width: 50%', 'max-width: 50%: a 90mm box at the left, half the page area.'],
			['max-width: 100mm; margin-left: auto', 'max-width: 100mm; margin-left: auto: a 100mm box at the right.'],
			['max-width: 100mm; direction: rtl', 'max-width: 100mm; direction: rtl: a 100mm box at the right, its text right-aligned.'],
			['float: left; max-width: 100mm', 'float: left; max-width: 100mm: a 100mm box at the left, with the text of the next caption beside it.'],
			['max-width: 100mm; margin: 0 auto; padding: 5mm; border: 1mm solid #000000', 'max-width: 100mm; margin: 0 auto; padding: 5mm; border: 1mm solid: a 112mm box centred, 34mm in from each side, with a black border.'],
		];
	}

	/**
	 * A grey box for each case under its caption, and a positioned block at the foot of the page
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 2mm 0 0.5mm 0; }
			div.box { height: 8mm; background-color: #bbbbbb; }
		</style>
		<h1>min-width and max-width on a block</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . $case[1] . '</p><div class="box" style="' . $case[0] . '">box</div>';
		}

		$html .= '<p style="clear: both">position: absolute; left: 0; right: 0; max-width: 100mm; margin: 0 auto:'
			. ' a 100mm box centred on the 210mm page, at its foot.</p>'
			. '<div class="box" style="position: absolute; left: 0; right: 0; top: 265mm; max-width: 100mm; margin: 0 auto">box</div>';

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
