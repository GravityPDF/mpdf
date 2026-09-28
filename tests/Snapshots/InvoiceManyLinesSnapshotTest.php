<?php

namespace Snapshots;

/**
 * An invoice of more lines than fit on a page: the column headings repeat on the next, and the totals follow the last
 * line
 *
 * @group snapshot
 */
class InvoiceManyLinesSnapshotTest extends InvoiceSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-many-lines';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'en16931-many-lines.xml';
	}

}
