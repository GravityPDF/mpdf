<?php

/*
 * Builds a Cache on <workDir>/mpdf. Several of these are started at once and released together so
 * that they all find the directory missing and race to create it: each announces itself by writing
 * <workDir>/ready.<n>, then waits for <workDir>/start. Prints "ok", or the exception it caught.
 *
 * Usage: php cache-directory-race.php <workDir> <n>
 */

require __DIR__ . '/../../../vendor/autoload.php';

$workDir = $argv[1];

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
	new \Mpdf\Cache($workDir . '/mpdf');
	echo "ok\n";
} catch (\Exception $e) {
	echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
