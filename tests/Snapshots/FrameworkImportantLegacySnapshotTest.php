<?php

namespace Snapshots;

/**
 * The framework stylesheet with !important utilities under the legacy cascade
 *
 * @group snapshot
 */
class FrameworkImportantLegacySnapshotTest extends FrameworkImportantSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
