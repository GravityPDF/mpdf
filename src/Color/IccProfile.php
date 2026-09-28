<?php

namespace Mpdf\Color;

use Mpdf\Strict;

/**
 * The header of an ICC profile, which ICC.1 7.2 lays out: the device class at byte 12 and the data colour space at
 * byte 16
 */
class IccProfile
{

	use Strict;

	/**
	 * @var string The first 20 bytes of the profile, or nothing where it cannot be read
	 */
	private $header;

	/**
	 * @param string $path
	 */
	public function __construct($path)
	{
		$this->header = is_readable($path) ? (string) file_get_contents($path, false, null, 0, 20) : '';
	}

	/**
	 * @return bool
	 */
	public function isReadable()
	{
		return $this->header !== '';
	}

	/**
	 * @return string The device class, e.g. 'prtr' for a printer or 'mntr' for a display
	 */
	public function deviceClass()
	{
		return (string) substr($this->header, 12, 4);
	}

	/**
	 * @return string The data colour space, e.g. 'RGB ' or 'CMYK'
	 */
	public function colorSpace()
	{
		return (string) substr($this->header, 16, 4);
	}

	/**
	 * @return bool Whether the profile is of grey, RGB or CMYK, the colour spaces an output intent may print to under
	 *              PDF/A and PDF/X
	 */
	public function isGreyRgbOrCmyk()
	{
		return in_array($this->colorSpace(), ['GRAY', 'RGB ', 'CMYK'], true);
	}

	/**
	 * @return int|null The /N of a stream embedding the profile (ISO 32000-1 Table 66): 1 for grey, 3 for RGB or Lab,
	 *                  4 for CMYK, and null for a colour space PDF does not permit or where it cannot be read
	 */
	public function channels()
	{
		$channels = ['GRAY' => 1, 'RGB ' => 3, 'Lab ' => 3, 'CMYK' => 4];
		$space = $this->colorSpace();

		return isset($channels[$space]) ? $channels[$space] : null;
	}

}
