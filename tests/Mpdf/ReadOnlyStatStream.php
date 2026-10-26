<?php

namespace Mpdf;

/**
 * Maps onto the local filesystem but reports every path with its write bits cleared, as a network share can.
 */
class ReadOnlyStatStream
{

	public $context;

	public function url_stat($path, $flags)
	{
		$stat = @stat(substr($path, strlen('ro-stat://')));
		if ($stat) {
			$stat['mode'] &= ~0222;
			$stat[2] = $stat['mode'];
		}

		return $stat;
	}

}
