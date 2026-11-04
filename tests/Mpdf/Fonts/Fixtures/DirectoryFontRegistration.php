<?php

namespace Mpdf\Fonts\Fixtures;

use Mpdf\Fonts\FontRegistration;

/**
 * A font package whose directory and fonts are what the test gives it
 */
class DirectoryFontRegistration extends FontRegistration
{

	/**
	 * @var string
	 */
	private $directory;

	/**
	 * @var array
	 */
	private $fonts;

	/**
	 * @param string $directory
	 * @param array  $fonts     As getFonts() returns them
	 */
	public function __construct($directory, array $fonts)
	{
		$this->directory = $directory;
		$this->fonts = $fonts;
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'directory:' . $this->directory;
	}

	/**
	 * @return string
	 */
	public function getDirectory()
	{
		return $this->directory;
	}

	/**
	 * @return array
	 */
	public function getFonts()
	{
		return $this->fonts;
	}

}
