<?php

namespace Mpdf;

/**
 * Documents mPDF writes as PDF/A-2 and PDF/A-3 pass veraPDF, the reference validator
 *
 * Needs `verapdf` on the PATH, and is skipped where it is not.
 *
 * @group pdfa
 */
class PdfaConformanceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $file;

	/**
	 * Skip unless veraPDF can be run
	 */
	protected function set_up()
	{
		exec('verapdf --version 2>&1', $output, $status);
		if ($status !== 0) {
			$this->markTestSkipped('veraPDF is not on the PATH');
		}

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

		exec('verapdf --flavour ' . strtolower(str_replace('-', '', $version)) . ' --format xml ' . escapeshellarg($this->file) . ' 2>&1', $report);
		$report = implode("\n", $report);

		preg_match_all('/<rule [^>]*clause="([^"]+)" testNumber="(\d+)" status="failed"/', $report, $failed, PREG_SET_ORDER);
		$this->assertSame([], array_map(function ($rule) {
			return $rule[1] . '-' . $rule[2];
		}, $failed), 'veraPDF found the document does not conform');
		$this->assertStringContainsString('isCompliant="true"', $report);
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
