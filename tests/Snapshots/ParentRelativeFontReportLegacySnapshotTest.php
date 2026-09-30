<?php

namespace Snapshots;

/**
 * The report under the legacy CSS mode
 *
 * @group snapshot
 */
class ParentRelativeFontReportLegacySnapshotTest extends ParentRelativeFontReportSnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
