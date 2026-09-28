<?php

namespace Snapshots;

/**
 * An XRechnung CII invoice, embedded as xrechnung.xml: the buyer's Leitweg-ID as its reference and its Skonto line put
 * into words
 *
 * @group snapshot
 */
class FacturXXRechnungSnapshotTest extends FacturXSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'facturx-xrechnung';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'xrechnung.xml';
	}

}
