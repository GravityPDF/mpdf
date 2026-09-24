<?php

namespace Mpdf;

use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * The version in the file header, which features used after the document began can raise
 */
class PdfHeaderVersionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use ObjectOffsets;

	/**
	 * A one page document written with the configuration, after $draw has drawn on its page
	 *
	 * @param array         $config
	 * @param callable|null $draw
	 *
	 * @return string
	 */
	private function document(array $config = [], $draw = null)
	{
		$mpdf = new Mpdf(array_merge(['mode' => 'c'], $config));
		$mpdf->AddPage();
		if ($draw) {
			$draw($mpdf);
		}
		$mpdf->WriteHTML('<p>Header version</p>');

		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * A draw callback that places the first page of a PDF declaring $version
	 *
	 * @param string $version
	 *
	 * @return callable
	 */
	private static function import($version)
	{
		return function (Mpdf $mpdf) use ($version) {
			$source = new Mpdf(['mode' => 'c', 'pdf_version' => $version]);
			$source->WriteHTML('<p>Imported</p>');
			$pdf = $source->Output('', Destination::STRING_RETURN);
			$source->cleanup();

			$mpdf->setSourceFile(StreamReader::createByString($pdf));
			$mpdf->useTemplate($mpdf->importPage(1));
		};
	}

	/**
	 * Each document and the header version it should be written with
	 */
	public function headerProvider()
	{
		return [
			'plain' => [[], null, '1.4'],
			'layers' => [[], function (Mpdf $mpdf) {
				$mpdf->BeginLayer(1);
				$mpdf->WriteText(10, 10, 'Layer');
				$mpdf->EndLayer();
			}, '1.5'],
			'visibility' => [[], function (Mpdf $mpdf) {
				$mpdf->SetVisibility('printonly');
				$mpdf->WriteText(10, 10, 'Print only');
				$mpdf->SetVisibility('visible');
			}, '1.5'],
			'an imported PDF 1.6 page' => [[], self::import('1.6'), '1.6'],
			'object streams' => [['useObjectStreams' => true, 'compress' => true], null, '1.5'],
			'layers do not lower a later version' => [['pdf_version' => '1.7'], function (Mpdf $mpdf) {
				$mpdf->BeginLayer(1);
				$mpdf->EndLayer();
			}, '1.7'],
			'a version longer than the one first written' => [[], function (Mpdf $mpdf) {
				$mpdf->pdf_version = '1.10';
			}, '1.10'],
			'PDF/A-1b refuses layers' => [['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B'], function (Mpdf $mpdf) {
				$mpdf->BeginLayer(1);
				$mpdf->EndLayer();
			}, '1.4'],
			'PDF/A-1b keeps 1.4 over an imported PDF 1.6 page' => [['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B'], self::import('1.6'), '1.4'],
			'PDF/X-1a keeps 1.4 over an imported PDF 1.6 page' => [['mode' => '', 'PDFX' => true, 'PDFXversion' => '1a', 'PDFXauto' => true], self::import('1.6'), '1.4'],
		];
	}

	/**
	 * The header carries the version the document's features asked for, and every object is still where the
	 * cross-reference says
	 *
	 * @dataProvider headerProvider
	 *
	 * @param array         $config
	 * @param callable|null $draw
	 * @param string        $version
	 */
	public function testHeaderCarriesTheVersion(array $config, $draw, $version)
	{
		$pdf = $this->document($config, $draw);

		$this->assertSame('%PDF-' . $version . "\n", substr($pdf, 0, strlen($version) + 6));

		$this->assertEachOffsetLandsOnItsObject($pdf);

		$mpdf = new Mpdf(['mode' => 'c']);
		$this->assertSame(1, $mpdf->setSourceFile(StreamReader::createByString($pdf)));
		$mpdf->cleanup();
	}

}
