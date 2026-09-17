<?php

/*
 * Clears the expired files out of <workDir>/mpdf. Several of these are started at once and released
 * together so that they all list the same expired files and race to remove them: each announces
 * itself by writing <workDir>/ready.<n>, then waits for <workDir>/start. Warnings are turned into
 * exceptions the way the report behind mpdf/mpdf#1775 does, so a lost race is caught here rather
 * than printed. Prints "ok", or the exception it caught.
 *
 * Usage: php cache-expiry-race.php <workDir> <n>
 */

require __DIR__ . '/../../../vendor/autoload.php';

$workDir = $argv[1];

set_error_handler(function ($severity, $message) {
	if (!(error_reporting() & $severity)) {
		return false;
	}

	throw new \ErrorException($message, 0, $severity);
});

$cache = new \Mpdf\Cache($workDir . '/mpdf', 1);

touch($workDir . '/ready.' . $argv[2]);

$deadline = microtime(true) + 30;
while (!file_exists($workDir . '/start')) {
	if (microtime(true) > $deadline) {
		fwrite(STDERR, 'Gave up waiting for ' . $workDir . '/start' . PHP_EOL);
		exit(1);
	}

	usleep(100);
}

try {
	$cache->clearOld();
	echo "ok\n";
} catch (\Exception $e) {
	echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
