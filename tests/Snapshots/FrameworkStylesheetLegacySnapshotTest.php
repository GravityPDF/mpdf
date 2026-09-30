<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The framework stylesheet in cssMode legacy
 *
 * @group snapshot
 */
class FrameworkStylesheetLegacySnapshotTest extends FrameworkStylesheetSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::LEGACY;
	}

}
