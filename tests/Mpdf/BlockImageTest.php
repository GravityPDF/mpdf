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
	 * A 50mm-wide image, styled as given; a width in $style replaces the 50mm
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
	 * The placement of the only image on the first page, keyed w/h/x/y in millimetres
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return float[]
	 */
	private function placement($html, $config = [])
	{
		$placements = $this->imagePlacements($html, 0, $config);
		$this->assertCount(1, $placements);

		return $placements[0];
	}

	/**
	 * The bottom edge of each piece of text on the first page, in millimetres up from the page's bottom, keyed by the
	 * text, and the bottom edge of the only image under 'image'
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return float[]
	 */
	private function bottoms($html, $config = [])
	{
		$page = $this->pages($this->render($html, $config))[0];
		preg_match_all('/BT [-\d.]+ ([-\d.]+) Td\s+\((.*?)\) Tj/', $page, $drawn, PREG_SET_ORDER);

		$bottoms = [];
		foreach ($drawn as $text) {
			$bottoms[$text[2]] = $text[1] / Mpdf::SCALE;
		}
		$placements = $this->placementsIn($page);
		$this->assertCount(1, $placements);
		$bottoms['image'] = $placements[0]['y'];

		return $bottoms;
	}

	/**
	 * @dataProvider marginProvider
	 */
	public function testTheMarginsPlaceABlockImage($style, $x, $config = [])
	{
		$this->assertEqualsWithDelta($x, $this->placement($this->image($style), $config)['x'], 0.01);
	}

	/**
	 * Each margin combination of the issue's table, with where a browser puts the 50mm image, and legacy mode's inline
	 * image
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
			'a length on each side keeps the left one' => ['display: block; margin-left: 20mm; margin-right: 200mm', 35],
			'legacy mode reads auto as 0' => ['display: block; margin: 0 auto', 15, ['cssMode' => CssMode::LEGACY]],
		];
	}

	public function testABlockImageBreaksTheLineBeforeAndAfterIt()
	{
		$bottoms = $this->bottoms('<p>before ' . $this->image('display: block; margin: 0 auto') . ' after</p>');

		$this->assertSame(['before', 'after', 'image'], array_keys($bottoms));
		$this->assertGreaterThan($bottoms['image'], $bottoms['before'], 'before is on a line above the image');
		$this->assertGreaterThan($bottoms['after'], $bottoms['image'], 'after is on a line below the image');
	}

	/**
	 * @dataProvider inlineProvider
	 */
	public function testAnInlineImageStaysOnTheLineWithItsText($style, $config)
	{
		$bottoms = $this->bottoms('<p>before ' . $this->image($style) . ' after</p>', $config);

		$this->assertEqualsWithDelta($bottoms['before '], $bottoms[' after'], 0.01);
		$this->assertEqualsWithDelta($bottoms['before '], $bottoms['image'], 0.01);
	}

	/**
	 * An image without display: block, and a block one in legacy mode
	 *
	 * @return array[]
	 */
	public function inlineProvider()
	{
		return [
			'inline' => ['margin: 0 auto', []],
			'legacy block' => ['display: block; margin: 0 auto', ['cssMode' => CssMode::LEGACY]],
		];
	}

	public function testTextAlignCentresAnInlineImage()
	{
		$this->assertEqualsWithDelta(80, $this->placement('<div style="text-align: center">' . $this->image('') . '</div>')['x'], 0.01);
	}

	/**
	 * @dataProvider textAlignProvider
	 */
	public function testTextAlignDoesNotMoveABlockImage($align)
	{
		$this->assertEqualsWithDelta(15, $this->placement('<div style="text-align: ' . $align . '">' . $this->image('display: block') . '</div>')['x'], 0.01);
	}

	/**
	 * @return array[]
	 */
	public function textAlignProvider()
	{
		return [['right'], ['center'], ['justify']];
	}

	/**
	 * The last line of a block is not justified, and the line before a block-level image is one
	 */
	public function testTheLineBeforeABlockImageIsNotJustified()
	{
		$page = $this->pages($this->render('<div style="text-align: justify">before the image ' . $this->image('display: block; margin: 0 auto') . ' after</div>'))[0];

		$this->assertDoesNotMatchRegularExpression('/BT [1-9][\d.]* Tw ET/', $page, 'no word spacing is set');
	}

	/**
	 * The border box is 64mm wide, so each auto margin is 58mm and the picture inside the 2mm border and 5mm padding
	 * is still centred
	 */
	public function testThePaddingAndBorderAreInsideTheMargins()
	{
		$placement = $this->placement($this->image('display: block; margin: 0 auto; padding: 5mm; border: 2mm solid red'));

		$this->assertEqualsWithDelta(80, $placement['x'], 0.01);
		$this->assertEqualsWithDelta(50, $placement['w'], 0.01);
	}

	/**
	 * @dataProvider rtlProvider
	 */
	public function testARightToLeftBlockPlacesTheImageFromItsRightEdge($style, $x)
	{
		$this->assertEqualsWithDelta($x, $this->placement('<div dir="rtl">' . $this->image($style) . '</div>')['x'], 0.01);
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
		$placements = $this->imagePlacements('<div>' . $this->image('float: left; width: 40mm') . $this->image('display: block; margin: 0 auto') . '</div>');

		$this->assertCount(2, $placements);
		$this->assertEqualsWithDelta(40, $placements[1]['w'], 0.01, 'the float');
		$this->assertEqualsWithDelta(100, $placements[0]['x'], 0.01, '(180 - 40 - 50) / 2 = 45mm in from the float');
	}

	public function testTwoBlockImagesTakeALineEach()
	{
		$placements = $this->imagePlacements('<p>' . $this->image('display: block; margin: 0 auto') . $this->image('display: block; margin-left: auto') . '</p>');

		$this->assertCount(2, $placements);
		$this->assertEqualsWithDelta(80, $placements[0]['x'], 0.01);
		$this->assertEqualsWithDelta(145, $placements[1]['x'], 0.01);
		$this->assertGreaterThan($placements[1]['y'], $placements[0]['y'], 'the second is below the first');
	}
}
