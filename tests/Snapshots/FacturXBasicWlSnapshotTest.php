<?php

namespace Snapshots;

/**
 * A BASIC WL Factur-X invoice, embedded as Data: the parties, VAT breakdown and payment, but no lines
 *
 * @group snapshot
 */
class FacturXBasicWlSnapshotTest extends FacturXSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'facturx-basic-wl';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'basic-wl.xml';
	}

}
