<?php

namespace Mpdf;

/**
 * Counts how often a document is put back to a snapshot: once for each page-break-inside:avoid block that ran onto
 * another page, and not at all for one that stayed. Counts the snapshots taken too. Both counts are part of the
 * snapshot, so they count what the layout that was kept went through
 */
class UnwindCountingMpdf extends Mpdf
{
	public $unwinds = 0;

	public $snapshots = 0;

	/**
	 * Counts the snapshot, before taking it
	 *
	 * @return array<string, mixed>
	 */
	public function getStateSnapshot()
	{
		$this->snapshots++;

		return parent::getStateSnapshot();
	}

	public function restoreStateSnapshot(array $snapshot)
	{
		parent::restoreStateSnapshot($snapshot); // the count is part of the snapshot, so it is kept after the restore
		$this->unwinds++;
	}
}
