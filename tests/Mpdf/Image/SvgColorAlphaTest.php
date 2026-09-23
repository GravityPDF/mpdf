<?php

namespace Mpdf\Image;

use Mpdf\Mpdf;
use Mpdf\PageStreams;

/**
 * The alpha of an rgba() or cmyka() fill, stroke or stop-color in an SVG (#395)
 */
class SvgColorAlphaTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A fill and a stroke take the alpha of their colour, multiplied by any fill-opacity or stroke-opacity
	 *
	 * @dataProvider fillAndStrokeProvider
	 *
	 * @param string $attributes
	 * @param float $fill
	 * @param float $stroke
	 * @param array $config
	 */
	public function testFillAndStrokeTakeTheColourAlpha($attributes, $fill, $stroke, $config = [])
	{
		$states = $this->states($this->draw('<rect width="80" height="40" ' . $attributes . ' />', $config));

		$this->assertContains(['ca' => $fill, 'BM' => '/Normal'], $states);
		$this->assertContains(['CA' => $stroke, 'BM' => '/Normal'], $states);
	}

	/**
	 * Rect attributes, the fill and stroke alphas they draw with, and the document config; opacities multiply with
	 * the colour's alpha, as browsers do
	 *
	 * @return array
	 */
	public function fillAndStrokeProvider()
	{
		return [
			'rgba fill, cmyka stroke' => ['fill="rgba(255,0,0,0.3)" stroke="cmyka(0,100,0,0,0.6)"', 0.3, 0.6],
			'cmyka fill, rgba stroke' => ['fill="cmyka(100,0,0,0,0.45)" stroke="rgba(0,0,255,0.7)"', 0.45, 0.7],
			'currentColor' => ['color="rgba(0,128,0,0.4)" fill="currentColor" stroke="currentColor"', 0.4, 0.4],
			'with opacity' => ['fill="rgba(255,0,0,0.5)" fill-opacity="0.5" stroke="cmyka(0,0,100,0,0.8)" stroke-opacity="0.5"', 0.25, 0.4],
			'restricted to grayscale' => ['fill="rgba(255,0,0,0.3)" stroke="cmyka(0,100,0,0,0.6)"', 0.3, 0.6, ['restrictColorSpace' => 1]],
		];
	}

	/**
	 * A gradient stop takes the alpha of its rgba() stop-color, multiplied by any stop-opacity
	 */
	public function testGradientStopTakesTheColourAlpha()
	{
		$mpdf = $this->draw(
			'<linearGradient id="g">'
			. '<stop offset="0" stop-color="rgba(255,0,0,0.4)" />'
			. '<stop offset="0.5" stop-color="cmyka(0,100,0,0,0.6)" stop-opacity="0.5" />'
			. '<stop offset="1" stop-color="blue" />'
			. '</linearGradient>'
			. '<rect width="80" height="40" fill="url(#g)" />'
		);

		$this->assertSame([0.4, 0.3, 1.0], $this->stopOpacities($mpdf));
	}

	/**
	 * Reading an alpha whose byte is a digit raised an ord() deprecation on PHP 8.5
	 */
	public function testAlphaIsReadWithoutRaisingAnything()
	{
		$this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML($this->svg($this->translucentShapes()));
		});
	}

	/**
	 * PDF/A-1b and PDF/X-1a forbid transparency, so the colour's alpha is dropped
	 *
	 * @dataProvider noTransparencyProvider
	 *
	 * @param array $config
	 */
	public function testNoTransparencyWhereTheDocumentForbidsIt($config)
	{
		$mpdf = $this->draw($this->translucentShapes(), $config);
		$states = $this->states($mpdf);

		$this->assertEquals([1], array_unique(array_merge(array_column($states, 'ca'), array_column($states, 'CA'))));
		$this->assertSame([1.0, 1.0], $this->stopOpacities($mpdf));
		$this->assertStringNotContainsString('/SMask', $this->output($mpdf));
	}

	/**
	 * PDF/A-1b and PDF/X-1a, converting colours without warnings
	 *
	 * @return array
	 */
	public function noTransparencyProvider()
	{
		return [
			'PDF/A-1b' => [['mode' => 'utf-8', 'PDFA' => true, 'PDFAauto' => true]],
			'PDF/X-1a' => [['mode' => 'utf-8', 'PDFX' => true, 'PDFXauto' => true]],
		];
	}

	/**
	 * A fill, a stroke and a gradient stop at half opacity. An alpha of 0.5 is stored as the byte "2", a digit, which
	 * the old reading divided and passed to ord() as a longer string
	 *
	 * @return string
	 */
	private function translucentShapes()
	{
		return '<rect width="80" height="40" fill="rgba(255,0,0,0.5)" stroke="cmyka(0,100,0,0,0.5)" />'
			. '<linearGradient id="g"><stop offset="0" stop-color="rgba(255,0,0,0.5)" /><stop offset="1" stop-color="blue" /></linearGradient>'
			. '<rect width="80" height="40" fill="url(#g)" />';
	}

	/**
	 * Write the SVG elements into a new document
	 *
	 * @param string $elements
	 * @param array $config
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function draw($elements, $config = [])
	{
		$mpdf = $this->mpdf($config);
		$mpdf->WriteHTML($this->svg($elements));

		return $mpdf;
	}

	/**
	 * The elements wrapped in an svg element
	 *
	 * @param string $elements
	 *
	 * @return string
	 */
	private function svg($elements)
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50">' . $elements . '</svg>';
	}

	/**
	 * The parameters of each graphics state the document registered
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return array[]
	 */
	private function states(Mpdf $mpdf)
	{
		return array_values(array_column($mpdf->extgstates, 'parms'));
	}

	/**
	 * The opacity of each stop of the document's first gradient
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return float[]
	 */
	private function stopOpacities(Mpdf $mpdf)
	{
		$gradient = reset($mpdf->gradients);

		return array_map('floatval', array_column($gradient['stops'], 'opacity'));
	}
}
