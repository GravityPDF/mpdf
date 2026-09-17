<?php

namespace Mpdf;

class CacheExpiryRaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const PROCESSES = 4;

	const FILES = 300;

	private $workDir;

	private $cacheDir;

	protected function set_up()
	{
		parent::set_up();

		$this->workDir = sys_get_temp_dir() . '/mpdf-expiry-race-' . uniqid('', true);
		$this->cacheDir = $this->workDir . '/mpdf';

		mkdir($this->cacheDir, 0777, true);
		chmod($this->workDir, 0777);
		chmod($this->cacheDir, 0777);
	}

	protected function tear_down()
	{
		$this->emptyDirectory($this->cacheDir);
		rmdir($this->cacheDir);

		$this->emptyDirectory($this->workDir);
		rmdir($this->workDir);

		parent::tear_down();
	}

	public function testAnEntryThatGoesBeforeItsMtimeIsReadIsPassedOver()
	{
		$this->expire([ExpiryRaceLosingCache::BEFORE_THE_STAT, 'left-to-us']);

		/* No error handler here: SplFileInfo turns the failed stat behind getMTime() into a
		 * RuntimeException itself, so it reaches the caller whether one is installed or not. */
		$cache = new ExpiryRaceLosingCache($this->cacheDir, 1);
		$cache->clearOld();

		$this->assertSame([], $this->filesLeft());
	}

	public function testAFileAnotherProcessRemovedAfterItWasFoundExpiredIsNotAnError()
	{
		$this->expire([ExpiryRaceLosingCache::AFTER_THE_STAT, 'left-to-us']);

		$cache = new ExpiryRaceLosingCache($this->cacheDir, 1);

		$this->throwOnWarning();

		try {
			$cache->clearOld();
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $this->filesLeft());
	}

	public function testRemovingAFileAnotherProcessAlreadyRemovedCountsAsRemoved()
	{
		$cache = new Cache($this->cacheDir);

		$this->throwOnWarning();

		try {
			$removed = $cache->remove('already-gone');
		} finally {
			restore_error_handler();
		}

		$this->assertTrue($removed);
	}

	/**
	 * The race itself, with real processes. Where they happen to arrive at a file one after another
	 * the test passes without having raced, so it can miss the bug but never invent it.
	 */
	public function testProcessesThatAllListTheSameExpiredFilesAllClearTheCache()
	{
		$expired = [];
		for ($i = 0; $i < self::FILES; $i++) {
			$expired[] = 'ttfontdata' . $i;
		}

		$this->expire($expired);

		$children = [];

		for ($i = 0; $i < self::PROCESSES; $i++) {
			$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr '
				. escapeshellarg(__DIR__ . '/Fixtures/cache-expiry-race.php') . ' '
				. escapeshellarg($this->workDir) . ' ' . $i;

			$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
			$this->assertNotFalse($process, 'Could not start process ' . $i);

			$children[$i] = [$process, $pipes];
		}

		$this->waitForProcessesToBeReady();
		touch($this->workDir . '/start');

		foreach ($children as $i => $child) {
			list($process, $pipes) = $child;

			$output = stream_get_contents($pipes[1]);
			$errors = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);

			$this->assertSame(0, proc_close($process), $errors);
			$this->assertSame("ok\n", $output, 'Process ' . $i . ' did not clear the cache. ' . $errors);
			$this->assertStringNotContainsString('unlink(', $errors, 'Process ' . $i . ' reported');
			$this->assertStringNotContainsString('stat failed', $errors, 'Process ' . $i . ' reported');
		}

		$this->assertSame([], $this->filesLeft());
	}

	private function expire(array $filenames)
	{
		foreach ($filenames as $filename) {
			touch($this->cacheDir . '/' . $filename, time() - 3600);
		}
	}

	private function filesLeft()
	{
		$left = [];
		foreach (new \DirectoryIterator($this->cacheDir) as $item) {
			if (!$item->isDot()) {
				$left[] = $item->getFilename();
			}
		}

		sort($left);

		return $left;
	}

	private function throwOnWarning()
	{
		/* The handler from mpdf/mpdf#1775's stack trace, written as the PHP manual writes it. */
		set_error_handler(function ($severity, $message) {
			if (!(error_reporting() & $severity)) {
				return false;
			}

			throw new \ErrorException($message, 0, $severity);
		});
	}

	private function waitForProcessesToBeReady()
	{
		$deadline = microtime(true) + 15;

		for ($i = 0; $i < self::PROCESSES; $i++) {
			while (!file_exists($this->workDir . '/ready.' . $i) && microtime(true) < $deadline) {
				usleep(1000);
			}
		}
	}

	private function emptyDirectory($path)
	{
		foreach (new \DirectoryIterator($path) as $item) {
			if (!$item->isDot() && $item->isFile()) {
				unlink($item->getPathname());
			}
		}
	}
}
