<?php

namespace Snapshots;

/**
 * The catalogue under the standard cascade
 *
 * @group snapshot
 */
class BlockInsideInlineCatalogueStandardSnapshotTest extends BlockInsideInlineCatalogueSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
