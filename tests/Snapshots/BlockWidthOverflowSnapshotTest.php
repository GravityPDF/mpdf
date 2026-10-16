<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A block wider than its container sticks out of it, as in a browser, and a positioned block is placed by both its
 * margins. The page area is 180mm wide, from 15mm to 195mm.
 *
 * @group snapshot
 */
class BlockWidthOverflowSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-width-overflow';
	}

	/**
	 * Each case as the style of its 60mm container, the style of the block inside it, and a caption saying where the
	 * block's box should be
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['', 'min-width: 100mm', 'min-width: 100mm in a 60mm container: a 100mm dark box at the left, sticking 40mm out of the light container on the right.'],
			['', 'width: 100mm; margin: 0 auto', 'width: 100mm; margin: 0 auto: the same, as the auto margins count as 0.'],
			['', 'width: 60mm; padding: 0 5mm', 'width: 60mm; padding: 0 5mm: a 70mm dark box at the left, sticking 10mm out on the right.'],
			['margin-left: auto', 'width: 100mm; direction: rtl', 'width: 100mm; direction: rtl, in a container at the right: a 100mm dark box sticking 40mm out on the left.'],
			['', 'float: left; width: 100mm', 'float: left; width: 100mm: a 100mm dark box at the left, sticking 40mm out on the right.'],
			['margin-left: auto', 'float: right; min-width: 100mm', 'float: right; min-width: 100mm, in a container at the right: a 100mm dark box sticking 40mm out on the left.'],
		];
	}

	/**
	 * A dark box in a light container for each case under its caption, and two positioned blocks at the foot of the page
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 3mm 0 0.5mm 0; }
			div.container { width: 60mm; padding-bottom: 3mm; background-color: #dddddd; }
			div.box { height: 8mm; background-color: #888888; }
		</style>
		<h1>A block wider than its container</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p style="clear: both">' . $case[2] . '</p>'
				. '<div class="container" style="' . $case[0] . '"><div class="box" style="' . $case[1] . '">box</div>'
				. '<div style="clear: both"></div></div>';
		}

		$html .= '<p style="clear: both">position: absolute; margin-left: 5mm; margin-right: 30mm on the 210mm page.'
			. ' Upper box: left: 10mm; right: 20mm; width: 100mm: a 100mm box 15mm from the page\'s left edge.'
			. ' Lower box: right: 20mm; width: 100mm: a 100mm box 60mm from the left edge, its right edge 50mm from the right edge.</p>';

		$positioned = 'position: absolute; margin-left: 5mm; margin-right: 30mm;';
		$html .= '<div class="box" style="' . $positioned . ' top: 240mm; left: 10mm; right: 20mm; width: 100mm">box</div>'
			. '<div class="box" style="' . $positioned . ' top: 255mm; right: 20mm; width: 100mm">box</div>';

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
