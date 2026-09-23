<?php

namespace Mpdf;

/**
 * Documents mPDF writes as PDF/A-1b, PDF/A-2 and PDF/A-3 pass veraPDF
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
	 * Skip before building a document veraPDF is not there to check
	 */
	protected function set_up()
	{
		$this->skipWithoutVeraPdf();
	}

	/**
	 * Remove the documents written
	 */
	protected function tear_down()
	{
		array_map('unlink', $this->files);
	}

	/**
	 * A document with transparency, text outside Latin-1 and annotations conforms, with an RGB or a CMYK output
	 * intent. PDF/A-1 paints what is transparent opaque, and refuses a watermark outright.
	 *
	 * @dataProvider documents
	 */
	public function testDocumentConforms($version, $config)
	{
		$img = __DIR__ . '/../data/img/';

		$mpdf = $this->pdfa($version, $config);
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->showWatermarkText = $mpdf->transparencyAllowed();
		$mpdf->WriteHTML(
			'<bookmark content="Start" /><h1>PDF/A</h1>'
			. '<p style="font-family: dejavusans">Ελληνικά “quoted” <a href="https://example.com">link</a></p>'
			. '<p style="font-family: freeserif">नमस्ते</p>'
			. '<p style="color: cmyka(0, 100, 100, 0, 0.5)">Translucent CMYK</p>'
			. '<div style="background: linear-gradient(rgba(255, 0, 0, 1), rgba(0, 0, 255, 0.2)); height: 10mm"></div>'
			. '<div style="border: 1mm solid rgba(0, 128, 0, 0.4); box-shadow: 1mm 1mm 1mm rgba(0, 0, 0, 0.5)">Borders</div>'
			. '<p>Notes <annotation content="Note" subject="Subject" /> <annotation content="Popup" popup="true" /></p>'
			. '<img src="' . $img . 'pngpixels/rgba8-None.png" /> <img src="' . $img . 'pngpixels/la8-PNG.png" />'
			. '<img src="' . $img . 'truecolour-trns.png" /> <img style="opacity: 0.5" src="' . $img . 'tiger.jpg" width="20" />'
			. '<img src="' . $img . 'demo.svg" width="40" />'
			. '<svg width="100" height="50"><rect width="80" height="40" fill="red" fill-opacity="0.3" stroke="blue"'
			. ' stroke-opacity="0.4" opacity="0.5" /><text x="5" y="30" fill-opacity="0.5">SVG</text></svg>'
			. '<svg width="100" height="50"><linearGradient id="l"><stop offset="0" stop-color="red" stop-opacity="0.2" />'
			. '<stop offset="1" stop-color="blue" /></linearGradient><radialGradient id="r"><stop offset="0" stop-color="red"'
			. ' stop-opacity="0.2" /><stop offset="1" stop-color="blue" /></radialGradient><rect width="40" height="40"'
			. ' fill="url(#l)" /><rect x="50" width="40" height="40" fill="url(#r)" /></svg>'
		);

		$this->assertConforms($this->write($mpdf), $this->flavour($mpdf));
	}

	/**
	 * A document that attaches a PDF/A document, a plain PDF and a PHP file conforms: PDF/A-2 keeps only the PDF/A
	 * one, PDF/A-3 all three
	 *
	 * @dataProvider attachmentVersions
	 */
	public function testAttachmentsConform($version, $embedded)
	{
		$attachment = $this->write($this->pdfa('2-B'));

		$mpdf = $this->pdfa($version, ['allowAnnotationFiles' => true]);
		$mpdf->WriteHTML(
			'<p>PDF/A <annotation content="PDF/A" file="' . $attachment . '" /></p>'
			. '<p>Plain <annotation content="Plain" file="' . __DIR__ . '/../data/pdfs/2-Page-PDF_1_4.pdf" /></p>'
			. '<p>PHP <annotation content="PHP" file="' . __FILE__ . '" /></p>'
		);
		$file = $this->write($mpdf);

		$this->assertSame($embedded, substr_count(file_get_contents($file), '/Type /EmbeddedFile'));
		$this->assertConforms($file, $this->flavour($mpdf));
	}

	/**
	 * A document with layers, one of them hidden, and a hidden block conforms
	 *
	 * @dataProvider layeredVersions
	 */
	public function testLayersConform($version)
	{
		$mpdf = $this->pdfa($version);
		$mpdf->layerDetails[2] = ['state' => 'hidden', 'name' => 'Second'];
		$mpdf->WriteHTML(
			'<div style="z-index: 1">First layer</div><div style="z-index: 2">Second layer</div>'
			. '<div style="visibility: hidden">Hidden</div>'
		);

		$this->assertSame([], $mpdf->PDFAXwarnings);
		$this->assertConforms($this->write($mpdf), $this->flavour($mpdf));
	}

	/**
	 * Each PDF/A version, with the default sRGB output intent and with a CMYK one
	 *
	 * @return mixed[][]
	 */
	public function documents()
	{
		$cmyk = ['restrictColorSpace' => 3, 'ICCProfile' => __DIR__ . '/../../data/iccprofiles/SWOP2006_Coated3v2.icc'];

		$documents = [];
		foreach (['1-B', '2-B', '2-U', '3-B', '3-U'] as $version) {
			$documents[$version . ' RGB'] = [$version, []];
			$documents[$version . ' CMYK'] = [$version, $cmyk];
		}

		return $documents;
	}

	/**
	 * The PDF/A versions that embed files, with how many of the three attachments each embeds: PDF/A-2 only the
	 * PDF/A document
	 *
	 * @return mixed[][]
	 */
	public function attachmentVersions()
	{
		return [['2-B', 1], ['2-U', 1], ['3-B', 3], ['3-U', 3]];
	}

	/**
	 * A PDF/A-2 and a PDF/A-3 version, which allow optional content
	 *
	 * @return string[][]
	 */
	public function layeredVersions()
	{
		return [['2-B'], ['3-B']];
	}

	/**
	 * A PDF/A document of the given version that fixes what it can
	 *
	 * @param string $version
	 * @param mixed[] $config
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function pdfa($version, $config = [])
	{
		return new Mpdf($config + ['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version]);
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
	 * The veraPDF flavour a PDF/A document claims: PDF/A-2b is '2b'
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return string
	 */
	private function flavour(Mpdf $mpdf)
	{
		return strtolower(implode('', $mpdf->pdfaConformance()));
	}

}
