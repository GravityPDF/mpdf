<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\InvoiceFixtures;

/**
 * An invoice under the reverse charge: no VAT, the reason for none beside its group, and the total in bold as nothing
 * was prepaid
 *
 * @group snapshot
 */
class InvoiceReverseChargeSnapshotTest extends InvoiceSnapshot
{

	use InvoiceFixtures;

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-reverse-charge';
	}

	/**
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	protected function getInvoice()
	{
		return $this->reverseChargeInvoice();
	}

}
