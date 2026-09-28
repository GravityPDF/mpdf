<?php

namespace Snapshots;

/**
 * An EN 16931 Factur-X invoice, embedded as Alternative: two VAT rates, less a prepayment
 *
 * @group snapshot
 */
class FacturXEn16931SnapshotTest extends FacturXSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'facturx-en16931';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'en16931.xml';
	}

}
