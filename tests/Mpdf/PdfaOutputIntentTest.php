<?php

namespace Mpdf;

/**
 * The profile a PDF/A output intent embeds is of the colour space the document writes in, and its stream's /N counts
 * that profile's components
 */
class PdfaOutputIntentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The bundled profiles: sRGB, SWOP (CMYK) and a grey one
	 */
	const SRGB = __DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc';
	const CMYK = __DIR__ . '/../../data/iccprofiles/SWOP2006_Coated3v2.icc';
	const GRAY = __DIR__ . '/../../data/iccprofiles/Gray_sRGB_TRC.icc';

	/**
	 * A document restricted to CMYK writes DeviceCMYK, which PDF/A permits only under a CMYK output intent. mPDF
	 * assumes no CMYK printing condition, so a document naming no profile is refused rather than given sRGB.
	 */
	public function testACmykDocumentWithoutAProfileIsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('A PDF/A document restricted to CMYK (restrictColorSpace 3) needs a CMYK output intent.');

		$this->write(['PDFA' => true, 'restrictColorSpace' => 3]);
	}

	/**
	 * An RGB profile is refused for a document restricted to CMYK
	 */
	public function testACmykDocumentRefusesAnRgbProfile()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('needs a CMYK output intent. Set ICCProfile to a CMYK profile, such as the data/iccprofiles/SWOP2006_Coated3v2.icc');

		$this->write(['PDFA' => true, 'restrictColorSpace' => 3, 'ICCProfile' => self::SRGB]);
	}

	/**
	 * A document not restricted to CMYK writes DeviceRGB, which PDF/A permits only under an RGB output intent, so a
	 * CMYK or grey profile is refused
	 *
	 * @dataProvider notRgbProfiles
	 *
	 * @param string $profile
	 */
	public function testAnRgbDocumentRefusesAProfileThatIsNotRgb($profile)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('A PDF/A document writes RGB colour unless restricted to CMYK, and ICCProfile "' . $profile . '" is not an RGB profile.');

		$this->write(['PDFA' => true, 'ICCProfile' => $profile]);
	}

	/**
	 * @return string[][] The bundled CMYK and grey profiles
	 */
	public function notRgbProfiles()
	{
		return ['CMYK' => [self::CMYK], 'grey' => [self::GRAY]];
	}

	/**
	 * The profile stream's /N is the number of components of the profile embedded: three for the default sRGB and
	 * for an RGB profile named, four for a CMYK profile, and one for a grey profile named outside PDF/A
	 *
	 * @dataProvider profiles
	 *
	 * @param array $config
	 * @param int   $channels
	 */
	public function testTheProfileStreamCountsTheProfilesComponents(array $config, $channels)
	{
		$profile = isset($config['ICCProfile']) ? $config['ICCProfile'] : self::SRGB;

		$this->assertStringContainsString("<<\n/N " . $channels . "\n/Length " . filesize($profile) . '>>', $this->write($config));
	}

	/**
	 * @return mixed[][] Configurations with the number of components of the profile each embeds
	 */
	public function profiles()
	{
		return [
			'PDF/A, sRGB by default' => [['PDFA' => true], 3],
			'PDF/A, RGB named' => [['PDFA' => true, 'ICCProfile' => self::SRGB], 3],
			'PDF/A, CMYK' => [['PDFA' => true, 'restrictColorSpace' => 3, 'ICCProfile' => self::CMYK], 4],
			'grey, outside PDF/A' => [['ICCProfile' => self::GRAY], 1],
		];
	}

	/**
	 * @param array $config Merged over automatic fixing, in a mode that embeds its fonts
	 *
	 * @return string A document of red text
	 */
	private function write(array $config)
	{
		return $this->render('<p style="color: red">Red</p>', $config + ['mode' => '', 'PDFAauto' => true]);
	}

}
