<?php

namespace Mpdf;

/**
 * Layers and the visibility property draw with optional content, which PDF/A-2 and PDF/A-3 allow on conditions and
 * PDF/A-1 and PDF/X-1a forbid
 */
class PdfaOptionalContentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Optional content is allowed where transparency is: not under PDF/A-1 or PDF/X-1a
	 *
	 * @dataProvider optionalContentConfigs
	 */
	public function testOptionalContentAllowed($config, $allowed)
	{
		$mpdf = new Mpdf($config + ['PDFAauto' => true, 'PDFXauto' => true]);

		$this->assertSame($allowed, $mpdf->optionalContentAllowed());
	}

	/**
	 * Configurations and whether they allow optional content
	 *
	 * @return mixed[][]
	 */
	public function optionalContentConfigs()
	{
		return [
			'plain' => [[], true],
			'PDF/A-1b' => [['PDFA' => true, 'PDFAversion' => '1-B'], false],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAversion' => '2-B'], true],
			'PDF/A-2u' => [['PDFA' => true, 'PDFAversion' => '2-U'], true],
			'PDF/A-3b' => [['PDFA' => true, 'PDFAversion' => '3-B'], true],
			'PDF/A-3u' => [['PDFA' => true, 'PDFAversion' => '3-U'], true],
			'PDF/X-1a' => [['PDFX' => true], false],
		];
	}

	/**
	 * Under PDF/A-2 and PDF/A-3 layers are drawn, and the default configuration has a /Name, no /AS, and an /Order
	 * that lists every group
	 *
	 * @dataProvider pdfa2Versions
	 */
	public function testLayersUnderPdfa2($version)
	{
		$mpdf = $this->pdfa($version);
		$mpdf->WriteHTML($this->layeredContent());
		$pdf = $this->output($mpdf);

		$this->assertSame([], $mpdf->PDFAXwarnings);
		preg_match_all('#/OC /ZI(\d+) BDC#', implode('', $this->pages($pdf)), $layers);
		$this->assertSame(['1', '2'], array_values(array_unique($layers[1])));

		$properties = $this->optionalContentProperties($pdf);
		$this->assertStringContainsString('/D <</Name (Default) ', $properties);
		$this->assertStringNotContainsString('/AS', $properties);

		preg_match('#/OCGs \[([^\]]*)\]#', $properties, $groups);
		preg_match('#/Order \[([^\]]*)\]#', $properties, $order);
		$this->assertCount(3, $this->references($groups[1]));
		$this->assertEqualsCanonicalizing($this->references($groups[1]), $this->references($order[1]));
	}

	/**
	 * Print-only and screen-only content switch on the /AS entry PDF/A-2 forbids, so they are refused, with no /AS
	 * written: auto draws print-only content and leaves screen-only content out
	 *
	 * @dataProvider printOrScreen
	 */
	public function testPrintOrScreenOnlyRefusedUnderPdfa2($visibility)
	{
		$mpdf = $this->pdfa('2-B');
		$mpdf->WriteHTML('<div style="visibility: ' . $visibility . '">Switched</div>');
		$pdf = $this->output($mpdf);

		$this->assertContains('Cannot set visibility to ' . $visibility . ' when using PDFA or PDFX', $mpdf->PDFAXwarnings);
		$this->assertStringNotContainsString('/OC /OC', implode('', $this->pages($pdf)));
		$this->assertStringNotContainsString('/AS', $pdf);
	}

	/**
	 * The visibility values that depend on /AS
	 *
	 * @return string[][]
	 */
	public function printOrScreen()
	{
		return [['printonly'], ['screenonly']];
	}

	/**
	 * A document that is not PDF/A keeps /AS for print-only content
	 */
	public function testPrintOnlyKeepsUsageApplication()
	{
		$pdf = $this->render('<div style="visibility: printonly">Print</div>');

		$this->assertStringContainsString('/OC /OC1 BDC', implode('', $this->pages($pdf)));
		$this->assertStringContainsString('/AS [<</Event /Print', $this->optionalContentProperties($pdf));
	}

	/**
	 * PDF/A-1 refuses every layer and visibility, and writes no optional content at all
	 */
	public function testPdfa1RefusesOptionalContent()
	{
		$mpdf = $this->pdfa('1-B');
		$mpdf->WriteHTML($this->layeredContent() . '<div style="visibility: printonly">Print</div>');
		$pdf = $this->output($mpdf);

		$this->assertContains('Cannot use layers when using PDFA-1 or PDFX', $mpdf->PDFAXwarnings);
		$this->assertContains('Cannot set visibility to hidden when using PDFA or PDFX', $mpdf->PDFAXwarnings);
		$this->assertContains('Cannot set visibility to printonly when using PDFA or PDFX', $mpdf->PDFAXwarnings);
		$this->assertStringNotContainsString('BDC', implode('', $this->pages($pdf)));
		$this->assertStringNotContainsString('/OCProperties', $pdf);
		$this->assertStringNotContainsString('/Type /OCG', $pdf);
	}

	/**
	 * The catalog lists every optional content group written, and the page resources name only those groups
	 *
	 * @dataProvider visibilityDocuments
	 */
	public function testEveryGroupWrittenIsListed($config, $html)
	{
		$pdf = $this->render($html, $config);

		preg_match_all('#(\d+) 0 obj\s*<</Type /OCG #', $pdf, $written);
		$written = array_map(static function ($number) {
			return $number . ' 0 R';
		}, $written[1]);
		preg_match('#/OCGs \[([^\]]*)\]#', $this->optionalContentProperties($pdf), $listed);
		preg_match('#/Properties <<(.*?)>>#s', $pdf, $properties);

		$this->assertEqualsCanonicalizing($written, $this->references($listed[1]));
		$this->assertEqualsCanonicalizing($written, $this->references($properties[1]));
	}

	/**
	 * Documents using some of the visibility groups: a PDF/A-2 one with layers and hidden content, and plain ones
	 * with print-only content, and with print-only and hidden content
	 *
	 * @return mixed[][]
	 */
	public function visibilityDocuments()
	{
		return [
			'PDF/A-2b, layers and hidden' => [['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B'], $this->layeredContent()],
			'plain, print-only' => [[], '<div style="visibility: printonly">Print</div>'],
			'plain, print-only and hidden' => [[], '<div style="visibility: printonly">Print</div><div style="visibility: hidden">Hidden</div>'],
		];
	}

	/**
	 * The PDF/A versions that allow optional content
	 *
	 * @return string[][]
	 */
	public function pdfa2Versions()
	{
		return [['2-B'], ['2-U'], ['3-B'], ['3-U']];
	}

	/**
	 * Two layers, one of them hidden, twice entered on the same layer, and a hidden block
	 *
	 * @return string
	 */
	private function layeredContent()
	{
		return '<div style="z-index: 1">One</div><div style="z-index: 2">Two</div><div style="z-index: 2">Two again</div>'
			. '<div style="visibility: hidden">Hidden</div>';
	}

	/**
	 * A PDF/A document of the given version, uncompressed, that fixes what it can
	 *
	 * @param string $version
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function pdfa($version)
	{
		$mpdf = new Mpdf(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version]);
		$mpdf->compress = false;
		$mpdf->layerDetails[2] = ['state' => 'hidden', 'name' => 'Second'];

		return $mpdf;
	}

	/**
	 * The catalog's /OCProperties dictionary
	 *
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function optionalContentProperties($pdf)
	{
		$this->assertSame(1, preg_match('#/OCProperties <<.*?>>>>#s', $pdf, $match));

		return $match[0];
	}

	/**
	 * The object references in the body of an array
	 *
	 * @param string $array
	 *
	 * @return string[]
	 */
	private function references($array)
	{
		preg_match_all('/\d+ 0 R/', $array, $references);

		return $references[0];
	}

}
