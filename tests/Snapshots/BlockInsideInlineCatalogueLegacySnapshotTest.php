<?php

namespace Snapshots;

/**
 * The catalogue under the legacy cascade
 *
 * @group snapshot
 */
class BlockInsideInlineCatalogueLegacySnapshotTest extends BlockInsideInlineCatalogueSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
