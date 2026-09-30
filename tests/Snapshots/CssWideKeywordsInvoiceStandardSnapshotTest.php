<?php

namespace Snapshots;

/**
 * The invoice under the standard CSS mode
 *
 * @group snapshot
 */
class CssWideKeywordsInvoiceStandardSnapshotTest extends CssWideKeywordsInvoiceSnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
