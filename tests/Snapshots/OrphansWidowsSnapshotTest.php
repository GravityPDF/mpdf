<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * orphans and widows: a blue paragraph near the foot of a page breaks so that it leaves at least `orphans` lines on the
 * page and carries at least `widows` onto the next, or moves whole where no break does both. Each case takes two pages:
 * a box with its caption fills the first down to where the paragraph starts
 *
 * @group snapshot
 */
class OrphansWidowsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'orphans-widows';
	}

	/**
	 * Each case: the paragraph's style, its number of lines, the height of the box above it, which sets how many of its
	 * lines fit the first page without either property, and the caption
	 *
	 * @return array[]
	 */
	protected function cases()
	{
		return [
			['', 3, 252, 'Neither property set: two lines of the blue paragraph at the foot of this page, and Line 3 of 3 alone at the top of the next, as mPDF has always broken it.'],
			['orphans: 2; widows: 2', 3, 252, 'orphans: 2; widows: 2. Two lines fit here, which would carry the last over alone: none of the blue paragraph is on this page, and all three lines are at the top of the next.'],
			['orphans: 2; widows: 2', 3, 257, 'orphans: 2; widows: 2. One line fits here: none of the blue paragraph is on this page, and all three lines are at the top of the next.'],
			['orphans: 3; widows: 3', 6, 237, 'orphans: 3; widows: 3. Five lines fit here, which would carry Line 6 over alone: Lines 1 to 3 of 6 are at the foot of this page, and Lines 4 to 6 at the top of the next.'],
			['widows: 3', 4, 252, 'widows: 3 alone. Two lines fit here: Line 1 of 4 alone is at the foot of this page, as orphans is 1, and Lines 2 to 4 at the top of the next.'],
		];
	}

	/**
	 * Each case on a page of its own, with the paragraph set 35mm wide so each "Line n of m" is a line
	 */
	public function generatePdf()
	{
		$html = '<style>
			div.caption { border: 0.2mm solid #999999; font-size: 10pt; }
			p.lines { margin: 0; width: 35mm; background: #cce5ff; }
		</style>';

		foreach ($this->cases() as $i => $case) {
			list($style, $count, $height, $caption) = $case;
			$lines = [];
			for ($line = 1; $line <= $count; $line++) {
				$lines[] = 'Line&nbsp;' . $line . '&nbsp;of&nbsp;' . $count;
			}

			$html .= ($i ? '<pagebreak />' : '')
				. '<div class="caption" style="height: ' . $height . 'mm">' . $caption . '</div>'
				. '<p class="lines" style="' . $style . '">' . implode(' ', $lines) . '</p>';
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
