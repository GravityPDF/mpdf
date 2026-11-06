<?php

namespace Mpdf;

use Mpdf\File\LocalContentLoaderInterface;

/**
 * Gives the same bytes for every path, and counts how often it was asked
 */
class CountingContentLoader implements LocalContentLoaderInterface
{

	/**
	 * @var int
	 */
	public $loads = 0;

	/**
	 * @var string
	 */
	private $data;

	/**
	 * @param string $data What every load gives
	 */
	public function __construct($data)
	{
		$this->data = $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function load($path)
	{
		$this->loads++;

		return $this->data;
	}
}
