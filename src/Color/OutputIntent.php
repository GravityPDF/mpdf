<?php

namespace Mpdf\Color;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Writer\BaseWriter;

/**
 * The ICC profile a document's output intent embeds, and what PDF/A permits of it
 */
class OutputIntent
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Color\IccProfile[] Each profile read, by path
	 */
	private $profiles = [];

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 */
	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * @return string|null For PDF/X see Mpdf::pdfxOutputProfile(), otherwise ICCProfile, or the bundled sRGB profile
	 *                     where it names none. Under PDFAauto a profile PDF/A refuses is replaced by pdfaDefaultProfile().
	 */
	public function profile()
	{
		if ($this->mpdf->PDFX) {
			return $this->mpdf->pdfxOutputProfile();
		}

		$profile = $this->mpdf->ICCProfile ?: BaseWriter::SRGB_PROFILE;
		if ($this->mpdf->PDFA && $this->mpdf->PDFAauto && $this->pdfaProblem($profile) !== null) {
			return $this->pdfaDefaultProfile();
		}

		return $profile;
	}

	/**
	 * @throws \Mpdf\MpdfException Where the profile is of a colour space PDF does not permit
	 *
	 * @return int The /N of the profile stream
	 */
	public function channels()
	{
		if ($this->mpdf->PDFX) {
			return $this->mpdf->pdfxOutputChannels();
		}

		$profile = $this->profile();
		$channels = $this->icc($profile)->channels();
		if ($channels === null) {
			throw new MpdfException(sprintf('The output intent must be a grey, RGB, CMYK or Lab profile, and ICCProfile "%s" is not.', $profile));
		}

		return $channels;
	}

	/**
	 * Adds a PDF/A warning, which refuses the document, where PDF/A does not permit the output intent profile. Under
	 * PDFAauto profile() has already replaced such a profile.
	 */
	public function checkPdfa()
	{
		if (!$this->mpdf->PDFA || $this->mpdf->PDFAauto || $this->mpdf->PDFX) {
			return;
		}

		$problem = $this->pdfaProblem($this->profile());
		if ($problem !== null) {
			$this->mpdf->PDFAXwarnings[] = $problem;
		}
	}

	/**
	 * @return string The bundled SWOP (CMYK) profile for a document restricted to CMYK, and sRGB otherwise
	 */
	private function pdfaDefaultProfile()
	{
		return $this->mpdf->restrictColorSpace == ColorSpaceRestrictor::RESTRICT_TO_CMYK_SPOT_GRAYSCALE
			? Mpdf::PDFX4_OUTPUT_PROFILE
			: BaseWriter::SRGB_PROFILE;
	}

	/**
	 * ISO 19005-2 6.2.3 (19005-1 6.2.2): the output intent is a printer or monitor profile of grey, RGB or CMYK.
	 * 19005-2 6.2.4.3 (19005-1 6.2.3.3): DeviceCMYK needs a CMYK output intent, DeviceRGB an RGB one, DeviceGray any.
	 * mPDF writes DeviceCMYK restricted to CMYK, DeviceGray restricted to greyscale, and DeviceRGB otherwise.
	 *
	 * @param string $profile The path to an ICC profile
	 *
	 * @return string|null Why PDF/A refuses the profile, or null where it permits it or the profile cannot be read,
	 *                     which the writer refuses anyway
	 */
	private function pdfaProblem($profile)
	{
		$icc = $this->icc($profile);
		if (!$icc->isReadable()) {
			return null;
		}

		$name = $this->mpdf->ICCProfile ? sprintf('ICCProfile "%s"', $profile) : 'the sRGB profile used where ICCProfile is blank';
		$instead = sprintf('(The bundled %s profile will be used instead.)', basename($this->pdfaDefaultProfile(), '.icc'));

		if ($icc->deviceClass() !== 'prtr' && $icc->deviceClass() !== 'mntr') {
			return sprintf('The PDF/A output intent must be a printer (prtr) or monitor (mntr) profile, and %s is of the %s class. %s', $name, trim($icc->deviceClass()), $instead);
		}
		if (!$icc->isGreyRgbOrCmyk()) {
			return sprintf('The PDF/A output intent must be a grey, RGB or CMYK profile, and %s is not. %s', $name, $instead);
		}
		if ($this->mpdf->restrictColorSpace == ColorSpaceRestrictor::RESTRICT_TO_GRAYSCALE) {
			return null;
		}

		list($device, $space, $needs) = $this->mpdf->restrictColorSpace == ColorSpaceRestrictor::RESTRICT_TO_CMYK_SPOT_GRAYSCALE
			? ['DeviceCMYK', 'a CMYK', 4]
			: ['DeviceRGB', 'an RGB', 3];
		if ($icc->channels() !== $needs) {
			return sprintf('This PDF/A document writes %s, which needs %s output intent, and %s is not %s profile. %s', $device, $space, $name, $space, $instead);
		}

		return null;
	}

	/**
	 * @param string $path
	 *
	 * @return \Mpdf\Color\IccProfile
	 */
	private function icc($path)
	{
		if (!isset($this->profiles[$path])) {
			$this->profiles[$path] = new IccProfile($path);
		}

		return $this->profiles[$path];
	}

}
