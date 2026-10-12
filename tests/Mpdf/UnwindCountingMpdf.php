<?php

namespace Mpdf;

/**
 * Counts how often a document is put back to a snapshot: once for each page-break-inside:avoid block that ran onto
 * another page, and not at all for one that stayed, and once for each extra layout orphans and widows ask of a block
 */
class UnwindCountingMpdf extends Mpdf
{
	public $unwinds = 0;

	/**
	 * Puts the document back, counting it. The count is part of the snapshot, so it is carried over the restore: a
	 * block put back to the same snapshot twice counts twice
	 *
	 * @param array $snapshot
	 */
	public function restoreStateSnapshot(array $snapshot)
	{
		$unwinds = $this->unwinds;
		parent::restoreStateSnapshot($snapshot);
		$this->unwinds = $unwinds + 1;
	}
}
