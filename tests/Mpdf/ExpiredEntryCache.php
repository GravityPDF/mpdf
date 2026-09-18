<?php

namespace Mpdf;

/**
 * A Cache that loses the race for an entry: it stands in for the process whose clearOld() expires the
 * entry after this one has decided it wants it and before the read lands.
 *
 * The entry goes when it is asked for, after the answer that it is there - which is the window, and is
 * the same window whether the caller reads through loadIfPresent() or asks has() and then load(). A
 * caller that went back to the second would fail these tests rather than stop racing.
 *
 * Each named entry goes once, so that a caller answering the miss by generating the entry again can
 * load what it wrote.
 */
class ExpiredEntryCache extends Cache
{

	private $expiring;

	public function __construct($basePath, array $expiring)
	{
		$this->expiring = $expiring;

		parent::__construct($basePath);
	}

	public function has($filename)
	{
		$present = parent::has($filename);

		if ($present) {
			$this->expire($filename);
		}

		return $present;
	}

	private function expire($filename)
	{
		$at = array_search($filename, $this->expiring, true);

		if (false === $at) {
			return;
		}

		unset($this->expiring[$at]);
		unlink($this->tempFilename($filename));
	}
}
