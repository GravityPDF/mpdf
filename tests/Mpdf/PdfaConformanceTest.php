<?php

namespace Mpdf;

/**
 * Documents mPDF writes as PDF/A-2 and PDF/A-3 pass veraPDF
 *
 * @group conformance
 */
class PdfaConformanceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use VeraPdf;

	/**
	 * @var string
	 */
	private $file;

	/**
	 * Name the document to validate
	 */
	protected function set_up()
	{
		$this->file = sys_get_temp_dir() . '/mpdf-pdfa-' . uniqid() . '.pdf';
	}

	/**
	 * Remove the document validated
	 */
	protected function tear_down()
	{
		if ($this->file && file_exists($this->file)) {
			unlink($this->file);
		}
	}

	/**
	 * A document with transparency, text outside Latin-1 and annotations conforms
	 *
	 * @dataProvider versions
	 */
	public function testDocumentConforms($version)
	{
		$img = __DIR__ . '/../data/img/';

		$mpdf = new Mpdf(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version]);
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->showWatermarkText = true;
		$mpdf->WriteHTML(
			'<bookmark content="Start" /><h1>PDF/A</h1>'
			. '<p style="font-family: dejavusans">Ελληνικά “quoted” <a href="https://example.com">link</a></p>'
			. '<div style="background: linear-gradient(rgba(255, 0, 0, 1), rgba(0, 0, 255, 0.2)); height: 10mm"></div>'
			. '<div style="border: 1mm solid rgba(0, 128, 0, 0.4); box-shadow: 1mm 1mm 1mm rgba(0, 0, 0, 0.5)">Borders</div>'
			. '<p>Notes <annotation content="Note" /> <annotation content="Popup" popup="true" /></p>'
			. '<img src="' . $img . 'pngpixels/rgba8-None.png" /> <img src="' . $img . 'pngpixels/la8-PNG.png" />'
			. '<img src="' . $img . 'truecolour-trns.png" /> <img style="opacity: 0.5" src="' . $img . 'tiger.jpg" width="20" />'
			. '<img src="' . $img . 'demo.svg" width="40" />'
		);
		$mpdf->Output($this->file, 'F');

		$this->assertConforms($this->file, strtolower(str_replace('-', '', $version)));
	}

	/**
	 * The PDF/A versions that allow transparency
	 *
	 * @return string[][]
	 */
	public function versions()
	{
		return [['2-B'], ['2-U'], ['3-B'], ['3-U']];
	}

}
