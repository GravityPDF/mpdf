<?php

namespace Mpdf\Pdf;

use Mpdf\PageStreams;

class DocumentProfileTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Configurations, and the PDF version, standards and their names each writes
	 *
	 * @return mixed[]
	 */
	public function configProvider()
	{
		return [
			'plain' => [[], '1.4', [], []],
			'PDF/A-1b' => [['PDFA' => true, 'PDFAversion' => '1-B'], '1.4', [DocumentProfile::PDFA => '1-B'], [DocumentProfile::PDFA => 'PDF/A-1b']],
			'PDF/A-3u' => [['PDFA' => true, 'PDFAversion' => '3-u'], '1.7', [DocumentProfile::PDFA => '3-U'], [DocumentProfile::PDFA => 'PDF/A-3u']],
			'PDF/X-1a' => [['PDFX' => true], '1.4', [DocumentProfile::PDFX => '1a'], [DocumentProfile::PDFX => 'PDF/X-1a:2003']],
		];
	}

	/**
	 * The profile names each standard the document is written to, with its version, and the PDF version readers see
	 *
	 * @dataProvider configProvider
	 *
	 * @param mixed[] $config
	 * @param string $pdfVersion
	 * @param string[] $standards
	 * @param string[] $labels
	 */
	public function testNamesTheStandardsApplied($config, $pdfVersion, $standards, $labels)
	{
		$profile = DocumentProfile::fromMpdf($this->mpdf($config + ['mode' => '', 'PDFAauto' => true, 'PDFXauto' => true]));

		$this->assertSame($pdfVersion, $profile->getPdfVersion());
		$this->assertSame($standards, $profile->getStandards());
		foreach ([DocumentProfile::PDFA, DocumentProfile::PDFX] as $standard) {
			$this->assertSame(isset($labels[$standard]) ? $labels[$standard] : null, $profile->getLabel($standard));
		}
	}

	/**
	 * A PDF/A version applied, a version asked for, and whether the document conforms to it
	 *
	 * @return mixed[]
	 */
	public function conformsToProvider()
	{
		$cases = [];
		foreach (['3-B', '3-U'] as $applied) {
			$cases[$applied . ', any version'] = [$applied, null, true];
			$cases[$applied . ' against its part'] = [$applied, '3', true];
			$cases[$applied . ' against 3-B'] = [$applied, '3-B', $applied === '3-B'];
			$cases[$applied . ' against 3-U'] = [$applied, '3-U', $applied === '3-U'];
			$cases[$applied . ' against another part'] = [$applied, '2', false];
			$cases[$applied . ' against a part with no level'] = [$applied, '3-', false];
		}

		return $cases;
	}

	/**
	 * A version matches itself, and a PDF/A part matches each of its conformance levels but not the other's
	 *
	 * @dataProvider conformsToProvider
	 *
	 * @param string $applied
	 * @param string|null $version
	 * @param bool $conforms
	 */
	public function testConformsToAPdfaPartOrLevel($applied, $version, $conforms)
	{
		$profile = new DocumentProfile('1.7', [DocumentProfile::PDFA => $applied]);

		$this->assertSame($conforms, $profile->conformsTo(DocumentProfile::PDFA, $version));
		$this->assertSame($applied, $profile->getVersion(DocumentProfile::PDFA));
	}

	/**
	 * A standard the document is not written to matches no version, and has neither a version nor a name
	 */
	public function testConformsToNoStandardNotApplied()
	{
		$profile = new DocumentProfile('1.7', [DocumentProfile::PDFA => '3-B']);

		$this->assertFalse($profile->conformsTo(DocumentProfile::PDFX));
		$this->assertFalse($profile->conformsTo(DocumentProfile::PDFX, '1a'));
		$this->assertNull($profile->getVersion(DocumentProfile::PDFX));
		$this->assertNull($profile->getLabel(DocumentProfile::PDFX));
	}

}
