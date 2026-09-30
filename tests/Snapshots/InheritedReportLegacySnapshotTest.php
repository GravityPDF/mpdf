<?php

namespace Snapshots;

/**
 * The themed report under the legacy cascade
 *
 * @group snapshot
 */
class InheritedReportLegacySnapshotTest extends InheritedReportSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
