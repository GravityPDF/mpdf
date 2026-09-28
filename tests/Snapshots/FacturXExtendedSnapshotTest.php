<?php

namespace Snapshots;

/**
 * An EXTENDED Factur-X invoice, embedded as Alternative: its structured early payment discount and late payment penalty
 * put into words
 *
 * @group snapshot
 */
class FacturXExtendedSnapshotTest extends FacturXSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'facturx-extended';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'extended.xml';
	}

}
