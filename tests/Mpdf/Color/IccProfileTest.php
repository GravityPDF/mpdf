<?php

namespace Mpdf\Color;

use Mpdf\IccProfiles;
use Mpdf\Mpdf;
use Mpdf\Writer\BaseWriter;

/**
 * Reads an ICC profile's device class and colour space from its header, and counts its components as /N does
 */
class IccProfileTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use IccProfiles;

	/**
	 * Makes a directory of the test's own for the profiles it writes
	 */
	public function set_up()
	{
		$this->makeProfileDir('mpdf-icc-profile');
	}

	/**
	 * Leaves no profile behind
	 */
	public function tear_down()
	{
		$this->removeProfileDir();
	}

	/**
	 * The bundled profiles are read as they are: sRGB a display profile, SWOP a CMYK printer profile
	 */
	public function testTheBundledProfilesAreRead()
	{
		$srgb = new IccProfile(BaseWriter::SRGB_PROFILE);
		$swop = new IccProfile(Mpdf::PDFX4_OUTPUT_PROFILE);

		$this->assertSame(['mntr', 'RGB ', 3], [$srgb->deviceClass(), $srgb->colorSpace(), $srgb->channels()]);
		$this->assertSame(['prtr', 'CMYK', 4], [$swop->deviceClass(), $swop->colorSpace(), $swop->channels()]);
	}

	/**
	 * Grey counts one component, RGB and Lab three, CMYK four, and a colour space PDF does not permit none; only Lab
	 * of these is not an output intent's grey, RGB or CMYK
	 *
	 * @dataProvider colorSpaces
	 *
	 * @param string   $space
	 * @param int|null $channels
	 * @param bool     $greyRgbOrCmyk
	 */
	public function testChannelsFollowTheColorSpace($space, $channels, $greyRgbOrCmyk)
	{
		$icc = new IccProfile($this->writeProfile('profile', $space));

		$this->assertSame($channels, $icc->channels());
		$this->assertSame($greyRgbOrCmyk, $icc->isGreyRgbOrCmyk());
	}

	/**
	 * @return mixed[][] Each colour space with its /N and whether it is grey, RGB or CMYK
	 */
	public function colorSpaces()
	{
		return [
			'grey' => ['GRAY', 1, true],
			'RGB' => ['RGB ', 3, true],
			'Lab' => ['Lab ', 3, false],
			'CMYK' => ['CMYK', 4, true],
			'XYZ' => ['XYZ ', null, false],
		];
	}

	/**
	 * A profile that cannot be read has no class, colour space or components
	 */
	public function testAProfileThatCannotBeReadIsEmpty()
	{
		$icc = new IccProfile($this->dir . '/missing.icc');

		$this->assertFalse($icc->isReadable());
		$this->assertSame(['', '', null], [$icc->deviceClass(), $icc->colorSpace(), $icc->channels()]);
	}

}
