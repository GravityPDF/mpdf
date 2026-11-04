<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * What bin/mpdf-font-ranges runs: writes a PHP ranges file for each font file it is given, the
 * format mPDF reads from a font package.
 *
 *   mpdf-font-ranges [--into=<dir>] <font file>...
 */
class CharacterRangesCommand
{

	const USAGE = "Usage: mpdf-font-ranges [--into=<dir>] <font file>...\n\n"
		. "Writes <font file>.ranges.php beside each font, or into <dir>.\n"
		. "mPDF reads a .ranges.php file from the directory of the font package that registers the font.\n";

	/**
	 * @param string[] $arguments The command line, without the script's name
	 * @param resource $stdout    Told each file written
	 * @param resource $stderr    Told what went wrong
	 *
	 * @return int The exit status
	 */
	public function run(array $arguments, $stdout, $stderr)
	{
		$into = null;
		$fonts = [];

		foreach ($arguments as $argument) {
			if ($argument === '--help' || $argument === '-h') {
				fwrite($stdout, self::USAGE);

				return 0;
			}

			if (strpos($argument, '--into=') === 0) {
				$into = substr($argument, 7);
			} elseif (strpos($argument, '--') === 0) {
				fwrite($stderr, sprintf("Unknown option %s\n\n%s", $argument, self::USAGE));

				return 1;
			} else {
				$fonts[] = $argument;
			}
		}

		if (!$fonts) {
			fwrite($stderr, self::USAGE);

			return 1;
		}

		if ($into !== null && !is_dir($into)) {
			fwrite($stderr, sprintf("%s is not a directory\n", $into));

			return 1;
		}

		$status = 0;
		foreach ($fonts as $font) {
			if (!is_file($font)) {
				fwrite($stderr, sprintf("%s is not a file\n", $font));
				$status = 1;

				continue;
			}

			fwrite($stdout, $this->write($font, $into) . "\n");
		}

		return $status;
	}

	/**
	 * @return string[] The regular font file of each family the registered font packages provide, by
	 *                  family, whether or not it is installed
	 */
	public static function packagedFonts()
	{
		$fonts = [];
		foreach ((new FontRegistry())->getAll() as $package) {
			foreach ($package->getFonts() as $family => $fontData) {
				if (!empty($fontData['R'])) {
					$fonts[$family] = $package->getDirectory() . '/' . $fontData['R'];
				}
			}
		}

		return $fonts;
	}

	/**
	 * @param string      $font The font file
	 * @param string|null $into The directory to write to, or null for the font's own
	 *
	 * @return string The file written
	 */
	public function write($font, $into = null)
	{
		$path = rtrim($into === null ? dirname($font) : $into, '/\\') . '/' . basename($font) . '.ranges.php';

		if (file_put_contents($path, $this->rangesOf($font)->toPhp()) === false) {
			throw new \RuntimeException(sprintf('Could not write %s', $path));
		}

		return $path;
	}

	/**
	 * @param string $font The font file
	 *
	 * @return CharacterRanges The characters its cmap gives a width, as MetricsGenerator reads them
	 */
	public function rangesOf($font)
	{
		// Parsing without OpenType layout writes nothing to the font cache
		$ttf = new TTFontFile(new FontCache(new Cache(sys_get_temp_dir())), 'win');
		$ttf->getMetrics($font, basename($font));

		return CharacterRanges::fromWidths($ttf->charWidths, filesize($font), $ttf->colorFormats, $ttf->hasOutlines);
	}

}
