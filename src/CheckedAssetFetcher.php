<?php

namespace Mpdf;

use Mpdf\File\StreamWrapperChecker;

/**
 * Refuses a path through a stream wrapper that is not allowed before any asset fetcher sees it, including one passed
 * through the container that does no check of its own
 */
final class CheckedAssetFetcher implements AssetFetcherInterface
{

	/**
	 * @var \Mpdf\AssetFetcherInterface
	 */
	private $fetcher;

	/**
	 * @var \Mpdf\File\StreamWrapperChecker
	 */
	private $checker;

	/**
	 * @param \Mpdf\AssetFetcherInterface $fetcher
	 * @param \Mpdf\File\StreamWrapperChecker $checker
	 */
	public function __construct(AssetFetcherInterface $fetcher, StreamWrapperChecker $checker)
	{
		$this->fetcher = $fetcher;
		$this->checker = $checker;
	}

	/**
	 * Checks both paths, then asks the fetcher for the data
	 *
	 * @param string $path
	 * @param string|null $originalSrc
	 *
	 * @return string
	 *
	 * @throws \Mpdf\Exception\AssetFetchingException Where either path uses a stream wrapper that is not allowed
	 */
	public function fetchDataFromPath($path, $originalSrc = null)
	{
		$this->checker->assertAllowed($path, $originalSrc);

		return $this->fetcher->fetchDataFromPath($path, $originalSrc);
	}

}
