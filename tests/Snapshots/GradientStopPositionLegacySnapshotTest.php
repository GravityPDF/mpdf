<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The legacy CSS mode reads gradient stops and positions at fractional percentages and zero lengths as the standard
 * one does, and draws the same document.
 *
 * @group snapshot
 */
class GradientStopPositionLegacySnapshotTest extends GradientStopPositionSnapshotTest
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'gradient-stop-position-legacy';
	}

	/**
	 * The CSS mode the document is drawn in
	 *
	 * @return string
	 */
	protected function cssMode()
	{
		return CssMode::LEGACY;
	}

}
