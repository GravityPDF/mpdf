<?php

/*
 * Shapes two Arabic Behs with Otl's debugOTL trace on, and prints what the trace echoes. Run in a
 * process of its own because the trace ends with exit.
 *
 * Usage: php shaping-trace.php <tempDir>
 */

require __DIR__ . '/../../../vendor/autoload.php';

$tempDir = $argv[1];

$mpdf = new \Mpdf\Mpdf([
	'mode' => 'utf-8',
	'tempDir' => $tempDir,
	'fontDir' => [__DIR__ . '/../../data/ttf'],
	'fontdata' => ['joining' => [
		'R' => 'NotoSansArabic-Joining-Subset.ttf',
		'useOTL' => 0xFF,
	]],
	'default_font' => 'joining',
]);

$otl = new \Mpdf\Otl($mpdf, new \Mpdf\Fonts\FontCache(new \Mpdf\Cache($tempDir . '/mpdf/ttfontdata')));
$otl->debugOTL = true;
$otl->applyOTL("\xD8\xA8\xD8\xA8", 0xFF);
