<?php

namespace Snapshots;

/**
 * The article with tables in styled blocks under the legacy cascade
 *
 * @group snapshot
 */
class TableInBlocksArticleLegacySnapshotTest extends TableInBlocksArticleSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
