<?php

namespace Mpdf;

use DirectoryIterator;

class Cache
{

	private $basePath;

	private $cleanupInterval;

	public function __construct($basePath, $cleanupInterval = 3600)
	{
		if (!is_int($cleanupInterval) && false !== $cleanupInterval) {
			throw new \Mpdf\MpdfException('Cache cleanup interval has to be an integer or false');
		}

		if (!$this->createBasePath($basePath)) {
			throw new \Mpdf\MpdfException(sprintf('Temporary files directory "%s" is not writable', $basePath));
		}

		$this->basePath = $basePath;
		$this->cleanupInterval = $cleanupInterval;
	}

	protected function createBasePath($basePath)
	{
		if (!file_exists($basePath)) {
			if (!$this->createDirectory($basePath)) {
				return false;
			}
		}

		if (!is_writable($basePath) || !is_dir($basePath)) {
			return false;
		}

		return true;
	}

	protected function createDirectory($basePath)
	{
		$parentPath = $this->getExistingParentDirectory($basePath);
		$permissions = $this->getPermission($parentPath);

		/* Another process can create the directory between createBasePath() finding it missing and
		 * this call; that counts as created, with whatever permissions that process chose. The
		 * warning is suppressed so a handler converting warnings to exceptions cannot make it
		 * fatal. */
		if (!@mkdir($basePath, $permissions, true)) {
			return is_dir($basePath);
		}

		/* Check if umask modified the permissions and reset any created directories */
		if (($permissions & ~umask()) !== $permissions) {
			$basePath = realpath($basePath);
			$folders = explode('/', substr($basePath, strlen($parentPath) + 1));
			for ($i = 1, $total = count($folders); $i <= $total; $i++) {
				$path = $parentPath . '/';
				$path .= implode('/', array_slice($folders, 0, $i));

				chmod($path, $permissions);
			}
		}

		return true;
	}

	protected function getExistingParentDirectory($basePath)
	{
		$targetParent = dirname($basePath);
		while ($targetParent !== '.' && ! is_dir($targetParent) && dirname($targetParent) !== $targetParent) {
			$targetParent = dirname($targetParent);
		}

		return realpath($targetParent);
	}

	protected function getPermission($basePath, $fallbackPermission = 0777)
	{
		if (! is_dir($basePath)) {
			return $fallbackPermission;
		}

		$result = fileperms($basePath);

		return $result ? $result & 0007777 : $fallbackPermission;
	}

	public function tempFilename($filename)
	{
		return $this->getFilePath($filename);
	}

	public function has($filename)
	{
		return file_exists($this->getFilePath($filename));
	}

	public function load($filename)
	{
		return file_get_contents($this->getFilePath($filename));
	}

	/**
	 * The entry's contents, or null where it is not there.
	 *
	 * A caller that asks has() and then load() is handed nothing in place of the entry it had just seen
	 * where another process's clearOld() expires it in between. Reporting the miss instead lets the
	 * caller take the path it takes when has() is false.
	 *
	 * The has() below is what makes an entry this cache has never held raise no diagnostic at all, which
	 * a lone suppressed read cannot: @ leaves an error handler to be called with error_reporting() at
	 * zero, and a handler that does not consult it sees the warning. Only the read that loses the race
	 * inside the two calls is suppressed, so that a handler converting warnings to exceptions cannot
	 * make a cache miss fatal.
	 */
	public function loadIfPresent($filename)
	{
		if (!$this->has($filename)) {
			return null;
		}

		$contents = @file_get_contents($this->getFilePath($filename));

		return false === $contents ? null : $contents;
	}

	public function write($filename, $data)
	{
		$tempFile = tempnam($this->basePath, 'cache_tmp_');
		file_put_contents($tempFile, $data);
		chmod($tempFile, 0664);

		$path = $this->getFilePath($filename);
		rename($tempFile, $path);

		return $path;
	}

	public function remove($filename)
	{
		return unlink($this->getFilePath($filename));
	}

	public function clearOld()
	{
		$iterator = new DirectoryIterator($this->basePath);

		/** @var \DirectoryIterator $item */
		foreach ($iterator as $item) {
			if (!$item->isDot()
					&& $item->isFile()
					&& !$this->isDotFile($item)
					&& $this->isOld($item)) {
				unlink($item->getPathname());
			}
		}
	}

	private function getFilePath($filename)
	{
		return $this->basePath . '/' . $filename;
	}

	private function isOld(DirectoryIterator $item)
	{
		return $this->cleanupInterval
			? $item->getMTime() + $this->cleanupInterval < time()
			: false;
	}

	public function isDotFile(DirectoryIterator $item)
	{
		return substr($item->getFilename(), 0, 1) === '.';
	}
}
