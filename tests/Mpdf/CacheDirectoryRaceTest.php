<?php

namespace Mpdf;

class CacheDirectoryRaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const PROCESSES = 4;

	private $workDir;

	protected function set_up()
	{
		parent::set_up();

		$this->workDir = sys_get_temp_dir() . '/mpdf-cache-race-' . uniqid('', true);
		mkdir($this->workDir, 0777, true);
		chmod($this->workDir, 0777);
	}

	protected function tear_down()
	{
		foreach (new \DirectoryIterator($this->workDir) as $item) {
			if ($item->isDot()) {
				continue;
			}

			if ($item->isDir()) {
				rmdir($item->getPathname());
				continue;
			}

			unlink($item->getPathname());
		}

		rmdir($this->workDir);

		parent::tear_down();
	}

	public function testADirectoryCreatedAfterItWasFoundMissingCountsAsCreated()
	{
		$dir = $this->workDir . '/mpdf';

		new RaceLosingCache($dir);

		$this->assertDirectoryExists($dir);
	}

	public function testTheLostRaceRaisesNoWarningForAnErrorHandlerToConvert()
	{
		$dir = $this->workDir . '/mpdf';

		/* The handler from mpdf/mpdf#1775's stack trace, written as the PHP manual writes it. */
		set_error_handler(function ($severity, $message) {
			if (!(error_reporting() & $severity)) {
				return false;
			}

			throw new \ErrorException($message, 0, $severity);
		});

		try {
			new RaceLosingCache($dir);
		} finally {
			restore_error_handler();
		}

		$this->assertDirectoryExists($dir);
	}

	public function testADirectoryAnotherProcessCreatedKeepsThePermissionsThatProcessGaveIt()
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			$this->markTestSkipped('Windows does not honour directory permissions');
		}

		$dir = $this->workDir . '/mpdf';

		/* A umask that the parent's 0777 does not survive, so the correcting chmod() would run. */
		$oldUmask = umask(0022);

		try {
			new RaceLosingCache($dir, 0700);
		} finally {
			umask($oldUmask);
		}

		$this->assertSame(0700, fileperms($dir) & 0777);
	}

	/**
	 * The race itself, with real processes. Where they happen to arrive at mkdir() one after
	 * another the test passes without having raced, so it can miss the bug but never invent it.
	 */
	public function testProcessesThatAllFindTheDirectoryMissingAllGetTheCache()
	{
		$children = [];

		for ($i = 0; $i < self::PROCESSES; $i++) {
			$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr '
				. escapeshellarg(__DIR__ . '/Fixtures/cache-directory-race.php') . ' '
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
			$this->assertSame("ok\n", $output, 'Process ' . $i . ' did not get the cache. ' . $errors);
			$this->assertStringNotContainsString('mkdir(', $errors, 'Process ' . $i . ' reported');
		}
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
}
