<?php

namespace Snapshots;

/**
 * The framework stylesheet under the legacy cascade
 *
 * @group snapshot
 */
class FrameworkCascadeLegacySnapshotTest extends FrameworkCascadeSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
