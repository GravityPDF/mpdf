<?php

namespace Snapshots;

/**
 * A MINIMUM invoice, which has no lines or VAT breakdown: its totals alone, with the VAT as one sum
 *
 * @group snapshot
 */
class InvoiceMinimumSnapshotTest extends InvoiceSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-minimum';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'minimum.xml';
	}

}
