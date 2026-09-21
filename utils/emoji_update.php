<?php

/**
 * Rewrites the emoji property tables in src/Unicode/Emoji.php from Unicode's emoji-data.txt:
 *
 *   composer emoji:update [<version>]
 *
 * The version defaults to the one Mpdf\Unicode\EmojiTables names, which is the one Ucdn's tables were built
 * from. The file is read from unicode.org once and kept under utils/data/ucd/<version>, the same place
 * composer ucdn:update keeps its files, so a second run is offline.
 *
 * Read tests/Mpdf/Unicode/EmojiTables.php before trusting a diff.
 */

require __DIR__ . '/../vendor/autoload.php';

$version = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : Mpdf\Unicode\EmojiTables::DEFAULT_VERSION;
$file = __DIR__ . '/../src/Unicode/Emoji.php';

$tables = new Mpdf\Unicode\EmojiTables($version);
$written = $tables->rewrite($file);

foreach ($written as $name => $count) {
	printf("Unicode %s: %s, %d ranges\n", $version, $name, $count);
}
