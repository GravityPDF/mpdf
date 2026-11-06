<?php

namespace Mpdf\File;

use Mpdf\Exception\AssetFetchingException;
use Mpdf\Mpdf;

final class StreamWrapperChecker
{

	private $mpdf;

	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * @param string $filename
	 * @return bool
	 * @since 7.1.8
	 */
	public function hasBlacklistedStreamWrapper($filename)
	{
		if (strpos($filename, '://') > 0) {
			$wrappers = stream_get_wrappers();
			$whitelistStreamWrappers = $this->getWhitelistedStreamWrappers();
			foreach ($wrappers as $wrapper) {
				if (in_array($wrapper, $whitelistStreamWrappers)) {
					continue;
				}

				if (stripos($filename, $wrapper . '://') === 0) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Prevents insecure PHP object injection through phar:// wrapper
	 *
	 * @see https://github.com/mpdf/mpdf/issues/949
	 * @see https://github.com/mpdf/mpdf/issues/1381
	 *
	 * @param string $path
	 * @param string|null $originalSrc
	 *
	 * @throws \Mpdf\Exception\AssetFetchingException Where either uses a stream wrapper that is not allowed
	 */
	public function assertAllowed($path, $originalSrc)
	{
		if ($this->hasBlacklistedStreamWrapper($path) || ($originalSrc && $this->hasBlacklistedStreamWrapper($originalSrc))) {
			throw new AssetFetchingException('File contains an invalid stream. Only ' . implode(', ', $this->getWhitelistedStreamWrappers()) . ' streams are allowed.');
		}
	}

	public function getWhitelistedStreamWrappers()
	{
		return array_diff($this->mpdf->whitelistStreamWrappers, ['phar']); // remove 'phar' (security issue)
	}

}
