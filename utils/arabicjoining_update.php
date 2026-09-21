<?php

/**
 * Rewrites the joining tables in src/Shaper/Arabic.php from a Unicode Character Database:
 *
 *   composer arabicjoining:update [<version>]
 *
 * The version defaults to the one Mpdf\ArabicJoining names, which is the one Ucdn's scripts were built
 * from. ArabicShaping.txt and extracted/DerivedJoiningType.txt are read from unicode.org once and kept
 * under utils/data/ucd/<version>, the same place composer ucdn:update keeps its files, so a second run
 * is offline; they are not committed, being larger than the tables they produce.
 *
 * Read tests/Mpdf/ArabicJoining.php before trusting a diff - which characters are written, and which of
 * the class's tables is not generated, is written there.
 */

require __DIR__ . '/../vendor/autoload.php';

$version = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : Mpdf\ArabicJoining::DEFAULT_VERSION;
$file = __DIR__ . '/../src/Shaper/Arabic.php';

$tables = new Mpdf\ArabicJoining($version);
$written = $tables->rewrite($file);

printf(
	"Unicode %s: %d left-joining, %d right-joining, %d transparent-joining characters, %d bytes written\n",
	$version,
	$written['leftJoining'],
	$written['rightJoining'],
	$written['transparent'],
	filesize($file)
);
