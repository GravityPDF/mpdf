<?php

namespace Snapshots;

/**
 * The invoice under the legacy CSS mode
 *
 * @group snapshot
 */
class CssWideKeywordsInvoiceLegacySnapshotTest extends CssWideKeywordsInvoiceSnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
