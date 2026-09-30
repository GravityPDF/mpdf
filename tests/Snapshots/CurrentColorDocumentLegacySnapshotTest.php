<?php

namespace Snapshots;

/**
 * The framework-styled invoice in the legacy CSS mode
 *
 * @group snapshot
 */
class CurrentColorDocumentLegacySnapshotTest extends CurrentColorDocumentSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
