<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The newsletter article in cssMode standard
 *
 * @group snapshot
 */
class InlineLineHeightArticleStandardSnapshotTest extends InlineLineHeightArticleSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::STANDARD;
	}

}
