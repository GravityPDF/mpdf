<?php

/**
 * Rewrites the generated Unicode tables in src/Unicode/Ucdn.php from a Unicode Character Database:
 *
 *   composer ucdn:update [<version>]
 *
 * The version defaults to the one Mpdf\Unicode\UcdnTables names. The files are read from unicode.org once
 * and kept under utils/data/ucd/<version>, so a second run is offline; they are not committed, being
 * larger between them than the table they produce.
 *
 * Read tests/Mpdf/Unicode/UcdnTables.php before trusting a diff - what is generated, what is left alone and
 * why, is written there.
 */

require __DIR__ . '/../vendor/autoload.php';

$version = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : Mpdf\Unicode\UcdnTables::DEFAULT_VERSION;
$file = __DIR__ . '/../src/Unicode/Ucdn.php';

$tables = new Mpdf\Unicode\UcdnTables($version);
$written = $tables->rewrite($file);

printf(
	"Unicode %s: %d records, %d scripts (%d new), %d mirror pairs, %d bytes written\n",
	$version,
	$written['records'],
	$written['scripts'],
	count($written['added']),
	$written['mirrors'],
	filesize($file)
);

if ($written['added']) {
	printf("New scripts: %s\n", implode(', ', array_keys($written['added'])));
}
