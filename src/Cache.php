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
		$path = $this->getFilePath($filename);

		/* A file another process removed first is removed; callers only ask that it be gone. The
		 * warning is suppressed so a handler converting warnings to exceptions cannot make that
		 * fatal. */
		return @unlink($path) || !file_exists($path);
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
				/* Every process sharing the cache lists the same expired files, so the file can be
				 * gone by the time this reaches it. That is the outcome asked for, and the warning
				 * is suppressed for the reason given in remove(). */
				@unlink($item->getPathname());
			}
		}
	}

	private function getFilePath($filename)
	{
		return $this->basePath . '/' . $filename;
	}

	protected function isOld(DirectoryIterator $item)
	{
		if (!$this->cleanupInterval) {
			return false;
		}

		/* Not $item->getMTime(), which SplFileInfo turns into a RuntimeException no error handler
		 * can decline where the file has gone since the directory was listed. A file that is not
		 * there is not expired, and it is already what clearOld() would have made of it. */
		$mtime = @filemtime($item->getPathname());

		return false !== $mtime && $mtime + $this->cleanupInterval < time();
	}

	public function isDotFile(DirectoryIterator $item)
	{
		return substr($item->getFilename(), 0, 1) === '.';
	}
}
