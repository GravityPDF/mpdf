<?php

namespace Snapshots;

/**
 * The brochure under the standard cascade
 *
 * @group snapshot
 */
class ComputedValuesBrochureStandardSnapshotTest extends ComputedValuesBrochureSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
