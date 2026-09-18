<?php

namespace Mpdf;

/**
 * A Cache that loses the race for an entry: it stands in for the process whose clearOld() expires the
 * entry after this one has decided it wants it and before the read lands.
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

	public function loadIfPresent($filename)
	{
		$this->expire($filename);

		return parent::loadIfPresent($filename);
	}

	private function expire($filename)
	{
		$at = array_search($filename, $this->expiring, true);

		if (false === $at || !$this->has($filename)) {
			return;
		}

		unset($this->expiring[$at]);
		unlink($this->tempFilename($filename));
	}
}
