<?php

namespace Mpdf;

use Mpdf\Fonts\FontFileFinder;

class RecordingFontFileFinder extends FontFileFinder
{

	public $lookups = [];

	/**
	 * @var string[]|null The only font files found, as a finder that has not fetched the rest would
	 *                    find them, or null for every file in the directories
	 */
	public $onDisk;

	/**
	 * Records the name, and finds the file unless it is left out of $onDisk
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function findFontFile($name)
	{
		$this->lookups[] = $name;

		if ($this->onDisk !== null && !in_array($name, $this->onDisk, true)) {
			throw new MpdfException(sprintf('Cannot find TTF TrueType font file "%s" in configured font directories.', $name));
		}

		return parent::findFontFile($name);
	}

}
