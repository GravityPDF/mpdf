<?php

namespace Snapshots;

/**
 * The email invoice template under the legacy cascade
 *
 * @group snapshot
 */
class EmailInvoiceTemplateLegacySnapshotTest extends EmailInvoiceTemplateSnapshot
{

	/**
	 * @return string
	 */
	protected function cssMode()
	{
		return \Mpdf\CssMode::LEGACY;
	}

}
