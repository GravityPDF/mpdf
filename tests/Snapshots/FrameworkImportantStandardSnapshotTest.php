<?php

namespace Snapshots;

/**
 * The framework stylesheet with !important utilities under the standard cascade
 *
 * @group snapshot
 */
class FrameworkImportantStandardSnapshotTest extends FrameworkImportantSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
