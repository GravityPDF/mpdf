<?php

namespace Mpdf;

use Mpdf\Writer\BaseWriter;

/**
 * A PDF/A output intent embeds a profile PDF/A permits for the colour the document writes, with /N counting its
 * components. Any other profile has the document refused, or under PDFAauto is replaced by the bundled sRGB or SWOP.
 */
class PdfaOutputIntentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;
	use IccProfiles;

	/**
	 * Makes a directory of the test's own for the profiles it writes
	 */
	public function set_up()
	{
		$this->makeProfileDir('mpdf-pdfa-intent');
	}

	/**
	 * Leaves no profile behind
	 */
	public function tear_down()
	{
		$this->removeProfileDir();
	}

	/**
	 * Without PDFAauto a CMYK document needs a CMYK profile and an RGB document an RGB one, or it is refused with
	 * a PDF/A warning saying why
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
				'This PDF/A document writes DeviceCMYK, which needs a CMYK output intent, and the sRGB profile used where ICCProfile is blank is not a CMYK profile. (The bundled SWOP2006_Coated3v2 profile will be used instead.)',
			],
			'CMYK document, RGB profile' => [
				['restrictColorSpace' => 3, 'ICCProfile' => BaseWriter::SRGB_PROFILE],
				'which needs a CMYK output intent, and ICCProfile "' . BaseWriter::SRGB_PROFILE . '" is not a CMYK profile.',
			],
			'RGB document, CMYK profile' => [
				['ICCProfile' => Mpdf::PDFX4_OUTPUT_PROFILE],
				'This PDF/A document writes DeviceRGB, which needs an RGB output intent, and ICCProfile "' . Mpdf::PDFX4_OUTPUT_PROFILE . '" is not an RGB profile. (The bundled sRGB_IEC61966-2-1 profile will be used instead.)',
			],
			'RGB document, grey profile' => [
				['ICCProfile' => BaseWriter::GRAY_PROFILE],
				'which needs an RGB output intent, and ICCProfile "' . BaseWriter::GRAY_PROFILE . '" is not an RGB profile.',
			],
		];
	}

	/**
	 * PDF/A takes only a printer or monitor profile of grey, RGB or CMYK as the output intent, so a Lab profile and
	 * an RGB input (scanner) profile are refused
	 *
	 * @dataProvider profilesOfAnotherKind
	 *
	 * @param string $space
	 * @param string $class
	 * @param string $warning With %s for the profile's path
	 */
	public function testAProfileOfAnotherKindIsRefused($space, $class, $warning)
	{
		$profile = $this->writeProfile('refused', $space, $class);

		$this->assertStringContainsString(sprintf($warning, $profile), implode("\n", $this->warnings(['ICCProfile' => $profile])));
	}

	/**
	 * @return string[][] The colour space and class of a Lab and an input profile, with the warning each is refused with
	 */
	public function profilesOfAnotherKind()
	{
		return [
			'Lab' => ['Lab ', 'mntr', 'The PDF/A output intent must be a grey, RGB or CMYK profile, and ICCProfile "%s" is not.'],
			'scanner' => ['RGB ', 'scnr', 'The PDF/A output intent must be a printer (prtr) or monitor (mntr) profile, and ICCProfile "%s" is of the scnr class.'],
		];
	}

	/**
	 * A greyscale document writes DeviceGray, which PDF/A permits under any output intent, so it embeds a grey, RGB
	 * or CMYK profile as named
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
		$this->assertProfileStream($pdf, $channels, $profile);
	}

	/**
	 * @return mixed[][] The bundled profiles with the number of components of each
	 */
	public function greyDocumentProfiles()
	{
		return [
			'grey' => [BaseWriter::GRAY_PROFILE, 1],
			'RGB' => [BaseWriter::SRGB_PROFILE, 3],
			'CMYK' => [Mpdf::PDFX4_OUTPUT_PROFILE, 4],
		];
	}

	/**
	 * Under PDFAauto a CMYK document naming no CMYK profile embeds the bundled SWOP profile as CGATS TR 003
	 *
	 * @dataProvider cmykDocumentsWithoutACmykProfile
	 *
	 * @param array $config
	 */
	public function testPdfaAutoGivesACmykDocumentTheBundledSwopProfile(array $config)
	{
		$pdf = $this->write($config + ['PDFA' => true, 'PDFAauto' => true, 'restrictColorSpace' => 3]);

		$this->assertStringContainsString('/OutputConditionIdentifier (CGATS TR 003)', $pdf);
		$this->assertProfileStream($pdf, 4, Mpdf::PDFX4_OUTPUT_PROFILE);
	}

	/**
	 * @return array[][] CMYK documents naming no profile, and naming an RGB one
	 */
	public function cmykDocumentsWithoutACmykProfile()
	{
		return ['no profile' => [[]], 'RGB profile' => [['ICCProfile' => BaseWriter::SRGB_PROFILE]]];
	}

	/**
	 * Under PDFAauto an RGB document naming a profile PDF/A refuses embeds the bundled sRGB profile instead
	 *
	 * @dataProvider profilesAnRgbDocumentRefuses
	 *
	 * @param string $space
	 * @param string $class
	 */
	public function testPdfaAutoGivesAnRgbDocumentTheBundledSrgbProfile($space, $class)
	{
		$pdf = $this->write(['PDFA' => true, 'PDFAauto' => true, 'ICCProfile' => $this->writeProfile('refused', $space, $class)]);

		$this->assertStringContainsString('/OutputConditionIdentifier (sRGB IEC61966-2.1)', $pdf);
		$this->assertProfileStream($pdf, 3, BaseWriter::SRGB_PROFILE);
	}

	/**
	 * @return string[][] The colour space and class of a CMYK, a Lab and an RGB input profile
	 */
	public function profilesAnRgbDocumentRefuses()
	{
		return ['CMYK' => ['CMYK', 'prtr'], 'Lab' => ['Lab ', 'mntr'], 'scanner' => ['RGB ', 'scnr']];
	}

	/**
	 * PDFAauto does not replace a profile that cannot be found, so a mistyped path is still an error
	 */
	public function testPdfaAutoRefusesAProfileThatCannotBeFound()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Unable to find ICC profile "' . $this->dir . '/missing.icc"');

		$this->write(['PDFA' => true, 'PDFAauto' => true, 'ICCProfile' => $this->dir . '/missing.icc']);
	}

	/**
	 * The profile stream's /N is the number of components of the profile embedded
	 *
	 * @dataProvider profiles
	 *
	 * @param array $config
	 * @param int   $channels
	 */
	public function testTheProfileStreamCountsTheProfilesComponents(array $config, $channels)
	{
		$profile = isset($config['ICCProfile']) ? $config['ICCProfile'] : BaseWriter::SRGB_PROFILE;

		$this->assertProfileStream($this->write($config), $channels, $profile);
	}

	/**
	 * @return mixed[][] Configurations with the number of components of the profile each embeds
	 */
	public function profiles()
	{
		return [
			'PDF/A, sRGB by default' => [['PDFA' => true], 3],
			'PDF/A, RGB named' => [['PDFA' => true, 'ICCProfile' => BaseWriter::SRGB_PROFILE], 3],
			'PDF/A, CMYK' => [['PDFA' => true, 'restrictColorSpace' => 3, 'ICCProfile' => Mpdf::PDFX4_OUTPUT_PROFILE], 4],
			'grey, outside PDF/A' => [['ICCProfile' => BaseWriter::GRAY_PROFILE], 1],
		];
	}

	/**
	 * ISO 32000-1 permits a Lab profile outside PDF/A, embedded with /N 3
	 */
	public function testALabProfileOutsidePdfaCountsThreeComponents()
	{
		$profile = $this->writeProfile('lab', 'Lab ', 'mntr');

		$this->assertProfileStream($this->write(['ICCProfile' => $profile]), 3, $profile);
	}

	/**
	 * @param string $pdf
	 * @param int    $channels The /N the profile stream should have
	 * @param string $profile  The profile it should embed, found by its length
	 */
	private function assertProfileStream($pdf, $channels, $profile)
	{
		$this->assertStringContainsString("<<\n/N " . $channels . "\n/Length " . filesize($profile) . '>>', $pdf);
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

}
