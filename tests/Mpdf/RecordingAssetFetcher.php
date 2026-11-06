<?php

namespace Mpdf;

/**
 * An asset fetcher built on the interface alone, as a caller's own would be, serving its assets from memory
 */
class RecordingAssetFetcher implements AssetFetcherInterface
{

	/**
	 * What each path holds
	 *
	 * @var string[]
	 */
	private $assets;

	/**
	 * The paths asked for, in order
	 *
	 * @var string[]
	 */
	public $requested = [];

	/**
	 * @param string[] $assets What each path holds
	 */
	public function __construct(array $assets)
	{
		$this->assets = $assets;
	}

	/**
	 * Records the path, and returns what it holds
	 *
	 * @param string $path
	 * @param string|null $originalSrc
	 *
	 * @return string
	 */
	public function fetchDataFromPath($path, $originalSrc = null)
	{
		$this->requested[] = $path;

		return $this->assets[$path];
	}

}
