<?php

namespace Mpdf;

/**
 * A blurred text-shadow is drawn as translucent copies of the text stroked at growing widths, in the standard CSS
 * mode and where the document allows transparency
 */
class TextShadowBlurTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const WARNING = 'A text-shadow cannot be blurred without transparency (Shadow drawn without blur)';

	/**
	 * The layers run from widest to narrowest, the widest twice the blur radius across, one per quarter millimetre of
	 * blur but never fewer than two or more than eight
	 *
	 * @dataProvider layerCounts
	 */
	public function testLayerWidths($blur, $count)
	{
		$layers = TextShadowBlur::layers($blur, 1);

		$this->assertCount($count, $layers);
		$this->assertEqualsWithDelta(2 * $blur, $layers[0]['width'], 1e-9);
		$this->assertEqualsWithDelta(2 * $blur / $count, $layers[$count - 1]['width'], 1e-9);
		for ($i = 1; $i < $count; $i++) {
			$this->assertLessThan($layers[$i - 1]['width'], $layers[$i]['width']);
		}
	}

	/**
	 * Blur radii, in mm, and the number of layers each is drawn with
	 *
	 * @return array[]
	 */
	public function layerCounts()
	{
		return [
			'a hairline blur' => [0.05, 2],
			'half a millimetre' => [0.5, 2],
			'one millimetre' => [1, 4],
			'one and a bit' => [1.1, 5],
			'two millimetres' => [2, 8],
			'ten millimetres' => [10, 8],
		];
	}

	/**
	 * Where the layers overlap they compound to a Gaussian edge's fall-off: half the shadow colour's alpha at the
	 * glyph's edge, and fainter in each band further out
	 *
	 * @dataProvider alphas
	 */
	public function testLayersCompoundToAGaussianEdge($alpha)
	{
		$layers = TextShadowBlur::layers(2, $alpha);

		$bands = [];
		$clear = 1;
		foreach ($layers as $layer) {
			$clear *= 1 - $layer['alpha'];
			$bands[] = 1 - $clear;
		}

		// The last band reached is the glyph itself and the band beside it, which every layer covers
		$this->assertEqualsWithDelta($alpha / 2, end($bands), 1e-6);
		// The band one standard deviation (half the blur radius) out starts at 0.5 * erfc(1 / sqrt(2))
		$this->assertEqualsWithDelta($alpha * 0.158655, $bands[3], 1e-6);
		for ($i = 1; $i < count($bands); $i++) {
			$this->assertGreaterThan($bands[$i - 1], $bands[$i]);
		}
	}

	/**
	 * Shadow colour alphas
	 *
	 * @return array[]
	 */
	public function alphas()
	{
		return [
			'opaque' => [1],
			'half' => [0.5],
		];
	}

	/**
	 * A blurred shadow repeats the text once per layer, filled and stroked with round joins at the layer's width, in
	 * the shadow colour on both the fill and the stroke, offset by the shadow's offset
	 */
	public function testBlurredShadowIsDrawnInLayers()
	{
		$page = $this->page('<p style="text-shadow: 1mm 2mm 1mm #ff0000">Soft</p>');

		$this->assertTextCount(5, 'Soft', $page);
		$this->assertStringContainsString('1.000 0.000 0.000 RG 2 Tr 1 j 1 J', $page);
		$this->assertStringContainsString(sprintf('1 0 0 1 %.4F %.4F cm', Mpdf::SCALE, -2 * Mpdf::SCALE), $page);
		foreach (TextShadowBlur::layers(1, 1) as $layer) {
			$this->assertStringContainsString(sprintf(' %.3F w BT', $layer['width'] * Mpdf::SCALE), $page);
		}
	}

	/**
	 * The blurred copies take the shadow colour's own alpha into the layers' alphas rather than over them
	 */
	public function testTranslucentShadowColourScalesTheLayers()
	{
		$pdf = $this->render('<p style="text-shadow: 1mm 1mm 1mm rgba(255, 0, 0, 0.5)">Soft</p>');

		preg_match_all('/\/ca ([\d.]+)/', $pdf, $alphas);
		$expected = [1];
		foreach (TextShadowBlur::layers(1, 0.5) as $layer) {
			$expected[] = $layer['alpha'];
		}

		$this->assertEqualsWithDelta($expected, array_map('floatval', $alphas[1]), 1e-9);
	}

	/**
	 * A shadow with no blur, and a blurred shadow in the legacy CSS mode, are drawn as one sharp copy
	 *
	 * @dataProvider sharpShadows
	 */
	public function testSharpShadow($shadow, $config)
	{
		$page = $this->page('<p style="text-shadow: ' . $shadow . '">Soft</p>', $config);

		$this->assertTextCount(2, 'Soft', $page);
		$this->assertStringNotContainsString('2 Tr 1 j 1 J', $page);
	}

	/**
	 * Shadows drawn as one sharp copy, and the document they are drawn in
	 *
	 * @return array[]
	 */
	public function sharpShadows()
	{
		return [
			'no blur' => ['1mm 1mm #ff0000', []],
			'a zero blur' => ['1mm 1mm 0 #ff0000', []],
			'legacy mode' => ['1mm 1mm 1mm #ff0000', ['cssMode' => CssMode::LEGACY]],
		];
	}

	/**
	 * Where transparency is not allowed the shadow is drawn sharp; PDF/A-2 allows it, so the blur is kept
	 *
	 * @dataProvider documents
	 */
	public function testBlurNeedsTransparency($config, $blurred)
	{
		$page = $this->page('<p style="text-shadow: 1mm 1mm 1mm #ff0000">Soft</p>', $config + ['mode' => '']);

		$this->assertSame($blurred, strpos($page, '2 Tr 1 j 1 J') !== false);
	}

	/**
	 * Documents with and without transparency, and whether a shadow is blurred in each
	 *
	 * @return array[]
	 */
	public function documents()
	{
		return [
			'PDF/A-1b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B'], false],
			'PDF/X-1a' => [['PDFX' => true, 'PDFXauto' => true, 'PDFXversion' => '1a'], false],
			'PDF/X-4' => [['PDFX' => true, 'PDFXauto' => true, 'PDFXversion' => '4'], true],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B'], true],
		];
	}

	/**
	 * A PDF/A-1 document that is not to fix itself reports the blur it left out once, however many runs of text the
	 * shadow is on
	 */
	public function testDroppedBlurIsReportedOnce()
	{
		$mpdf = $this->mpdf(['mode' => '', 'PDFA' => true, 'PDFAauto' => false, 'PDFAversion' => '1-B']);
		$mpdf->WriteHTML(str_repeat('<p style="text-shadow: 1mm 1mm 1mm #ff0000">Soft <b>and</b> <i>shadowed</i></p>', 3));

		$this->assertSame(1, count(array_keys($mpdf->PDFAXwarnings, self::WARNING, true)));
	}

	/**
	 * The first page's content stream of a document holding the given HTML
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string
	 */
	private function page($html, $config = [])
	{
		$pages = $this->pages($this->render($html, $config));

		return $pages[0];
	}

}
