<?php

namespace Snapshots;

/**
 * The page with type sized in rem, in the legacy CSS mode
 *
 * @group snapshot
 */
class RootTypographyLegacySnapshotTest extends RootTypographySnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
