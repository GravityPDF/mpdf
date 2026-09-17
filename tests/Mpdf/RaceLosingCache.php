<?php

namespace Mpdf;

/**
 * A Cache that always loses the race for its base path: it stands in for the process that wins by
 * creating the directory after createBasePath() has found it missing and before mkdir() runs.
 */
class RaceLosingCache extends Cache
{

	private $winnerPermissions;

	public function __construct($basePath, $winnerPermissions = 0777)
	{
		$this->winnerPermissions = $winnerPermissions;

		parent::__construct($basePath);
	}

	protected function createDirectory($basePath)
	{
		mkdir($basePath, 0777, true);
		chmod($basePath, $this->winnerPermissions);

		return parent::createDirectory($basePath);
	}
}
