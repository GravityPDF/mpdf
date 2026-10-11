<?php

namespace Mpdf;

/**
 * A background with several comma-separated layers draws every layer, the last one at the bottom, over the colour
 * the last layer names. Each layer takes its own value from each longhand list, which repeats when it is shorter
 * than the list of images.
 */
class MultipleBackgroundsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The background image's size in points, 100 by 116 pixels at 96 dpi
	 */
	const IMAGE_SIZE = '75.000 87.000';

	/**
	 * @dataProvider providerLayerOrder
	 *
	 * @param string $css Declarations for a 60mm by 30mm block, with IMG for the background image
	 * @param string[] $painted What is painted, from the bottom up
	 */
	public function testPaintsEveryLayerFromTheLastToTheFirst($css, array $painted)
	{
		$layers = $this->paintedLayers('<div style="width: 60mm; height: 30mm; ' . $this->withImage($css) . '">Text</div>');

		$this->assertSame($painted, array_column($layers, 'kind'));
	}

	/**
	 * Backgrounds and what they paint, from the bottom up
	 *
	 * @return array[]
	 */
	public function providerLayerOrder()
	{
		return [
			'an image over a gradient' => ['background: url(IMG) no-repeat, linear-gradient(red, blue)', ['shading', 'pattern']],
			'a gradient over an image' => ['background: linear-gradient(red, blue), url(IMG)', ['pattern', 'shading']],
			'two images' => ['background-image: url(IMG), url(IMG); background-repeat: no-repeat', ['pattern', 'pattern']],
			'a gradient over a gradient' => ['background-image: linear-gradient(red, blue), radial-gradient(green, yellow)', ['shading', 'shading']],
			'a layer of none' => ['background-image: url(IMG), none, linear-gradient(red, blue)', ['shading', 'pattern']],
		];
	}

	/**
	 * The bottom layer of a gradient over a gradient is the one listed last
	 */
	public function testPaintsTheLastGradientAtTheBottom()
	{
		$layers = $this->paintedLayers('<div style="width: 60mm; height: 30mm; background-image: linear-gradient(red, blue), radial-gradient(green, yellow)">Text</div>');

		// ShadingType 2 is axial, 3 radial
		$this->assertSame(['3', '2'], array_column($layers, 'type'));
	}

	/**
	 * The colour the last layer names is painted under the images
	 */
	public function testPaintsTheColourOfTheLastLayerUnderTheImage()
	{
		$pdf = $this->render('<div style="width: 60mm; height: 30mm; ' . $this->withImage('background: url(IMG) no-repeat, #0f0') . '">Text</div>');

		$stream = $this->pageContents($pdf)[0];
		$this->assertStringContainsString('0.000 1.000 0.000 rg', $stream);
		$this->assertLessThan(strpos($stream, '/Pattern cs'), strpos($stream, '0.000 1.000 0.000 rg'));
	}

	/**
	 * A layer takes its own position and size from the lists, and a repeat list shorter than the list of images
	 * repeats: the third of three layers takes the first repeat
	 */
	public function testEachLayerTakesItsOwnValues()
	{
		$layers = $this->paintedLayers('<div style="width: 60mm; height: 30mm; ' . $this->withImage(
			'background-image: url(IMG), url(IMG), url(IMG);'
			. ' background-repeat: no-repeat, repeat-x;'
			. ' background-position: left top, right bottom, left top;'
			. ' background-size: auto, auto, 20mm auto'
		) . '">Text</div>');

		$this->assertSame([
			// The bottom layer, 20mm (56.693pt) wide and in proportion, at the top left
			['kind' => 'pattern', 'step' => '99999 99999', 'box' => '56.693 65.764', 'at' => '42.520 730.772'],
			// Repeated across, at the bottom right of the 170.079pt by 85.039pt box
			['kind' => 'pattern', 'step' => '75.000 99999', 'box' => self::IMAGE_SIZE, 'at' => '137.598 711.496'],
			// The top layer, once at the top left
			['kind' => 'pattern', 'step' => '99999 99999', 'box' => self::IMAGE_SIZE, 'at' => '42.520 709.536'],
		], $layers);
	}

	/**
	 * A longhand list longer than the list of images is cut short
	 */
	public function testCutsAListLongerThanTheImages()
	{
		$layers = $this->paintedLayers('<div style="width: 60mm; height: 30mm; ' . $this->withImage('background: url(IMG) no-repeat; background-position: right bottom, left top') . '">Text</div>');

		$this->assertSame([['kind' => 'pattern', 'step' => '99999 99999', 'box' => self::IMAGE_SIZE, 'at' => '137.598 711.496']], $layers);
	}

	/**
	 * A table cell draws every layer, the last one at the bottom
	 */
	public function testPaintsEveryLayerOfATableCell()
	{
		$layers = $this->paintedLayers('<table><tr><td style="width: 60mm; height: 30mm; ' . $this->withImage('background: url(IMG) no-repeat, linear-gradient(red, blue)') . '">Text</td></tr></table>');

		$this->assertSame(['shading', 'pattern'], array_column($layers, 'kind'));
	}

	/**
	 * A table draws every layer, the last one at the bottom
	 */
	public function testPaintsEveryLayerOfATable()
	{
		$layers = $this->paintedLayers('<table style="' . $this->withImage('background: linear-gradient(red, blue), url(IMG) no-repeat') . '"><tr><td style="width: 60mm; height: 30mm">Text</td></tr></table>');

		$this->assertSame(['pattern', 'shading'], array_column($layers, 'kind'));
	}

	/**
	 * A table row draws every layer, the last one at the bottom
	 */
	public function testPaintsEveryLayerOfATableRow()
	{
		$layers = $this->paintedLayers('<table><tr style="' . $this->withImage('background: linear-gradient(red, blue), url(IMG) no-repeat') . '"><td style="width: 60mm; height: 30mm">Text</td></tr></table>');

		$this->assertSame(['pattern', 'shading'], array_column($layers, 'kind'));
	}

	/**
	 * The page box of an @page rule draws every layer, the last one at the bottom
	 */
	public function testPaintsEveryLayerOfThePage()
	{
		$layers = $this->paintedLayers('<style>@page { ' . $this->withImage('background: url(IMG) no-repeat, linear-gradient(red, blue)') . ' }</style>Text');

		$this->assertSame(['shading', 'pattern'], array_column($layers, 'kind'));
	}

	/**
	 * The colour, and the layers with it, are clipped as the bottom layer's background-clip has it
	 */
	public function testClipsToTheBoxOfTheBottomLayer()
	{
		$pdf = $this->render('<div style="width: 60mm; height: 30mm; padding: 5mm; ' . $this->withImage('background: url(IMG) no-repeat border-box, #0f0 content-box') . '">Text</div>');

		// The content box starts 5mm (14.173pt) in from the block's left edge at 42.520pt
		$this->assertStringContainsString(' q 0 w 56.693 ', $this->pageContents($pdf)[0]);
	}

	/**
	 * The legacy CSS mode draws only the top layer, as mPDF v7 did
	 */
	public function testTheLegacyModeDrawsOnlyTheTopLayer()
	{
		$layers = $this->paintedLayers('<div style="width: 60mm; height: 30mm; ' . $this->withImage('background: url(IMG) no-repeat, linear-gradient(red, blue)') . '">Text</div>', ['cssMode' => CssMode::LEGACY]);

		$this->assertSame(['pattern'], array_column($layers, 'kind'));
	}

	/**
	 * Puts the test image's path in place of IMG
	 *
	 * @param string $css
	 * @return string
	 */
	private function withImage($css)
	{
		return str_replace('IMG', $this->backgroundImage(), $css);
	}

	/**
	 * The gradients and images the first page paints, from the bottom up. A gradient gives its shading type; an
	 * image pattern its steps, its box and where it is placed, in points
	 *
	 * @param string $html
	 * @param array $config
	 * @return array[]
	 */
	private function paintedLayers($html, $config = [])
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, $config);

		preg_match_all('#/(Sh\d+) sh|/Pattern cs /(P\d+) scn#', $this->pageContents($pdf)[0], $fills, PREG_SET_ORDER);

		$layers = [];
		foreach ($fills as $fill) {
			$name = isset($fill[2]) ? $fill[2] : $fill[1];
			preg_match('#/' . $name . ' (\d+) 0 R#', $pdf, $ref);
			$object = $this->object($pdf, $ref[1]);

			if (isset($fill[2])) {
				preg_match('#/BBox \[0 0 ([\d.]+ [\d.]+)\]\n/XStep ([\d.]+)\n/YStep ([\d.]+)\n/Matrix \[1 0 0 1 ([\d.]+ [\d.]+)\]#', $object, $pattern);
				$layers[] = ['kind' => 'pattern', 'step' => $pattern[2] . ' ' . $pattern[3], 'box' => $pattern[1], 'at' => $pattern[4]];
			} else {
				preg_match('#/ShadingType (\d)#', $object, $shading);
				$layers[] = ['kind' => 'shading', 'type' => $shading[1]];
			}
		}

		return $layers;
	}

}
