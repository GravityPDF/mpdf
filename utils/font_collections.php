<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\TTFontFileAnalysis;

/**
 * Prints what each TrueType collection file holds - .ttc, and .ttcf as macOS spells it.
 *
 *   php utils/font_collections.php [<directory>]
 *
 * Also runs over the web, taking the directory from the query string as "dir".
 *
 * Without a directory it reads every directory the registered font packages provide.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/font_directories.php';

$cli = PHP_SAPI === 'cli';
$argument = function ($position, $name, $default = '') use ($cli) {
	global $argv;

	if ($cli) {
		return isset($argv[$position]) ? $argv[$position] : $default;
	}

	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
};

$mpdf = new Mpdf();
$fontCache = new FontCache(new Cache($mpdf->tempDir . '/mpdf/ttfontdata'));

// Mpdf::$fontDir is private, and fonts ship as packages with a directory each rather than as one
// folder, so the directories come from the registry that finds them - unless one is named.
$ttfdirs = fontDirectories($argument(1, 'dir'));

$ttf = new TTFontFileAnalysis($fontCache, $mpdf->getFontDescriptor());

printf('Searching %d directories for .ttc/.ttcf font collections:' . "\n", count($ttfdirs));
foreach ($ttfdirs as $directory) {
	printf('  %s' . "\n", $directory);
}

$collections = 0;

foreach (fontFilesIn($ttfdirs) as $found) {
	list($ttfdir, $f) = $found;

	// ".ttcf" is five characters, so it needs its own length - read four back from the end of
	// "X.ttcf" and the comparison is against "ttcf", which never equals ".ttcf"
	$extension = strtolower($f);
	if (substr($extension, -4) !== '.ttc' && substr($extension, -5) !== '.ttcf') {
		continue;
	}

	$file = $ttfdir . '/' . $f;

	// A font mPDF cannot use - not Unicode encoded, or embedding forbidden - is one line of the
	// report, not the end of it. The point of the script is to survey a directory, and refusing
	// the whole directory over one file is what it used to do.
	try {
		$ttf->getTTCFonts($file);
	} catch (\Exception $e) {
		printf('Font collection file (%s) cannot be read: %s' . "\n\n", $file, $e->getMessage());
		continue;
	}

	$nf = $ttf->numTTCFonts;
	printf('Font collection file (%s) contains the following fonts:' . "\n", $file);

	for ($i = 1; $i <= $nf; $i++) {
		try {
			$ret = $ttf->extractCoreInfo($file, $i);
		} catch (\Exception $e) {
			printf('[%d] cannot be read: %s' . "\n", $i, $e->getMessage());
			continue;
		}

		$tfname = $ret[0];
		$bold = $ret[1];
		$italic = $ret[2];
		$fname = strtolower($tfname);
		$fname = preg_replace('/[ ()]/', '', $fname);
		$style = '';

		if ($bold) {
			$style .= 'Bold';
		}
		if ($italic) {
			$style .= 'Italic';
		}
		if (!$style) {
			$style = 'Regular';
		}

		printf('[%d] %s (%s) %s' . "\n", $i, $tfname, $fname, $style);
	}

	print("---------------\n\n");
	$collections++;
}

printf('Found and processed %d collections' . "\n", $collections);
