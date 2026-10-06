<?php

namespace Snapshots;

/**
 * The brochure under the legacy cascade
 *
 * @group snapshot
 */
class ComputedValuesBrochureLegacySnapshotTest extends ComputedValuesBrochureSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
