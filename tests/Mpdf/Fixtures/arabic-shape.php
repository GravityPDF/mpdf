<?php

/*
 * Runs Arabic::shape() over each of a set of runs and prints, as JSON, each run's [hex, form] pairs
 * under its name. Run in a process of its own, under a time limit, so a shaper that never returns
 * ends here instead of in the suite.
 *
 * Usage: php arabic-shape.php <base64 of the JSON [{name: [hexes, rtlSUB]}, usetags, GDEF marks]>
 */

require __DIR__ . '/../../../vendor/autoload.php';

set_time_limit(5);
error_reporting(E_ERROR);

list($runs, $usetags, $marks) = json_decode(base64_decode($argv[1]), true);

$forms = [];
foreach ($runs as $name => $run) {
	list($hexes, $glyphs) = $run;

	$info = [];
	foreach ($hexes as $hex) {
		$info[] = ['hex' => $hex, 'uni' => hexdec($hex)];
	}

	\Mpdf\Shaper\Arabic::resolveJoining($info, $marks);
	\Mpdf\Shaper\Arabic::shape($info, $glyphs, $marks, $usetags, 'arab');

	foreach ($info as $char) {
		$forms[$name][] = [$char['hex'], $char['form']];
	}
}

echo json_encode($forms);
