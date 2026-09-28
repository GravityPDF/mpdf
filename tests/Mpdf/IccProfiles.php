<?php

namespace Mpdf;

/**
 * Writes ICC profiles that are a header alone, into a directory of the test's own.
 *
 * mPDF reads only a profile's header, to count its colour components and check its class, before embedding it whole,
 * so a header with no tags stands in for a real profile of any class and colour space.
 */
trait IccProfiles
{

	/**
	 * @var string A directory of this test's own, so that runs side by side cannot remove each other's files
	 */
	private $dir;

	/**
	 * Makes the directory the profiles are written to
	 *
	 * @param string $prefix Names the directory
	 */
	private function makeProfileDir($prefix)
	{
		$this->dir = sys_get_temp_dir() . '/' . $prefix . '-' . uniqid('', true);
		mkdir($this->dir);
	}

	/**
	 * Removes the directory and every file written to it
	 */
	private function removeProfileDir()
	{
		foreach (glob($this->dir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->dir);
	}

	/**
	 * @param string $name  Names the file
	 * @param string $space The data colour space of the profile, e.g. 'CMYK' or 'Lab '
	 * @param string $class The device class, e.g. 'prtr' for a printer, 'mntr' for a display or 'scnr' for a scanner
	 *
	 * @return string The path of an ICC version 2.1 profile of that class and space that is a header alone, carrying no tags
	 */
	private function writeProfile($name, $space, $class = 'prtr')
	{
		$path = $this->dir . '/mpdf-test-' . $name . '.icc';

		$header = str_repeat("\0", 128);
		$header = substr_replace($header, pack('N', 0x02100000), 8, 4); // ICC version 2.1
		$header = substr_replace($header, $class, 12, 4); // device class
		$header = substr_replace($header, $space, 16, 4); // data colour space
		$header = substr_replace($header, 'Lab ', 20, 4); // profile connection space
		$header = substr_replace($header, 'acsp', 36, 4); // the file signature every profile carries

		$profile = $header . pack('N', 0); // a tag table of no tags
		$profile = substr_replace($profile, pack('N', strlen($profile)), 0, 4);

		file_put_contents($path, $profile);

		return $path;
	}

}
