<?php

namespace Snapshots;

/**
 * The email invoice template under the standard cascade
 *
 * @group snapshot
 */
class EmailInvoiceTemplateStandardSnapshotTest extends EmailInvoiceTemplateSnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::STANDARD;
	}

}
