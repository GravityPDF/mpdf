<?php

/*
 * Writes the given HTML and prints the exception message and UTF-8 log entries that came of it,
 * as JSON. The time limit, in a process of its own, turns a loop that makes no progress into a
 * failed test instead of a test run that never ends. It counts CPU time on Linux and wall-clock
 * time on Windows, and either catches a busy loop.
 *
 * Usage: php purify-utf8.php <html as hex> [swallow-errors]
 */

require __DIR__ . '/../../../vendor/autoload.php';

$logger = new \Mpdf\TestLogger();
$mpdf = new \Mpdf\Mpdf();
$mpdf->setLogger($logger);

if (isset($argv[2])) {
	set_error_handler(function () {
		return true;
	});
}

set_time_limit(5);

$exception = null;

try {
	$mpdf->WriteHTML(hex2bin($argv[1]));
} catch (\Mpdf\MpdfException $e) {
	$exception = $e->getMessage();
}

$log = [];

foreach ($logger->records as $record) {
	if ($record['context']['context'] === \Mpdf\Log\Context::UTF8) {
		$log[] = $record['message'];
	}
}

echo json_encode(['exception' => $exception, 'log' => $log]);
