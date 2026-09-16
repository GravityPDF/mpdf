<?php

namespace Mpdf;

/**
 * AddFont() gives every font it registers a haskernGPOS, but a core font never goes through it:
 * SetFont() builds the entry for the core fourteen out of CoreFonts, and a Type1 font that carries
 * no OpenType tables states nothing about a kern feature. Seven reads asked for the key anyway,
 * each of them behind useKerning, so every core-font run raised a diagnostic. GravityPDF/mpdf#141.
 */
class CoreFontKerningTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const TEXT = 'Kerning AV Wa To';

	/**
	 * What the guard is for: the core entry has the key neither set nor defaulted.
	 */
	public function testACoreFontIsRegisteredWithoutTheKey()
	{
		$mpdf = $this->core();
		$mpdf->SetFont('helvetica', '', 12);

		$this->assertArrayNotHasKey('haskernGPOS', $mpdf->CurrentFont);
		$mpdf->cleanup();
	}

	/**
	 * Mpdf::setCSS(), which is the read an HTML document reaches - font-kerning resolves to auto,
	 * and useKerning makes auto mean normal.
	 */
	public function testHtmlInACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = $this->core();
			$mpdf->WriteHTML('<div style="font-family:helvetica;">' . self::TEXT . '</div>');
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * The same read, in a document that never asked for a core font by name: useKerning is on and
	 * the default font is one. This is the shape a host hits, rather than mode 'c'.
	 */
	public function testHtmlFallingBackToACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = new Mpdf(['useKerning' => true, 'default_font' => 'chelvetica']);
			$mpdf->WriteHTML('<div>' . self::TEXT . '</div>');
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * Mpdf::WriteText()
	 */
	public function testWriteTextInACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = $this->core();
			$mpdf->SetFont('helvetica', '', 12);
			$mpdf->WriteText(20, 40, self::TEXT);
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * Mpdf::WriteCell()
	 */
	public function testWriteCellInACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = $this->core();
			$mpdf->SetFont('helvetica', '', 12);
			$mpdf->WriteCell(0, 5, self::TEXT);
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * Mpdf::watermark()
	 */
	public function testWatermarkInACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = $this->core();
			$mpdf->SetWatermarkText(self::TEXT);
			$mpdf->showWatermarkText = true;
			$mpdf->WriteHTML('Body');
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * Mpdf::AutosizeText()
	 */
	public function testAutosizeTextInACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = $this->core();
			$mpdf->SetFont('helvetica', '', 12);
			$mpdf->AutosizeText(self::TEXT, 100, 'helvetica', '', 20);
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * DirectWrite::Shaded_box()
	 */
	public function testShadedBoxInACoreFontRaisesNoDiagnostic()
	{
		$this->assertSilent(function () {
			$mpdf = $this->core();
			$mpdf->SetFont('helvetica', '', 12);
			$mpdf->Shaded_box(self::TEXT, 'helvetica', '', 12);
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * Image\Svg, which draws the text of an SVG through the same decision
	 */
	public function testSvgTextInACoreFontRaisesNoDiagnostic()
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50">'
			. '<text x="5" y="30" font-family="helvetica" font-size="16">' . self::TEXT . '</text></svg>';

		$this->assertSilent(function () use ($svg) {
			$mpdf = $this->core();
			$mpdf->WriteHTML('<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="200" />');
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		});
	}

	/**
	 * The other side of the guard. A font that states a kern feature still takes it: the run is
	 * drawn as a TJ array carrying the pair adjustments, where with kerning off it is one Tj string.
	 */
	public function testAFontThatStatesAKernFeatureStillKerns()
	{
		$on = $this->firstPage($this->trueType(true));
		$off = $this->firstPage($this->trueType(false));

		$this->assertMatchesRegularExpression('/\[\(.*?\)\d+\(.*?\)\d+.*?\] TJ/', $on);
		$this->assertNotSame($off, $on);
	}

	/**
	 * A document of the core fourteen only, with kerning on - the combination the reads are behind
	 *
	 * @return Mpdf
	 */
	private function core()
	{
		return new Mpdf(['mode' => 'c', 'useKerning' => true]);
	}

	/**
	 * Noto Sans states a kern feature in GPOS, so haskernGPOS is true and the run goes to the shaper
	 *
	 * @param bool $useKerning
	 *
	 * @return string the document
	 */
	private function trueType($useKerning)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['kerning' => ['R' => 'NotoSans-Regular.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'kerning',
			'useKerning' => $useKerning,
		]);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>AV Wa To LT VA Ty</p>');
		$this->assertTrue($mpdf->fonts['kerning']['haskernGPOS']);
		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * @param string $pdf
	 *
	 * @return string the content stream of the first page
	 */
	private function firstPage($pdf)
	{
		preg_match('/\d+ 0 obj\s*<<\/Length \d+>>\s*stream\n(.*?)\nendstream/s', $pdf, $matches);

		return $matches[1];
	}

	/**
	 * PHPUnit turns a warning into a failure, but a handler says which read raised it and catches
	 * the notice PHP 5 raises for the same miss.
	 *
	 * @param callable $render
	 */
	private function assertSilent($render)
	{
		$raised = [];
		set_error_handler(function ($errno, $message, $file, $line) use (&$raised) {
			$raised[] = sprintf('%s in %s on line %d', $message, basename($file), $line);
			return true;
		});

		try {
			call_user_func($render);
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

}
