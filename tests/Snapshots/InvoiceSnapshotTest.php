<?php

namespace Snapshots;

/**
 * An invoice at two VAT rates, less a prepayment, with a line description and markup in an address to escape
 *
 * @group snapshot
 */
class InvoiceSnapshotTest extends InvoiceSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'en16931.xml';
	}

}
