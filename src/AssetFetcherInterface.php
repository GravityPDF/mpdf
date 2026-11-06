<?php

namespace Mpdf;

interface AssetFetcherInterface
{
	/**
	 * Fetch data from a given path, either local or remote.
	 *
	 * The paths can come from the document's HTML and CSS. mPDF refuses a registered stream wrapper missing from
	 * `whitelistStreamWrappers`, and phar:// always, before this call. A scheme not registered with PHP reaches it,
	 * for the implementation to serve or refuse.
	 *
	 * @param string $path The path to fetch data from.
	 * @param string|null $originalSrc The original source path, if applicable.
	 * @return string The fetched data.
	 * @throws \Mpdf\Exception\AssetFetchingException If fetching fails.
	 */
	public function fetchDataFromPath($path, $originalSrc = null);
}
