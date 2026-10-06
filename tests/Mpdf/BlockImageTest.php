<?php

namespace Mpdf;

/**
 * An image with display: block has a line of its own and is placed by its margins: auto margins centre it or push it
 * to one side, and text-align does not move it. The page area is 180mm wide, from x = 15mm.
 */
class BlockImageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A 50mm-wide image, styled as given
	 *
	 * @param string $style
	 *
	 * @return string
	 */
	private function image($style)
	{
		return '<img src="' . $this->pngImage() . '" style="width: 50mm; ' . $style . '">';
	}

	/**
	 * The x of the only image on the first page, in millimetres
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return float
	 */
	private function imageX($html, $config = [])
	{
		$placements = $this->imagePlacements($html, 0, $config);
		$this->assertCount(1, $placements);

		return $placements[0]['x'];
	}

	/**
	 * The baseline of each piece of text on the first page, in points from the page's bottom, keyed by the text, and
	 * the bottom edge of each image under 'image'
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return float[]
	 */
	private function baselines($html, $config = [])
	{
		$page = $this->pages($this->render($html, $config))[0];
		preg_match_all('/BT [-\d.]+ ([-\d.]+) Td\s+\((.*?)\) Tj|[-\d.]+ 0 0 [-\d.]+ [-\d.]+ ([-\d.]+) cm \/I\d+ Do/', $page, $drawn, PREG_SET_ORDER);

		$baselines = [];
		foreach ($drawn as $operator) {
			if (isset($operator[3])) {
				$baselines['image'] = (float) $operator[3];
			} else {
				$baselines[$operator[2]] = (float) $operator[1];
			}
		}

		return $baselines;
	}

	/**
	 * @dataProvider marginProvider
	 */
	public function testAutoMarginsPlaceABlockImage($style, $x)
	{
		$this->assertEqualsWithDelta($x, $this->imageX($this->image($style)), 0.01);
	}

	/**
	 * Each margin combination of the issue's table, with where a browser puts the 50mm image
	 *
	 * @return array[]
	 */
	public function marginProvider()
	{
		return [
			'both auto centres' => ['display: block; margin: 0 auto', 80],
			'left auto pushes right' => ['display: block; margin-left: auto', 145],
			'right auto keeps left' => ['display: block; margin-right: auto', 15],
			'no auto margin keeps left' => ['display: block', 15],
			'a length beside an auto margin' => ['display: block; margin-left: auto; margin-right: 10mm', 135],
			'a length on each side is kept' => ['display: block; margin-left: 20mm; margin-right: 200mm', 35],
		];
	}

	public function testABlockImageBreaksTheLineBeforeAndAfterIt()
	{
		$baselines = $this->baselines('<p>before ' . $this->image('display: block; margin: 0 auto') . ' after</p>');

		$this->assertSame(['before', 'image', 'after'], array_keys($baselines));
		$this->assertGreaterThan($baselines['image'], $baselines['before'], 'before is on a line above the image');
		$this->assertGreaterThan($baselines['after'], $baselines['image'], 'after is on a line below the image');
	}

	public function testAnInlineImageStaysOnTheLineWithItsText()
	{
		$baselines = $this->baselines('<p>before ' . $this->image('margin: 0 auto') . ' after</p>');

		$this->assertEqualsWithDelta($baselines['before '], $baselines[' after'], 0.01);
		$this->assertEqualsWithDelta($baselines['before '], $baselines['image'], 0.01);
	}

	public function testTextAlignCentresAnInlineImage()
	{
		$this->assertEqualsWithDelta(80, $this->imageX('<div style="text-align: center">' . $this->image('') . '</div>'), 0.01);
	}

	public function testTextAlignDoesNotMoveABlockImage()
	{
		$this->assertEqualsWithDelta(15, $this->imageX('<div style="text-align: right">' . $this->image('display: block') . '</div>'), 0.01);
		$this->assertEqualsWithDelta(15, $this->imageX('<div style="text-align: center">' . $this->image('display: block') . '</div>'), 0.01);
	}

	/**
	 * The border box is 64mm wide, so each auto margin is 58mm and the picture inside the 2mm border and 5mm padding
	 * is still centred
	 */
	public function testThePaddingAndBorderAreInsideTheMargins()
	{
		$html = $this->image('display: block; margin: 0 auto; padding: 5mm; border: 2mm solid red');

		$this->assertEqualsWithDelta(80, $this->imageX($html), 0.01);
		$this->assertEqualsWithDelta(50, $this->drawnWidth($html), 0.01);
	}

	/**
	 * @dataProvider rtlProvider
	 */
	public function testARightToLeftBlockPlacesTheImageFromItsRightEdge($style, $x)
	{
		$this->assertEqualsWithDelta($x, $this->imageX('<div dir="rtl">' . $this->image($style) . '</div>'), 0.01);
	}

	/**
	 * In a right-to-left block the left margin is the one that gives way
	 *
	 * @return array[]
	 */
	public function rtlProvider()
	{
		return [
			'no auto margin sits at the right' => ['display: block', 145],
			'both auto centres' => ['display: block; margin: 0 auto', 80],
			'right auto pushes left' => ['display: block; margin-right: auto', 15],
			'left auto keeps right' => ['display: block; margin-left: auto', 145],
		];
	}

	public function testAnImageInATableCellKeepsItsPlace()
	{
		$html = '<table><tr><td>' . $this->image('display: block; margin: 0 auto') . '</td></tr></table>';

		$this->assertSame($this->imagePlacements($html, 0, ['cssMode' => CssMode::LEGACY]), $this->imagePlacements($html));
	}

	public function testABlockImageIsCentredInTheRoomBesideAFloat()
	{
		$placements = $this->imagePlacements('<div><img src="' . $this->pngImage() . '" style="float: left; width: 40mm">' . $this->image('display: block; margin: 0 auto') . '</div>');

		$this->assertCount(2, $placements);
		$this->assertEqualsWithDelta(100, $placements[0]['x'], 0.01, '(180 - 40 - 50) / 2 = 45mm in from the float');
	}

	public function testTwoBlockImagesTakeALineEach()
	{
		$placements = $this->imagePlacements('<p>' . $this->image('display: block; margin: 0 auto') . $this->image('display: block; margin-left: auto') . '</p>');

		$this->assertCount(2, $placements);
		$this->assertEqualsWithDelta(80, $placements[0]['x'], 0.01);
		$this->assertEqualsWithDelta(145, $placements[1]['x'], 0.01);
	}

	public function testLegacyModeKeepsTheImageInlineWithZeroMargins()
	{
		$legacy = ['cssMode' => CssMode::LEGACY];

		$this->assertEqualsWithDelta(15, $this->imageX($this->image('display: block; margin: 0 auto'), $legacy), 0.01);

		$baselines = $this->baselines('<p>before ' . $this->image('display: block; margin: 0 auto') . ' after</p>', $legacy);
		$this->assertEqualsWithDelta($baselines['before '], $baselines[' after'], 0.01);
		$this->assertEqualsWithDelta($baselines['before '], $baselines['image'], 0.01);
	}
}
