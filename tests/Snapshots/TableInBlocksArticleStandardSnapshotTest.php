<?php

namespace Snapshots;

/**
 * The article with tables in styled blocks under the standard cascade
 *
 * @group snapshot
 */
class TableInBlocksArticleStandardSnapshotTest extends TableInBlocksArticleSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
