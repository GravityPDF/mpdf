<?php

namespace Snapshots;

/**
 * The contract amendment under the legacy cascade
 *
 * @group snapshot
 */
class DecorationsAmendmentLegacySnapshotTest extends DecorationsAmendmentSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
