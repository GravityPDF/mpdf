<?php

namespace Snapshots;

/**
 * An invoice under the reverse charge: no VAT, the reason for none beside its group, and the total in bold as nothing
 * was prepaid
 *
 * @group snapshot
 */
class InvoiceReverseChargeSnapshotTest extends InvoiceSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-reverse-charge';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'en16931-reverse-charge.xml';
	}

}
