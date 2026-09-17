<?php

namespace Mpdf;

use DirectoryIterator;

/**
 * A Cache that always loses the race to remove an expired file: for the file it names it stands in
 * for the process that gets there first. isDotFile() is the last thing clearOld() asks before it
 * reads the mtime, so removing the file there lands it in that window; removing it from inside this
 * process also flushes the stat cache isFile() primed, which is what makes the stat fail every time
 * rather than only when another process happens to evict that one entry.
 */
class ExpiryRaceLosingCache extends Cache
{

	const STOLEN = 'vanishes-before-the-stat';

	public function isDotFile(DirectoryIterator $item)
	{
		if ($item->getFilename() === self::STOLEN) {
			unlink($item->getPathname());
		}

		return parent::isDotFile($item);
	}
}
