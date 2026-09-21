<?php

/**
 * Rewrites Ucdn::$ot_languages, the language to OpenType language system table, from HarfBuzz's:
 *
 *   composer otlanguages:update [<version>]
 *
 * The version is a HarfBuzz release and defaults to the one Mpdf\OtLanguageTags names.
 * src/hb-ot-tag-table.hh is read from raw.githubusercontent.com once and kept under
 * utils/data/harfbuzz/<version>, so a second run is offline; it is not committed, being larger than
 * the table it produces and HarfBuzz's to version.
 *
 * Read tests/Mpdf/OtLanguageTags.php before trusting a diff - which of HarfBuzz's four tables becomes
 * what, and which of its rules are in Mpdf\Shaper\OtlTags instead, is written there.
 */

require __DIR__ . '/../vendor/autoload.php';

$version = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : Mpdf\OtLanguageTags::DEFAULT_VERSION;
$file = __DIR__ . '/../src/Unicode/Ucdn.php';

$tags = new Mpdf\OtLanguageTags($version);
$written = $tags->rewrite($file);

printf(
	"HarfBuzz %s: %d languages with %d tags, %d blocked codes, %d bytes written\n",
	$version,
	$written['languages'],
	$written['tags'],
	$written['blocked'],
	filesize($file)
);
