<?php

namespace Mpdf\Pdf;

use Mpdf\Mpdf;
use Mpdf\Strict;

/**
 * The PDF version a document is written as, and each standard it is written to with the version applied
 *
 * A standard's version is spelt as mPDF's configuration spells it: '3-B' for PDF/A-3b, '1a' for PDF/X-1a:2003 and
 * '4' for PDF/X-4.
 */
class DocumentProfile
{

	use Strict;

	/**
	 * PDF/A (ISO 19005), for long-term archiving; its version is the part and conformance level, e.g. 3-B
	 *
	 * @see https://pdfa.org/resource/iso-19005-pdfa/
	 */
	const PDFA = 'PDF/A';

	/**
	 * PDF/X (ISO 15930), for print exchange; its version is the part, e.g. 1a for PDF/X-1a:2003 or 4 for PDF/X-4
	 *
	 * @see https://pdfa.org/resource/iso-15930-pdfx/
	 */
	const PDFX = 'PDF/X';

	/**
	 * @var string
	 */
	private $pdfVersion;

	/**
	 * @var string[]
	 */
	private $standards;

	/**
	 * @param string $pdfVersion The PDF version readers take the document to be, e.g. 1.7
	 * @param string[] $standards The version of each standard applied, by the standard, e.g. [DocumentProfile::PDFA => '3-B']
	 */
	public function __construct($pdfVersion, array $standards)
	{
		$this->pdfVersion = $pdfVersion;
		$this->standards = $standards;
	}

	/**
	 * The PDF version and standards of a document as its settings stand
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return self
	 */
	public static function fromMpdf(Mpdf $mpdf)
	{
		$standards = [];
		if ($mpdf->PDFA) {
			$standards[self::PDFA] = implode('-', $mpdf->pdfaConformance());
		}

		if ($mpdf->PDFX) {
			$standards[self::PDFX] = $mpdf->pdfxVersion();
		}

		// The catalog's /Version raises the header's to 1.7 for PDF/A-2 onwards, which ISO 32000-1 underlies, and for AES-256,
		// and to 1.6 for PDF/X-4, which PDF 1.6 underlies
		$raised = ($mpdf->PDFA && $mpdf->pdfaPart() !== '1') || $mpdf->encrypted;
		$version = $raised && version_compare($mpdf->pdf_version, '1.7', '<') ? '1.7' : $mpdf->pdf_version;
		if ($mpdf->isPdfx4() && version_compare($version, '1.6', '<')) {
			$version = '1.6';
		}

		return new self($version, $standards);
	}

	/**
	 * The PDF version readers take the document to be
	 *
	 * @return string e.g. 1.7
	 */
	public function getPdfVersion()
	{
		return $this->pdfVersion;
	}

	/**
	 * Each standard applied to the document, with the version applied
	 *
	 * @return string[] The version by the standard, e.g. [DocumentProfile::PDFA => '3-B']
	 */
	public function getStandards()
	{
		return $this->standards;
	}

	/**
	 * The version of a standard applied to the document
	 *
	 * @param string $standard e.g. DocumentProfile::PDFA
	 *
	 * @return string|null e.g. 3-B, or null when the standard is not applied
	 */
	public function getVersion($standard)
	{
		return isset($this->standards[$standard]) ? $this->standards[$standard] : null;
	}

	/**
	 * The name ISO gives the version of a standard applied to the document
	 *
	 * @param string $standard e.g. DocumentProfile::PDFA
	 *
	 * @return string|null e.g. PDF/A-3b or PDF/X-1a:2003, or null when the standard is not applied
	 */
	public function getLabel($standard)
	{
		$version = $this->getVersion($standard);
		if ($version === null) {
			return null;
		}

		if ($standard === self::PDFA) {
			list($part, $conformance) = explode('-', $version);

			return self::PDFA . '-' . $part . strtolower($conformance);
		}

		if ($standard === self::PDFX && $version === '1a') {
			return 'PDF/X-1a:2003';
		}

		return $standard . '-' . $version;
	}

	/**
	 * Whether the document is written to a standard, and to the version given if one is
	 *
	 * A version matches itself, and a PDF/A part matches each of its conformance levels: '3' matches '3-B' and '3-U'.
	 *
	 * @param string $standard e.g. DocumentProfile::PDFA
	 * @param string|null $version e.g. 3, or 3-B
	 *
	 * @return bool
	 */
	public function conformsTo($standard, $version = null)
	{
		$applied = $this->getVersion($standard);
		if ($applied === null) {
			return false;
		}

		if ($version === null) {
			return true;
		}

		return $applied === $version || strpos($applied, $version . '-') === 0;
	}

}
