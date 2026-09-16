<?php

namespace Mpdf;

/**
 * AddFont() gives every font it registers a haskernGPOS, and it is the only producer of a font entry
 * that does. SetFont() builds the entry for the core fourteen out of CoreFonts and AddCIDFont() builds
 * the Adobe CJK ones, and neither states anything about a kern feature the font has not got. Seven
 * reads asked for the key anyway, each of them behind useKerning. GravityPDF/mpdf#141.
 */
class CoreFontKerningTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const TEXT = 'Kerning AV Wa To';

	/**
	 * What the guard is for: the core entry has the key neither set nor defaulted.
	 */
	public function testACoreFontIsRegisteredWithoutTheKey()
	{
		$mpdf = $this->mpdf();
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
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->WriteHTML('<div style="font-family:helvetica;">' . self::TEXT . '</div>');
		});
	}

	/**
	 * The same read, in a document that never asked for a core font by name: useKerning is on and
	 * the default font is one. This is the shape a host hits, rather than mode 'c'.
	 */
	public function testHtmlFallingBackToACoreFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->WriteHTML('<div>' . self::TEXT . '</div>');
		}, ['mode' => 'utf-8', 'default_font' => 'chelvetica']);
	}

	/**
	 * An Adobe CJK font is registered by AddCIDFont(), which states no more about a kern feature
	 * than the core branch does.
	 */
	public function testAnAdobeCjkFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->WriteHTML('<div style="font-family:big5;">' . self::TEXT . '</div>');
		}, ['mode' => 'utf-8']);
	}

	public function testWriteTextInACoreFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->SetFont('helvetica', '', 12);
			$mpdf->WriteText(20, 40, self::TEXT);
		});
	}

	public function testWriteCellInACoreFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->SetFont('helvetica', '', 12);
			$mpdf->WriteCell(0, 5, self::TEXT);
		});
	}

	public function testAutosizeTextInACoreFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->AutosizeText(self::TEXT, 100, 'helvetica', '', 20);
		});
	}

	/**
	 * Mpdf::watermark(), which the text and the flag between them turn on
	 */
	public function testWatermarkInACoreFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->SetWatermarkText(self::TEXT);
			$mpdf->showWatermarkText = true;
			$mpdf->WriteHTML('Body');
		});
	}

	/**
	 * DirectWrite::Shaded_box(), which keeps its own copy of the decision
	 */
	public function testShadedBoxInACoreFontRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->Shaded_box(self::TEXT, 'helvetica', '', 12);
		});
	}

	/**
	 * Image\Svg, which keeps the third copy
	 */
	public function testSvgTextInACoreFontRaisesNoDiagnostic()
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50">'
			. '<text x="5" y="30" font-family="helvetica" font-size="16">' . self::TEXT . '</text></svg>';

		$this->assertDrawsSilently(function ($mpdf) use ($svg) {
			$mpdf->WriteHTML('<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="200" />');
		});
	}

	/**
	 * The other side of the guard. A font that states a kern feature still takes it: the run is
	 * drawn as a TJ array carrying the pair adjustments, where with kerning off it is one Tj string.
	 */
	public function testAFontThatStatesAKernFeatureStillKerns()
	{
		$kerned = '/\[\(.*?\)\d+\(.*?\)\d+.*?\] TJ/';

		$this->assertMatchesRegularExpression($kerned, $this->trueType(true));
		$this->assertDoesNotMatchRegularExpression($kerned, $this->trueType(false));
	}

	/**
	 * Noto Sans states a kern feature in GPOS, so haskernGPOS is true and the run goes to the shaper
	 *
	 * @param bool $useKerning
	 *
	 * @return string the page the text is drawn on
	 */
	private function trueType($useKerning)
	{
		$mpdf = $this->mpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['kerning' => ['R' => 'NotoSans-Regular.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'kerning',
			'useKerning' => $useKerning,
		]);
		$mpdf->WriteHTML('<p>AV Wa To LT VA Ty</p>');
		$this->assertTrue($mpdf->fonts['kerning']['haskernGPOS']);

		$pages = $this->pages($this->output($mpdf));

		return $pages[0];
	}

}
