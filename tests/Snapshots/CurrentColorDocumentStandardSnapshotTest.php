<?php

namespace Snapshots;

/**
 * The framework-styled invoice in the standard CSS mode
 *
 * @group snapshot
 */
class CurrentColorDocumentStandardSnapshotTest extends CurrentColorDocumentSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
