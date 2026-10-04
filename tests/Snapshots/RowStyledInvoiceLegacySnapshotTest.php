<?php

namespace Snapshots;

/**
 * The invoice with styled rows under the legacy cascade
 *
 * @group snapshot
 */
class RowStyledInvoiceLegacySnapshotTest extends RowStyledInvoiceSnapshot
{

	/**
	 * @return string
	 */
	protected function cascade()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
