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
	 * @var string[]
	 */
	private $files = [];

	/**
	 * Remove the documents written
	 */
	protected function tear_down()
	{
		foreach ($this->files as $file) {
			if (file_exists($file)) {
				unlink($file);
			}
		}
	}

	/**
	 * A document with transparency, text outside Latin-1 and annotations conforms, with an RGB or a CMYK output
	 * intent
	 *
	 * @dataProvider documents
	 */
	public function testDocumentConforms($version, $config)
	{
		$img = __DIR__ . '/../data/img/';

		$mpdf = new Mpdf($config + ['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version]);
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->showWatermarkText = true;
		$mpdf->WriteHTML(
			'<bookmark content="Start" /><h1>PDF/A</h1>'
			. '<p style="font-family: dejavusans">Ελληνικά “quoted” <a href="https://example.com">link</a></p>'
			. '<p style="color: cmyka(0, 100, 100, 0, 0.5)">Translucent CMYK</p>'
			. '<div style="background: linear-gradient(rgba(255, 0, 0, 1), rgba(0, 0, 255, 0.2)); height: 10mm"></div>'
			. '<div style="border: 1mm solid rgba(0, 128, 0, 0.4); box-shadow: 1mm 1mm 1mm rgba(0, 0, 0, 0.5)">Borders</div>'
			. '<p>Notes <annotation content="Note" /> <annotation content="Popup" popup="true" /></p>'
			. '<img src="' . $img . 'pngpixels/rgba8-None.png" /> <img src="' . $img . 'pngpixels/la8-PNG.png" />'
			. '<img src="' . $img . 'truecolour-trns.png" /> <img style="opacity: 0.5" src="' . $img . 'tiger.jpg" width="20" />'
			. '<img src="' . $img . 'demo.svg" width="40" />'
		);
		$file = $this->write($mpdf);

		$this->assertConforms($file, $this->flavour($version));
	}

	/**
	 * A document that attaches a PDF/A document and a plain PDF conforms, keeping only the PDF/A one
	 *
	 * @dataProvider pdfa2Versions
	 */
	public function testAttachmentsConform($version)
	{
		$attachment = $this->write(new Mpdf(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B']));

		$mpdf = new Mpdf(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version, 'allowAnnotationFiles' => true]);
		$mpdf->WriteHTML(
			'<p>PDF/A <annotation content="PDF/A" file="' . $attachment . '" /></p>'
			. '<p>Plain <annotation content="Plain" file="' . __DIR__ . '/../data/pdfs/2-Page-PDF_1_4.pdf" /></p>'
		);
		$file = $this->write($mpdf);

		$this->assertSame(1, substr_count(file_get_contents($file), '/Type /EmbeddedFile'));
		$this->assertConforms($file, $this->flavour($version));
	}

	/**
	 * The PDF/A versions that allow transparency, each with the default sRGB output intent and with a CMYK one
	 *
	 * @return mixed[][]
	 */
	public function documents()
	{
		$cmyk = ['restrictColorSpace' => 3, 'ICCProfile' => __DIR__ . '/../../data/iccprofiles/SWOP2006_Coated3v2.icc'];

		$documents = [];
		foreach (['2-B', '2-U', '3-B', '3-U'] as $version) {
			$documents[$version . ' RGB'] = [$version, []];
			$documents[$version . ' CMYK'] = [$version, $cmyk];
		}

		return $documents;
	}

	/**
	 * The PDF/A-2 versions, whose attachments must themselves be PDF/A
	 *
	 * @return string[][]
	 */
	public function pdfa2Versions()
	{
		return [['2-B'], ['2-U']];
	}

	/**
	 * Writes a document to a temporary file, removed after the test
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return string the file
	 */
	private function write(Mpdf $mpdf)
	{
		$file = sys_get_temp_dir() . '/mpdf-pdfa-' . uniqid() . '.pdf';
		$mpdf->Output($file, 'F');
		$this->files[] = $file;

		return $file;
	}

	/**
	 * The veraPDF flavour of a PDFAversion: '2-B' is '2b'
	 *
	 * @param string $version
	 *
	 * @return string
	 */
	private function flavour($version)
	{
		return strtolower(str_replace('-', '', $version));
	}

}
