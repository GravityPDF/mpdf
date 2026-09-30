<?php

namespace Snapshots;

/**
 * The page with type sized in rem, in the standard CSS mode
 *
 * @group snapshot
 */
class RootTypographyStandardSnapshotTest extends RootTypographySnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
