<?php

namespace Snapshots;

/**
 * A BASIC Factur-X invoice, embedded as Alternative: its lines without descriptions, and its account without a BIC or name
 *
 * @group snapshot
 */
class FacturXBasicSnapshotTest extends FacturXSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'facturx-basic';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'basic.xml';
	}

}
