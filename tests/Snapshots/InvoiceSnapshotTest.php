<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\InvoiceFixtures;

/**
 * An invoice at two VAT rates, less a prepayment, with a line description and markup in an address to escape
 *
 * @group snapshot
 */
class InvoiceSnapshotTest extends InvoiceSnapshot
{

	use InvoiceFixtures;

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice';
	}

	/**
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	protected function getInvoice()
	{
		return $this->invoice();
	}

}
