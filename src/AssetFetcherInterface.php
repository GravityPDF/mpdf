<?php

namespace Mpdf;

interface AssetFetcherInterface
{
	/**
	 * Fetch data from a given path, either local or remote.
	 *
	 * The paths can come from the document's HTML and CSS, and mPDF does not check them before this call. An
	 * implementation must refuse stream wrappers such as phar:// itself, as AssetFetcher does through
	 * \Mpdf\File\StreamWrapperChecker.
	 *
	 * @param string $path The path to fetch data from.
	 * @param string|null $originalSrc The original source path, if applicable.
	 * @return string The fetched data.
	 * @throws \Mpdf\Exception\AssetFetchingException If fetching fails.
	 */
	public function fetchDataFromPath($path, $originalSrc = null);
}
