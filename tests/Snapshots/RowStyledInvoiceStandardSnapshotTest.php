<?php

namespace Snapshots;

/**
 * The invoice with styled rows under the standard cascade
 *
 * @group snapshot
 */
class RowStyledInvoiceStandardSnapshotTest extends RowStyledInvoiceSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
