<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;

class FontCache
{

	private $memoryCache = [];

	private $cache;

	public function __construct(Cache $cache)
	{
		$this->cache = $cache;
	}

	public function tempFilename($filename)
	{
		return $this->cache->tempFilename($filename);
	}

	public function has($filename)
	{
		return $this->cache->has($filename);
	}

	public function jsonHas($filename)
	{
		return (isset($this->memoryCache[$filename]) || $this->has($filename));
	}

	public function load($filename)
	{
		return $this->cache->load($filename);
	}

	public function loadIfPresent($filename)
	{
		return $this->cache->loadIfPresent($filename);
	}

	public function jsonLoad($filename)
	{
		if (isset($this->memoryCache[$filename])) {
			return $this->memoryCache[$filename];
		}

		$this->memoryCache[$filename] = json_decode($this->load($filename), true);
		return $this->memoryCache[$filename];
	}

	/**
	 * The entry decoded, or null where it is not there.
	 *
	 * A read that came back with nothing is not remembered: the caller that answers a miss by generating
	 * the entry again has to be handed what it wrote, and not the same nothing for the rest of a document
	 * then drawn without the font's metrics. Nothing mPDF writes decodes to null, so a file that does is
	 * as unusable as a missing one and counts the same way.
	 */
	public function jsonLoadIfPresent($filename)
	{
		if (isset($this->memoryCache[$filename])) {
			return $this->memoryCache[$filename];
		}

		$contents = $this->cache->loadIfPresent($filename);
		$decoded = null === $contents ? null : json_decode($contents, true);

		if (null === $decoded) {
			return null;
		}

		$this->memoryCache[$filename] = $decoded;

		return $decoded;
	}

	public function write($filename, $data)
	{
		return $this->cache->write($filename, $data);
	}

	public function binaryWrite($filename, $data)
	{
		return $this->cache->write($filename, $data);
	}

	/**
	 * Forgets what was remembered of the entry, so a font regenerated over a stale entry reads back what
	 * was just written.
	 */
	public function jsonWrite($filename, $data)
	{
		unset($this->memoryCache[$filename]);

		return $this->cache->write($filename, json_encode($data));
	}

	public function remove($filename)
	{
		return $this->cache->remove($filename);
	}

	public function jsonRemove($filename)
	{
		if (isset($this->memoryCache[$filename])) {
			unset($this->memoryCache[$filename]);
		}

		$this->remove($filename);
	}
}
