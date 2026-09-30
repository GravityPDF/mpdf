<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The framework stylesheet in cssMode standard
 *
 * @group snapshot
 */
class FrameworkStylesheetStandardSnapshotTest extends FrameworkStylesheetSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::STANDARD;
	}

}
