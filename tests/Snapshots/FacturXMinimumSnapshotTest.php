<?php

namespace Snapshots;

/**
 * A MINIMUM Factur-X invoice, embedded as Data: no lines or VAT breakdown, so its totals alone with the VAT as one sum
 *
 * @group snapshot
 */
class FacturXMinimumSnapshotTest extends FacturXSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'facturx-minimum';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'minimum.xml';
	}

}
