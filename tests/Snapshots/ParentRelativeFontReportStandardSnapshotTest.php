<?php

namespace Snapshots;

/**
 * The report under the standard CSS mode
 *
 * @group snapshot
 */
class ParentRelativeFontReportStandardSnapshotTest extends ParentRelativeFontReportSnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
