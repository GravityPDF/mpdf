<?php

namespace Snapshots;

/**
 * The contract amendment under the standard cascade
 *
 * @group snapshot
 */
class DecorationsAmendmentStandardSnapshotTest extends DecorationsAmendmentSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
