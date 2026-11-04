<?php

/**
 * Rewrites the character ranges file mPDF ships for each font it ships:
 *
 *   composer ranges:update
 *
 * Only the regular font of a family gets one, because font substitution reads no other.
 */

require __DIR__ . '/../vendor/autoload.php';

$command = new Mpdf\Fonts\CharacterRangesCommand();
$directory = Mpdf\Fonts\CharacterRanges::SHIPPED_RANGES_DIRECTORY;

if (!is_dir($directory)) {
	mkdir($directory);
}

foreach (array_unique(Mpdf\Fonts\CharacterRanges::shippedFonts()) as $font) {
	$font = Mpdf\Fonts\CharacterRanges::SHIPPED_FONT_DIRECTORY . '/' . $font;
	if (is_file($font)) {
		echo $command->write($font, $directory), "\n";
	}
}
