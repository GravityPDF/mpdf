<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The newsletter article in cssMode legacy
 *
 * @group snapshot
 */
class InlineLineHeightArticleLegacySnapshotTest extends InlineLineHeightArticleSnapshot
{

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::LEGACY;
	}

}
