<?php

namespace Snapshots;

/**
 * The themed report under the standard cascade
 *
 * @group snapshot
 */
class InheritedReportStandardSnapshotTest extends InheritedReportSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
