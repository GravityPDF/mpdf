<?php

namespace Mpdf;

/**
 * object-fit, object-position and aspect-ratio on an image, in the standard CSS mode: the box keeps the size its width
 * and height give it, and the picture is scaled and placed inside it and clipped to it.
 */
class ObjectFitTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * 100 x 116 pixels: 26.46 x 30.69 mm at 96 dpi
	 */
	const PORTRAIT = '/../data/img/bg.jpg';

	/**
	 * 320 x 180 pixels: 84.67 x 47.63 mm
	 */
	const WIDE = '/../data/img/ratio-16x9.png';

	/**
	 * 180 x 320 pixels
	 */
	const TALL = '/../data/img/ratio-9x16.png';

	/**
	 * The box the picture is clipped to and the rectangle it is drawn in, each keyed x, top, w and h in millimetres
	 * from the bottom of the page, or a null box when the picture is not clipped to a rectangle
	 *
	 * @param string $style
	 * @param string $image
	 * @param array $config
	 * @param string $wrap The HTML around the image, with %s for it
	 *
	 * @return array Keyed box and image
	 */
	private function drawn($style, $image = self::PORTRAIT, $config = [], $wrap = '%s')
	{
		$html = sprintf($wrap, '<img src="' . __DIR__ . $image . '" style="' . $style . '">');
		$page = $this->pages($this->render($html, $config))[0];

		$this->assertSame(1, preg_match('/q (.*?)([-\d.]+) 0 0 ([-\d.]+) ([-\d.]+) ([-\d.]+) cm \/I\d+ Do Q/', $page, $m));

		$box = null;
		if (preg_match('/^([-\d.]+) ([-\d.]+) ([-\d.]+) ([-\d.]+) re W n $/', $m[1], $clip)) {
			$box = ['x' => $clip[1] / Mpdf::SCALE, 'top' => $clip[2] / Mpdf::SCALE, 'w' => $clip[3] / Mpdf::SCALE, 'h' => -$clip[4] / Mpdf::SCALE];
		}

		$image = [
			'x' => $m[4] / Mpdf::SCALE,
			'top' => ($m[5] + $m[3]) / Mpdf::SCALE,
			'w' => $m[2] / Mpdf::SCALE,
			'h' => $m[3] / Mpdf::SCALE,
		];

		return ['box' => $box, 'image' => $image, 'clip' => $m[1]];
	}

	/**
	 * Asserts the picture is drawn at the given size and offset from the top left of its box
	 *
	 * @param array $drawn As drawn() gives it
	 * @param float[] $size Width and height
	 * @param float[] $offset Right and down from the box's top left corner
	 */
	private function assertDrawnAt(array $drawn, array $size, array $offset)
	{
		$this->assertNotNull($drawn['box'], 'clipped to its box');
		$this->assertEqualsWithDelta($size[0], $drawn['image']['w'], 0.01, 'width');
		$this->assertEqualsWithDelta($size[1], $drawn['image']['h'], 0.01, 'height');
		$this->assertEqualsWithDelta($offset[0], $drawn['image']['x'] - $drawn['box']['x'], 0.01, 'left');
		$this->assertEqualsWithDelta($offset[1], $drawn['box']['top'] - $drawn['image']['top'], 0.01, 'top');
	}

	/**
	 * cover fills the box with the picture scaled up, and object-position: left top keeps its top left corner
	 */
	public function testCoverFillsTheBoxAndCropsTheRest()
	{
		$drawn = $this->drawn('width: 60mm; height: 20mm; object-fit: cover; object-position: left top');

		$this->assertEqualsWithDelta(60, $drawn['box']['w'], 0.01);
		$this->assertEqualsWithDelta(20, $drawn['box']['h'], 0.01);
		$this->assertDrawnAt($drawn, [60, 69.6], [0, 0]);
	}

	/**
	 * contain fits the whole picture in the box, centred by default
	 */
	public function testContainFitsThePictureInsideTheBoxCentred()
	{
		$w = 20 * 100 / 116;

		$this->assertDrawnAt($this->drawn('width: 60mm; height: 20mm; object-fit: contain'), [$w, 20], [(60 - $w) / 2, 0]);
	}

	/**
	 * none keeps the picture's natural size, centred and cropped by the box
	 */
	public function testNoneKeepsTheNaturalSize()
	{
		$w = 100 * 25.4 / 96;
		$h = 116 * 25.4 / 96;

		$this->assertDrawnAt($this->drawn('width: 20mm; height: 20mm; object-fit: none'), [$w, $h], [(20 - $w) / 2, (20 - $h) / 2]);
	}

	/**
	 * scale-down is contain in a box smaller than the picture, and none in a larger one
	 */
	public function testScaleDownIsTheSmallerOfNoneAndContain()
	{
		$w = 100 * 25.4 / 96;
		$h = 116 * 25.4 / 96;

		$this->assertDrawnAt($this->drawn('width: 60mm; height: 60mm; object-fit: scale-down'), [$w, $h], [(60 - $w) / 2, (60 - $h) / 2]);
		$this->assertDrawnAt($this->drawn('width: 60mm; height: 20mm; object-fit: scale-down'), [20 * 100 / 116, 20], [(60 - 20 * 100 / 116) / 2, 0]);
	}

	/**
	 * contain and cover on a wide and a tall picture in a square box
	 *
	 * @return array
	 */
	public function wideAndTallProvider()
	{
		return [
			'wide, contain' => [self::WIDE, 'contain', [40, 22.5], [0, 8.75]],
			'wide, cover' => [self::WIDE, 'cover', [40 * 16 / 9, 40], [-(40 * 16 / 9 - 40) / 2, 0]],
			'tall, contain' => [self::TALL, 'contain', [22.5, 40], [8.75, 0]],
			'tall, cover' => [self::TALL, 'cover', [40, 40 * 16 / 9], [0, -(40 * 16 / 9 - 40) / 2]],
		];
	}

	/**
	 * Each picture is scaled about the long or the short side, and centred
	 *
	 * @dataProvider wideAndTallProvider
	 *
	 * @param string $image
	 * @param string $fit
	 * @param float[] $size
	 * @param float[] $offset
	 */
	public function testWideAndTallPictures($image, $fit, array $size, array $offset)
	{
		$this->assertDrawnAt($this->drawn('width: 40mm; height: 40mm; object-fit: ' . $fit, $image), $size, $offset);
	}

	/**
	 * object-position values, each with where a 22.5 x 40 mm picture sits in a 40 mm square
	 *
	 * @return array
	 */
	public function positionProvider()
	{
		return [
			'keywords' => ['right bottom', [17.5, 0]],
			'vertical keyword first' => ['top right', [17.5, 0]],
			'one keyword' => ['left', [0, 0]],
			'one vertical keyword' => ['bottom', [8.75, 0]],
			'percentages' => ['100% 0%', [17.5, 0]],
			'one percentage' => ['25%', [17.5 / 4, 0]],
			'lengths' => ['5mm 2mm', [5, 2]],
			'a length and a keyword' => ['2mm center', [2, 0]],
			'unitless zero' => ['0 0', [0, 0]],
		];
	}

	/**
	 * object-position is read as background-position is: keywords, percentages of the room left and lengths
	 *
	 * @dataProvider positionProvider
	 *
	 * @param string $position
	 * @param float[] $offset
	 */
	public function testObjectPositionPlacesThePicture($position, array $offset)
	{
		$drawn = $this->drawn('width: 40mm; height: 40mm; object-fit: contain; object-position: ' . $position, self::TALL);

		$this->assertDrawnAt($drawn, [22.5, 40], $offset);
	}

	/**
	 * Values that are not positions: two horizontal keywords, an unknown unit, a number with no unit, three parts
	 *
	 * @return array
	 */
	public function invalidPositionProvider()
	{
		return [['left right'], ['10foo 0'], ['5 0'], ['left top 2mm']];
	}

	/**
	 * A position that is not one is ignored, and the picture stays centred
	 *
	 * @dataProvider invalidPositionProvider
	 *
	 * @param string $position
	 */
	public function testAnInvalidPositionLeavesThePictureCentred($position)
	{
		$drawn = $this->drawn('width: 40mm; height: 40mm; object-fit: contain; object-position: ' . $position, self::TALL);

		$this->assertDrawnAt($drawn, [22.5, 40], [8.75, 0]);
	}

	/**
	 * With fill, the default, a length in object-position still moves the picture within its box, which crops it
	 */
	public function testALengthMovesAFilledPicture()
	{
		$this->assertDrawnAt($this->drawn('width: 40mm; height: 40mm; object-position: 5mm 0'), [40, 40], [5, 0]);
	}

	/**
	 * fill, and no object-fit at all, stretch the picture to the box as before, with no clip
	 */
	public function testFillStretchesThePictureUnclipped()
	{
		foreach (['object-fit: fill', ''] as $fit) {
			$drawn = $this->drawn('width: 60mm; height: 20mm; ' . $fit);

			$this->assertNull($drawn['box']);
			$this->assertEqualsWithDelta(60, $drawn['image']['w'], 0.01);
			$this->assertEqualsWithDelta(20, $drawn['image']['h'], 0.01);
		}
	}

	/**
	 * The legacy CSS mode reads none of the three properties
	 */
	public function testTheLegacyModeIgnoresThem()
	{
		$drawn = $this->drawn('width: 60mm; height: 20mm; object-fit: cover; object-position: left top', self::PORTRAIT, ['cssMode' => CssMode::LEGACY]);
		$this->assertNull($drawn['box']);
		$this->assertEqualsWithDelta(60, $drawn['image']['w'], 0.01);
		$this->assertEqualsWithDelta(20, $drawn['image']['h'], 0.01);

		$drawn = $this->drawn('width: 60mm; aspect-ratio: 1 / 1', self::PORTRAIT, ['cssMode' => CssMode::LEGACY]);
		$this->assertEqualsWithDelta(69.6, $drawn['image']['h'], 0.01);
	}

	/**
	 * aspect-ratio values, each with the size of the image it gives
	 *
	 * @return array
	 */
	public function aspectRatioProvider()
	{
		$naturalW = 100 * 25.4 / 96;

		return [
			'a width' => ['width: 60mm; aspect-ratio: 1/1', [60, 60]],
			'a height' => ['height: 30mm; aspect-ratio: 2 / 1', [60, 30]],
			'neither, at the natural width' => ['aspect-ratio: 2', [$naturalW, $naturalW / 2]],
			'auto and a ratio keeps the picture\'s own' => ['width: 60mm; aspect-ratio: auto 3/2', [60, 69.6]],
			'auto' => ['width: 60mm; aspect-ratio: auto', [60, 69.6]],
			'a degenerate ratio is auto' => ['width: 60mm; aspect-ratio: 0 / 1', [60, 69.6]],
			'max-height follows the ratio' => ['width: 60mm; max-height: 20mm; aspect-ratio: 2/1', [40, 20]],
		];
	}

	/**
	 * aspect-ratio derives the length not given from the ratio, not from the picture, which is stretched to it
	 *
	 * @dataProvider aspectRatioProvider
	 *
	 * @param string $style
	 * @param float[] $size
	 */
	public function testAspectRatioSizesTheBox($style, array $size)
	{
		$drawn = $this->drawn($style);

		$this->assertEqualsWithDelta($size[0], $drawn['image']['w'], 0.01, 'width');
		$this->assertEqualsWithDelta($size[1], $drawn['image']['h'], 0.01, 'height');
	}

	/**
	 * aspect-ratio sets the box that object-fit then fits the picture into
	 */
	public function testAspectRatioAndCoverTogether()
	{
		$drawn = $this->drawn('width: 60mm; aspect-ratio: 3 / 1; object-fit: cover; object-position: left top');

		$this->assertEqualsWithDelta(20, $drawn['box']['h'], 0.01);
		$this->assertDrawnAt($drawn, [60, 69.6], [0, 0]);
	}

	/**
	 * A rounded image clips the fitted picture to its curved content edge, and its border goes round the box
	 */
	public function testARadiusClipsTheFittedPicture()
	{
		$drawn = $this->drawn('width: 60mm; height: 20mm; object-fit: cover; border: 1mm solid red; border-radius: 5mm');

		$this->assertSame(16, substr_count($drawn['clip'], ' c '), 'four corners of the curve');
		$this->assertStringNotContainsString(' re W n', $drawn['clip']);
		$this->assertEqualsWithDelta(60, $drawn['image']['w'], 0.01);
		$this->assertEqualsWithDelta(69.6, $drawn['image']['h'], 0.01);
	}

	/**
	 * Padding and a border go round the box, not the fitted picture
	 */
	public function testPaddingAndBorderGoRoundTheBox()
	{
		$drawn = $this->drawn('width: 60mm; height: 20mm; padding: 3mm; border: 1mm solid red; object-fit: contain');

		$this->assertEqualsWithDelta(60, $drawn['box']['w'], 0.01);
		$this->assertEqualsWithDelta(20, $drawn['box']['h'], 0.01);
		$this->assertEqualsWithDelta(15 + 4, $drawn['box']['x'], 0.01, 'inside the left margin, border and padding');
		$this->assertDrawnAt($drawn, [20 * 100 / 116, 20], [(60 - 20 * 100 / 116) / 2, 0]);
	}

	/**
	 * A linked image is clickable over its whole box, not only where the fitted picture is
	 */
	public function testALinkCoversTheBox()
	{
		$html = '<a href="https://example.com/"><img src="' . __DIR__ . self::PORTRAIT . '" style="width: 60mm; height: 20mm; object-fit: contain"></a>';
		$annotations = $this->annotations($this->render($html))[0];

		$this->assertSame(1, preg_match('/\/Rect \[([-\d.]+) [-\d.]+ ([-\d.]+) [-\d.]+\]/', $annotations, $rect));
		$this->assertEqualsWithDelta(60, ($rect[2] - $rect[1]) / Mpdf::SCALE, 0.01);
	}

	/**
	 * In a table cell, a table shrunk to fit the page shrinks the fitted picture, its box and its position alike
	 */
	public function testAnImageInAShrunkTableCell()
	{
		$wrap = '<table><tr><td>%s</td><td style="width: 150mm">x</td></tr></table>';
		$drawn = $this->drawn('width: 80mm; height: 40mm; object-fit: contain; object-position: 10mm 0', self::TALL, [], $wrap);

		$k = 80 / $drawn['box']['w'];
		$this->assertGreaterThan(1, $k, 'the table is shrunk');
		$this->assertEqualsWithDelta(40 / $k, $drawn['box']['h'], 0.01);
		$this->assertDrawnAt($drawn, [22.5 / $k, 40 / $k], [10 / $k, 0]);
	}

}
