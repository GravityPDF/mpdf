<?php

/**
 * Finds the font files the three font-reporting scripts examine.
 *
 * They were written when mPDF kept one ttfonts/ folder and read Mpdf::$fontDir to find it. Fonts
 * ship as packages now, each declaring its own directory through FontRegistrationInterface, and
 * $fontDir is private - so the directories come from the registry that finds them, which is the
 * same list the renderer resolves a font name against.
 */

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * @param string $override A single directory to read instead of the registered ones
 *
 * @return string[] Absolute paths, each without a trailing separator
 */
function fontDirectories($override = '')
{
	if ($override !== '') {
		return [rtrim($override, '/\\')];
	}

	$directories = [];
	foreach ((new FontRegistry())->getAll() as $package) {
		$directory = $package->getDirectory();
		if (is_dir($directory)) {
			$directories[rtrim($directory, '/\\')] = true;
		}
	}

	return array_keys($directories);
}

/**
 * Every file in the given directories, each paired with the directory holding it.
 *
 * Two packages can carry a file of the same name, so the pair is what identifies a font file -
 * the name alone is not enough to read it back.
 *
 * @param string[] $directories
 *
 * @return array[] [directory, filename] pairs
 */
function fontFilesIn($directories)
{
	$found = [];
	foreach ($directories as $directory) {
		$entries = scandir($directory);
		if ($entries === false) {
			continue;
		}

		sort($entries);
		foreach ($entries as $entry) {
			if ($entry !== '.' && $entry !== '..' && !is_dir($directory . '/' . $entry)) {
				$found[] = [$directory, $entry];
			}
		}
	}

	return $found;
}

/**
 * @param string[] $directories
 * @param string $file
 *
 * @return bool Whether any of the directories holds a file of that name
 */
function fontFileExists($directories, $file)
{
	foreach ($directories as $directory) {
		if (file_exists($directory . '/' . $file)) {
			return true;
		}
	}

	return false;
}
