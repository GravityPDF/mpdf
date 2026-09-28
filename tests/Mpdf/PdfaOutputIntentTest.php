<?php

namespace Mpdf;

/**
 * The profile a PDF/A output intent embeds is one PDF/A permits for the colour the document writes, and its stream's
 * /N counts that profile's components. Without PDFAauto another profile has the document refused; under PDFAauto
 * the bundled sRGB or SWOP profile takes its place.
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
	 * @var string The directory the profiles each test writes go to
	 */
	private $dir;

	/**
	 * Makes a directory of the test's own for the profiles it writes
	 */
	public function set_up()
	{
		$this->dir = sys_get_temp_dir() . '/mpdf-pdfa-intent-' . uniqid('', true);
		mkdir($this->dir);
	}

	/**
	 * Leaves no profile behind
	 */
	public function tear_down()
	{
		foreach (glob($this->dir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->dir);
	}

	/**
	 * Without PDFAauto, a profile PDF/A does not permit for the document has it refused, with the reason among the
	 * PDF/A warnings: a CMYK document needs a CMYK profile, and an RGB document an RGB one
	 *
	 * @dataProvider refusedProfiles
	 *
	 * @param array  $config
	 * @param string $warning
	 */
	public function testAProfilePdfaDoesNotPermitHasTheDocumentRefused(array $config, $warning)
	{
		$this->assertStringContainsString($warning, implode("\n", $this->warnings($config)));
	}

	/**
	 * @return string[][] Configurations with the warning each is refused with
	 */
	public function refusedProfiles()
	{
		return [
			'CMYK document, no profile' => [
				['restrictColorSpace' => 3],
				'writes DeviceCMYK, which needs a CMYK output intent, and the sRGB profile used where ICCProfile is blank is not a CMYK profile. (The bundled SWOP2006_Coated3v2 (CMYK) profile will be used instead.)',
			],
			'CMYK document, RGB profile' => [
				['restrictColorSpace' => 3, 'ICCProfile' => self::SRGB],
				'writes DeviceCMYK, which needs a CMYK output intent, and ICCProfile "' . self::SRGB . '" is not a CMYK profile.',
			],
			'RGB document, CMYK profile' => [
				['ICCProfile' => self::CMYK],
				'which needs an RGB output intent, and ICCProfile "' . self::CMYK . '" is not an RGB profile. (The bundled sRGB profile will be used instead.)',
			],
			'RGB document, grey profile' => [
				['ICCProfile' => self::GRAY],
				'which needs an RGB output intent, and ICCProfile "' . self::GRAY . '" is not an RGB profile.',
			],
		];
	}

	/**
	 * PDF/A takes as the output intent a grey, RGB or CMYK profile alone, so a Lab profile is refused even where
	 * the document writes no device colour it would describe
	 */
	public function testALabProfileIsRefused()
	{
		$profile = $this->writeProfile('lab', 'Lab ', 'mntr');

		$this->assertStringContainsString(
			'The PDF/A output intent must be a grey, RGB or CMYK profile, and ICCProfile "' . $profile . '" is not.',
			implode("\n", $this->warnings(['ICCProfile' => $profile]))
		);
	}

	/**
	 * PDF/A takes as the output intent a printer or monitor profile alone, so an RGB input (scanner) profile is
	 * refused for an RGB document
	 */
	public function testAnInputProfileIsRefused()
	{
		$profile = $this->writeProfile('scanner', 'RGB ', 'scnr');

		$this->assertStringContainsString(
			'The PDF/A output intent must be a printer (prtr) or monitor (mntr) profile, and ICCProfile "' . $profile . '" is of the scnr class.',
			implode("\n", $this->warnings(['ICCProfile' => $profile]))
		);
	}

	/**
	 * A document restricted to greyscale writes DeviceGray, which PDF/A permits under any output intent, so a grey,
	 * RGB or CMYK profile is embedded as named, without PDFAauto
	 *
	 * @dataProvider greyDocumentProfiles
	 *
	 * @param string $profile
	 * @param int    $channels
	 */
	public function testAGreyscaleDocumentTakesAGreyRgbOrCmykProfile($profile, $channels)
	{
		$pdf = $this->write(['PDFA' => true, 'restrictColorSpace' => 1, 'ICCProfile' => $profile]);

		$this->assertStringContainsString('/OutputConditionIdentifier (Custom)', $pdf);
		$this->assertStringContainsString("<<\n/N " . $channels . "\n/Length " . filesize($profile) . '>>', $pdf);
	}

	/**
	 * @return mixed[][] The bundled profiles with the number of components of each
	 */
	public function greyDocumentProfiles()
	{
		return ['grey' => [self::GRAY, 1], 'RGB' => [self::SRGB, 3], 'CMYK' => [self::CMYK, 4]];
	}

	/**
	 * Under PDFAauto a CMYK document naming no CMYK profile embeds the bundled SWOP profile, with the printing
	 * condition CGATS TR 003 that characterises it, as a PDF/X-4 document naming none does
	 *
	 * @dataProvider cmykDocumentsWithoutACmykProfile
	 *
	 * @param array $config
	 */
	public function testPdfaAutoGivesACmykDocumentTheBundledSwopProfile(array $config)
	{
		$pdf = $this->write($config + ['PDFA' => true, 'PDFAauto' => true, 'restrictColorSpace' => 3]);

		$this->assertStringContainsString('/OutputConditionIdentifier (CGATS TR 003)', $pdf);
		$this->assertStringContainsString("<<\n/N 4\n/Length " . filesize(self::CMYK) . '>>', $pdf);
	}

	/**
	 * @return array[][] CMYK documents naming no profile, and naming an RGB one
	 */
	public function cmykDocumentsWithoutACmykProfile()
	{
		return ['no profile' => [[]], 'RGB profile' => [['ICCProfile' => self::SRGB]]];
	}

	/**
	 * Under PDFAauto an RGB document naming a profile PDF/A does not permit for it embeds the bundled sRGB profile,
	 * named as where ICCProfile is blank
	 *
	 * @dataProvider profilesAnRgbDocumentRefuses
	 *
	 * @param string|null $class The device class of the profile named, or null for the bundled CMYK profile
	 * @param string      $space The colour space of the profile named
	 */
	public function testPdfaAutoGivesAnRgbDocumentTheBundledSrgbProfile($class, $space)
	{
		$profile = $class === null ? self::CMYK : $this->writeProfile('refused', $space, $class);

		$pdf = $this->write(['PDFA' => true, 'PDFAauto' => true, 'ICCProfile' => $profile]);

		$this->assertStringContainsString('/OutputConditionIdentifier (sRGB IEC61966-2.1)', $pdf);
		$this->assertStringContainsString("<<\n/N 3\n/Length " . filesize(self::SRGB) . '>>', $pdf);
	}

	/**
	 * @return mixed[][] A CMYK profile, a Lab profile and an RGB input profile
	 */
	public function profilesAnRgbDocumentRefuses()
	{
		return ['CMYK' => [null, 'CMYK'], 'Lab' => ['mntr', 'Lab '], 'scanner' => ['scnr', 'RGB ']];
	}

	/**
	 * PDFAauto puts no bundled profile in place of one that cannot be found, which is refused as without PDF/A
	 */
	public function testPdfaAutoRefusesAProfileThatCannotBeFound()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Unable to find ICC profile "' . $this->dir . '/missing.icc"');

		$this->write(['PDFA' => true, 'PDFAauto' => true, 'ICCProfile' => $this->dir . '/missing.icc']);
	}

	/**
	 * The profile stream's /N is the number of components of the profile embedded: three for the default sRGB and
	 * for an RGB profile named, four for a CMYK profile, one for a grey profile named outside PDF/A, and three for a
	 * Lab profile, which ISO 32000-1 permits outside PDF/A
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
	 * A Lab profile named outside PDF/A is embedded with /N 3
	 */
	public function testALabProfileOutsidePdfaCountsThreeComponents()
	{
		$profile = $this->writeProfile('lab', 'Lab ', 'mntr');

		$this->assertStringContainsString("<<\n/N 3\n/Length " . filesize($profile) . '>>', $this->write(['ICCProfile' => $profile]));
	}

	/**
	 * @param array $config Merged over PDF/A without PDFAauto
	 *
	 * @return string[] The PDF/A warnings the document is refused with
	 */
	private function warnings(array $config)
	{
		$mpdf = $this->mpdf($config + ['mode' => '', 'PDFA' => true]);
		$mpdf->WriteHTML('<p>Text</p>');

		try {
			$this->output($mpdf);
		} catch (MpdfException $e) {
			$this->assertSame('PDFA/PDFX warnings generated. See log for further details', $e->getMessage());

			return $mpdf->PDFAXwarnings;
		}

		$this->fail('The document was not refused');
	}

	/**
	 * @param array $config Merged over a mode that embeds its fonts
	 *
	 * @return string A document of text
	 */
	private function write(array $config)
	{
		return $this->render('<p>Text</p>', $config + ['mode' => '']);
	}

	/**
	 * @param string $name  Names the file
	 * @param string $space The data colour space of the profile, e.g. 'RGB ' or 'Lab '
	 * @param string $class The device class, e.g. 'mntr' for a display or 'scnr' for an input device
	 *
	 * @return string The path of an ICC version 2.1 profile of that class and space that is a header alone
	 */
	private function writeProfile($name, $space, $class)
	{
		$path = $this->dir . '/' . $name . '.icc';

		$header = str_repeat("\0", 128);
		$header = substr_replace($header, pack('N', 0x02100000), 8, 4); // ICC version 2.1
		$header = substr_replace($header, $class, 12, 4); // device class
		$header = substr_replace($header, $space, 16, 4); // data colour space
		$header = substr_replace($header, 'Lab ', 20, 4); // profile connection space
		$header = substr_replace($header, 'acsp', 36, 4); // the file signature every profile carries

		$profile = $header . pack('N', 0); // a tag table of no tags
		file_put_contents($path, substr_replace($profile, pack('N', strlen($profile)), 0, 4));

		return $path;
	}

}
