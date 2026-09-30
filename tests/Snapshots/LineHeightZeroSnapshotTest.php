<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * line-height: 0 giving lines no height rather than a normal one, a line height smaller than the font kept rather than
 * stretched down to the baseline, and a negative line-height ignored, in cssMode standard
 *
 * @group snapshot
 */
class LineHeightZeroSnapshotTest extends LineHeightZeroSnapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'line-height-zero';
	}

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::STANDARD;
	}

	/**
	 * @return string[]
	 */
	protected function captions()
	{
		return [
			'zero' => 'The three lines are drawn at the same height, the blue background has no height, and the green line starts straight after it, over the lower half of the text.',
			'zero-length' => 'line-height: 0px: the same.',
			'zero-parent' => 'line-height: 0 on the div around the paragraph: the same.',
			'one-mm' => 'line-height: 1mm: each line 1mm below the one before, so they overlap, on a background 3mm tall.',
			'half' => 'line-height: 0.5: each line half the font size (6pt) below the one before, so they overlap, on a background 18pt tall.',
			'negative' => 'line-height: -1 and -2mm inside a div with line-height: 2 are ignored: both paragraphs are double spaced, like the third, which sets nothing.',
			'cell' => 'line-height: 0 in the middle cell: its lines overlap and the row is only as tall as the other cells, which are normal.',
		];
	}

}
