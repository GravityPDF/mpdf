<?php

/**
 * Rewrites the character ranges file each font package ships beside its regular fonts:
 *
 *   composer ranges:update
 *
 * Only the regular font of a family gets one, because font substitution reads no other.
 */

require __DIR__ . '/../vendor/autoload.php';

$command = new Mpdf\Fonts\CharacterRangesCommand();

foreach (array_unique(Mpdf\Fonts\CharacterRangesCommand::packagedFonts()) as $font) {
	if (is_file($font)) {
		echo $command->write($font), "\n";
	}
}
