<?php

namespace Mpdf\Fonts;

use Psr\Log\NullLogger;

/**
 * A tempDir of the test's own, so the font cache starts empty, with a font directory inside it
 */
trait TempFontDirectory
{

	/**
	 * @var string The test's tempDir
	 */
	private $tempDir;

	/**
	 * @var string A directory the test writes fonts and ranges files into
	 */
	private $fontDir;

	/**
	 * @param string $name What the tempDir is named after
	 */
	private function makeTempFontDirectory($name)
	{
		$this->tempDir = sys_get_temp_dir() . '/mpdf-' . $name . '-' . uniqid('', true);
		$this->fontDir = $this->tempDir . '/fonts';
		mkdir($this->fontDir, 0777, true);
	}

	/**
	 * @param string $font The path of a font to copy into the font directory
	 */
	private function copyFont($font)
	{
		copy($font, $this->fontDir . '/' . basename($font));
	}

	/**
	 * Writes a PHP ranges file as mpdf-font-ranges writes one
	 *
	 * @param string  $path
	 * @param int[][] $ranges
	 * @param int     $size   The size of font it says it was written for
	 */
	private function writeRanges($path, array $ranges, $size = 1)
	{
		file_put_contents($path, $this->ranges($ranges, $size)->toPhp());
	}

	/**
	 * @param int[][] $ranges
	 * @param int     $size
	 *
	 * @return CharacterRanges
	 */
	private function ranges(array $ranges, $size = 1)
	{
		return CharacterRanges::fromData(['format' => CharacterRanges::FORMAT, 'size' => $size, 'ranges' => $ranges], 'test', new NullLogger());
	}

	/**
	 * Removes the tempDir and everything in it
	 */
	private function removeTempFontDirectory()
	{
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->tempDir);
	}

}
