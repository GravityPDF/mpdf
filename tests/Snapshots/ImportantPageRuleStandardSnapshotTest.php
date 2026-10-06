<?php

namespace Snapshots;

/**
 * The important @page margins in the standard CSS mode
 *
 * @group snapshot
 */
class ImportantPageRuleStandardSnapshotTest extends ImportantPageRuleSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
