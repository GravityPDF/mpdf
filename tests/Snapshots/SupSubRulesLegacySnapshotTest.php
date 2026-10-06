<?php

namespace Snapshots;

/**
 * Rules naming sup and sub in the legacy CSS mode
 *
 * @group snapshot
 */
class SupSubRulesLegacySnapshotTest extends SupSubRulesSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
