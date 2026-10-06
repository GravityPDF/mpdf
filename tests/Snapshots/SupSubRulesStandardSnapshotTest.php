<?php

namespace Snapshots;

/**
 * Rules naming sup and sub in the standard CSS mode
 *
 * @group snapshot
 */
class SupSubRulesStandardSnapshotTest extends SupSubRulesSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
