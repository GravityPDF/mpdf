<?php

/*
 * Renders the HTML to a finished PDF and prints the exception message and UTF-8 log entries as JSON.
 * It runs in its own process so the time limit fails a hang instead of stalling the test run.
 *
 * Usage: php purify-utf8.php <html as hex> [debug] [swallow-errors]
 */

require __DIR__ . '/../../../vendor/autoload.php';

$logger = new \Mpdf\TestLogger();
$mpdf = new \Mpdf\Mpdf(['debug' => in_array('debug', $argv, true)]);
$mpdf->setLogger($logger);

if (in_array('swallow-errors', $argv, true)) {
	set_error_handler(function () {
		return true;
	});
}

set_time_limit(5);

$exception = null;

try {
	$mpdf->WriteHTML(hex2bin($argv[1]));
	$mpdf->OutputBinaryData();
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
