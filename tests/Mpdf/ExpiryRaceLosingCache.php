<?php

namespace Mpdf;

use DirectoryIterator;

/**
 * A Cache that always loses the race to remove an expired file. For the two files it names it
 * stands in for the process that gets there first, once before this one can stat a file and once
 * after this one has already judged a file expired.
 */
class ExpiryRaceLosingCache extends Cache
{

	const BEFORE_THE_STAT = 'vanishes-before-the-stat';

	const AFTER_THE_STAT = 'vanishes-after-the-stat';

	protected function isOld(DirectoryIterator $item)
	{
		if ($item->getFilename() === self::BEFORE_THE_STAT) {
			unlink($item->getPathname());

			return parent::isOld($item);
		}

		if ($item->getFilename() === self::AFTER_THE_STAT) {
			$isOld = parent::isOld($item);
			unlink($item->getPathname());

			return $isOld;
		}

		return parent::isOld($item);
	}
}
