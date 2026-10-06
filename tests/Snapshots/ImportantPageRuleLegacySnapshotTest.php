<?php

namespace Snapshots;

/**
 * The important @page margins in the legacy CSS mode
 *
 * @group snapshot
 */
class ImportantPageRuleLegacySnapshotTest extends ImportantPageRuleSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
