<?php

namespace Mpdf;

use Mpdf\Css\TextVars;

/**
 * A run drawn outside the HTML flow starts from an empty feature set, and six places appended the
 * kern feature to a 'Plus' key the clear two lines above had just removed. Unlike #141 it is the
 * supported fonts that reach it: DejaVu Sans, which ships with mPDF, states a kern feature in
 * GPOS. GravityPDF/mpdf#146.
 */
class TextRunKerningTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const TEXT = 'Kerning AV Wa To';

	/**
	 * What the guard is for: the shipped font takes the branch that sets the feature, where #141
	 * was about the branch a font with no kern feature takes.
	 */
	public function testTheShippedFontStatesAKernFeature()
	{
		$mpdf = $this->kerning();
		$mpdf->SetFont('dejavusans', '', 12);

		$this->assertTrue($mpdf->CurrentFont['haskernGPOS']);
		$mpdf->cleanup();
	}

	/**
	 * What the append sat on top of: the set is empty, so there is no 'Plus' to append to.
	 */
	public function testTheFeatureSetIsEmptyWhenTheDecisionIsMade()
	{
		$mpdf = $this->kerning();
		$mpdf->OTLtags = ['Plus' => ' liga'];
		$mpdf->SetFont('dejavusans', '', 12);

		$this->assertSame(0, $mpdf->startTextRunFeatures());
		$this->assertSame(['Plus' => ' kern'], $mpdf->OTLtags);

		$mpdf->cleanup();
	}

	/**
	 * The other branch of the same decision, which #141 was about: a font that states no kern
	 * feature in GPOS takes its own kern table through the text flags instead.
	 */
	public function testAFontWithNoKernFeatureTakesTheTextFlagInstead()
	{
		$mpdf = $this->kerning();
		$mpdf->SetFont('chelvetica', '', 12);

		$this->assertSame(TextVars::FC_KERNING, $mpdf->startTextRunFeatures());
		$this->assertSame([], $mpdf->OTLtags);

		$mpdf->cleanup();
	}

	/**
	 * And the feature reaches the shaper: the run is drawn with the pair adjustments GPOS resolved
	 * for it, where the same run with kerning off carries none.
	 */
	public function testTheRunIsKernedThroughGpos()
	{
		$this->assertNotEmpty($this->drawnGposInfo(true));
		$this->assertSame([], $this->drawnGposInfo(false));
	}

	public function testWriteTextRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->SetFont('dejavusans', '', 12);
			$mpdf->WriteText(20, 40, self::TEXT);
		}, ['mode' => 'utf-8']);
	}

	public function testWriteCellRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->SetFont('dejavusans', '', 12);
			$mpdf->WriteCell(0, 5, self::TEXT);
		}, ['mode' => 'utf-8']);
	}

	/**
	 * Mpdf::watermark(), which the text and the flag between them turn on
	 */
	public function testWatermarkRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->watermark_font = 'dejavusans';
			$mpdf->SetWatermarkText(self::TEXT);
			$mpdf->showWatermarkText = true;
			$mpdf->WriteHTML('Body');
		}, ['mode' => 'utf-8']);
	}

	public function testAutosizeTextRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->AutosizeText(self::TEXT, 100, 'dejavusans', '', 20);
		}, ['mode' => 'utf-8']);
	}

	/**
	 * DirectWrite::Shaded_box(), which reaches the decision through $this->mpdf
	 */
	public function testShadedBoxRaisesNoDiagnostic()
	{
		$this->assertDrawsSilently(function ($mpdf) {
			$mpdf->Shaded_box(self::TEXT, 'dejavusans', '', 12);
		}, ['mode' => 'utf-8']);
	}

	/**
	 * Image\Svg, which spelled the isset() out and so was already silent
	 */
	public function testSvgTextRaisesNoDiagnostic()
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50">'
			. '<text x="5" y="30" font-family="dejavusans" font-size="16">' . self::TEXT . '</text></svg>';

		$this->assertDrawsSilently(function ($mpdf) use ($svg) {
			$mpdf->WriteHTML('<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="200" />');
		}, ['mode' => 'utf-8']);
	}

	/**
	 * The positioning WriteCell() hands to the drawing code
	 *
	 * @param bool $useKerning
	 *
	 * @return array
	 */
	private function drawnGposInfo($useKerning)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8', 'useKerning' => $useKerning]);
		$mpdf->SetFont('dejavusans', '', 12);
		$mpdf->WriteCell(0, 5, self::TEXT);
		$mpdf->cleanup();

		return $mpdf->drawnOTLdata[0]['GPOSinfo'];
	}

	/**
	 * @return Mpdf
	 */
	private function kerning()
	{
		return $this->mpdf(['mode' => 'utf-8', 'useKerning' => true]);
	}

}
