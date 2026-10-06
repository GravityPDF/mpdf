<?php

namespace Snapshots;

/**
 * Absolutely positioned blocks turned by mPDF's rotate property, with the angle written with and without "deg"
 *
 * @group snapshot
 */
class PositionedBlockRotateSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'positioned-block-rotate';
	}

	/**
	 * A positioned block for each rotate value, beside a caption saying which way it should be turned
	 */
	public function generatePdf()
	{
		$cases = [
			['90', 'rotate: 90. Turned clockwise: the text reads top to bottom.'],
			['90deg', 'rotate: 90deg. Turned clockwise: the text reads top to bottom.'],
			['-90deg', 'rotate: -90deg. Turned counter-clockwise: the text reads bottom to top.'],
			['270deg', 'rotate: 270deg, the same as -90deg. The text reads bottom to top.'],
			['180deg', 'rotate: 180deg. Turned upside down: the text reads right to left, upside down.'],
			['45deg', 'rotate: 45deg, which mPDF does not support. Not turned: the text reads left to right.'],
		];

		$html = '<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0; }
			div.caption { position: absolute; line-height: 1.33; left: 20mm; width: 90mm; font-size: 8pt; color: #606060; }
			div.turned { position: absolute; line-height: 1.33; left: 130mm; width: 34mm; border: 0.3mm solid #1f3a93; background: #d6eaf8; padding: 1mm; }
		</style>
		<h1>rotate on a positioned block</h1>';

		foreach ($cases as $i => $case) {
			$top = 25 + $i * 44;
			$html .= '<div class="caption" style="top: ' . $top . 'mm">' . $case[1] . '</div>'
				. '<div class="turned" style="top: ' . $top . 'mm; rotate: ' . $case[0] . '">Turned block ' . $case[0] . '</div>';
		}

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
