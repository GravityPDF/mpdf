<?php

namespace Snapshots;

/**
 * The framework stylesheet under the standard cascade
 *
 * @group snapshot
 */
class FrameworkCascadeStandardSnapshotTest extends FrameworkCascadeSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
