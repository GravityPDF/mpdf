<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * Zero, small and negative line heights in cssMode legacy, which reads them as mPDF v7 did: line-height: 0 as normal,
 * a line height smaller than the font stretched down to the baseline, and a negative one applied
 *
 * @group snapshot
 */
class LineHeightZeroLegacySnapshotTest extends LineHeightZeroSnapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'line-height-zero-legacy';
	}

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::LEGACY;
	}

	/**
	 * @return string[]
	 */
	protected function captions()
	{
		return [
			'zero' => 'line-height: 0 is read as normal: the three lines are normally spaced on the blue background, and the green line starts below it.',
			'zero-length' => 'line-height: 0px: the same.',
			'zero-parent' => 'line-height: 0 on the div around the paragraph: the same.',
			'one-mm' => 'line-height: 1mm: each line is stretched down to the baseline, so the lines are about 2mm apart, on a background about 6mm tall.',
			'half' => 'line-height: 0.5: each line is stretched down to the baseline, so the background is about 21pt tall rather than 18pt.',
			'negative' => 'line-height: -1 and -2mm inside a div with line-height: 2 are applied: the lines of the first two paragraphs are drawn over one another on a thin background. Only the third, which sets nothing, is double spaced.',
			'cell' => 'line-height: 0 in the middle cell is read as normal: its two lines are normally spaced, and the row is as tall as the three lines of the first cell.',
		];
	}

}
