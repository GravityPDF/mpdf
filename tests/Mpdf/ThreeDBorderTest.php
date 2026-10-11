<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;

/**
 * Groove, ridge, inset and outset borders on blocks, inline elements and images: drawn in standard mode in the two
 * tones a table border in the same style takes, lit from the top left, and flat in one colour in legacy mode. Each
 * side is read back from the page as the region it is clipped to and the rings filled in it.
 */
class ThreeDBorderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @var \Mpdf\Mpdf The document the converter works for
	 */
	private $converters;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	private $colorConverter;

	/**
	 * Builds the colour converter the tones are worked out with
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->converters = new Mpdf();
		$colorModeConverter = new ColorModeConverter();
		$this->colorConverter = new ColorConverter($this->converters, $colorModeConverter, new ColorSpaceRestrictor($this->converters, $colorModeConverter));
	}

	/**
	 * Lets the converter go
	 */
	protected function tear_down()
	{
		unset($this->converters, $this->colorConverter);

		parent::tear_down();
	}

	/**
	 * The fill operator of the dark and the light tone of a colour
	 *
	 * @param string $color
	 *
	 * @return string[] Keyed dark and light
	 */
	private function tones($color)
	{
		$c = $this->colorConverter->convert($color);

		return ['dark' => $this->fill($this->colorConverter->darken($c)), 'light' => $this->fill($this->colorConverter->lighten($c))];
	}

	/**
	 * The fill operator of an RGB colour
	 *
	 * @param string $c A colour as ColorConverter gives it
	 *
	 * @return string
	 */
	private function fill($c)
	{
		return sprintf('%.3F %.3F %.3F rg', ord($c[1]) / 255, ord($c[2]) / 255, ord($c[3]) / 255);
	}

	/**
	 * Each two-tone border side drawn on a page, in order: the corners of the region it is clipped to, in
	 * millimetres from the top left of an A4 page, and its rings, each the fill colour and the bounding boxes of its
	 * outer and inner edges
	 *
	 * @param string $stream
	 *
	 * @return array[] Keyed region and rings
	 */
	private function sides($stream)
	{
		preg_match_all('/q ((?:[-\d.]+ [-\d.]+ [ml] )+)h W n ((?:[\d. ]+rg (?:\/GS\d+ gs )?(?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c |h )+f\* )+)Q/', $stream, $matches, PREG_SET_ORDER);

		$sides = [];
		foreach ($matches as $match) {
			preg_match_all('/([\d. ]+rg) (?:\/GS\d+ gs )?((?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c |h )+)f\*/', $match[2], $fills, PREG_SET_ORDER);
			$rings = [];
			foreach ($fills as $fill) {
				$edges = array_values(array_filter(array_map('trim', explode(' h ', ' ' . $fill[2]))));
				$rings[] = [
					'colour' => trim($fill[1]),
					'outer' => $this->bounds($edges[0]),
					'inner' => isset($edges[1]) ? $this->bounds($edges[1]) : null,
				];
			}
			$sides[] = ['region' => $this->points($match[1]), 'rings' => $rings];
		}

		return $sides;
	}

	/**
	 * The points of a path, in millimetres from the top left of an A4 page
	 *
	 * @param string $path
	 *
	 * @return array[]
	 */
	private function points($path)
	{
		$numbers = array_map('floatval', preg_split('/\s+/', trim(preg_replace('/[mlch]/', ' ', $path))));
		$points = [];
		for ($i = 0; $i + 1 < count($numbers); $i += 2) {
			$points[] = [round($numbers[$i] / Mpdf::SCALE, 2), round(297 - $numbers[$i + 1] / Mpdf::SCALE, 2)];
		}

		return $points;
	}

	/**
	 * The bounding box of a path, in millimetres from the top left of an A4 page
	 *
	 * @param string $path
	 *
	 * @return float[]
	 */
	private function bounds($path)
	{
		$points = $this->points($path);
		$xs = array_column($points, 0);
		$ys = array_column($points, 1);

		return [min($xs), min($ys), max($xs), max($ys)];
	}

	/**
	 * The tone of each side's rings, from the outside in, keyed by the order the sides are drawn in
	 *
	 * @param array[] $sides
	 * @param string[] $tones Keyed dark and light
	 *
	 * @return string[][]
	 */
	private function sideTones(array $sides, array $tones)
	{
		$names = array_flip($tones);

		return array_map(function ($side) use ($names) {
			return array_map(function ($ring) use ($names) {
				return $names[$ring['colour']];
			}, $side['rings']);
		}, $sides);
	}

	/**
	 * Each style lights its sides from the top left, the block's sides drawn top, bottom, left, right. Inset and
	 * outset are a single ring; groove and ridge two, the outer one first
	 *
	 * @dataProvider styleProvider
	 *
	 * @param string $style
	 * @param array $expected
	 */
	public function testBlockSidesAreDrawnInTwoTones($style, array $expected)
	{
		$page = $this->pages($this->render('<div style="border: 3mm ' . $style . ' #36c">Text</div>'))[0];

		$sides = $this->sides($page);
		$this->assertSame($expected, $this->sideTones($sides, $this->tones('#36c')));
		$this->assertStringNotContainsString('0.200 0.400 0.800 RG', $page);
	}

	/**
	 * The tones of the top, bottom, left and right sides in each style, from the outside in
	 *
	 * @return array
	 */
	public function styleProvider()
	{
		return [
			'inset' => ['inset', [['dark'], ['light'], ['dark'], ['light']]],
			'outset' => ['outset', [['light'], ['dark'], ['light'], ['dark']]],
			'groove' => ['groove', [['dark', 'light'], ['light', 'dark'], ['dark', 'light'], ['light', 'dark']]],
			'ridge' => ['ridge', [['light', 'dark'], ['dark', 'light'], ['light', 'dark'], ['dark', 'light']]],
		];
	}

	/**
	 * A groove's outer ring runs from the border box to halfway across each border, and its inner ring from there to
	 * the padding box, so uneven borders are split at half of each one's width
	 */
	public function testGrooveRingsSplitEachBorderInHalf()
	{
		$page = $this->pages($this->render('<div style="width: 40mm; border-style: groove; border-color: #36c; border-width: 2mm 4mm 6mm 8mm">Text</div>'))[0];

		$top = $this->sides($page)[0];
		list($outer, $inner) = $top['rings'];
		$box = $outer['outer'];

		$this->assertEqualsWithDelta([$box[0] + 4, $box[1] + 1, $box[2] - 2, $box[3] - 3], $outer['inner'], 0.02);
		$this->assertEqualsWithDelta($outer['inner'], $inner['outer'], 0.02);
		$this->assertEqualsWithDelta([$box[0] + 8, $box[1] + 2, $box[2] - 4, $box[3] - 6], $inner['inner'], 0.02);
	}

	/**
	 * A side is cut from its neighbours along the line from each outer corner towards the inner one, so a wider left
	 * border takes more of the top left corner than the top border does
	 */
	public function testSidesMeetAlongTheLinesBetweenTheCorners()
	{
		$page = $this->pages($this->render('<div style="width: 40mm; border-style: inset; border-color: #36c; border-width: 2mm 4mm 6mm 8mm">Text</div>'))[0];

		list($top) = $this->sides($page);
		list($topLeft, $topRight, $innerRight, , $innerLeft) = $top['region'];

		// Rising 2mm for every 8mm across from the top left corner, and 2mm for every 4mm from the top right
		$this->assertEqualsWithDelta(2 / 8, ($innerLeft[1] - $topLeft[1]) / ($innerLeft[0] - $topLeft[0]), 0.01);
		$this->assertEqualsWithDelta(2 / 4, ($innerRight[1] - $topRight[1]) / ($topRight[0] - $innerRight[0]), 0.01);
	}

	/**
	 * A rounded border's rings follow its corners, the inner edge's radii smaller by the border width
	 */
	public function testRoundedBorderRingsFollowTheCorners()
	{
		$page = $this->pages($this->render('<div style="border: 3mm outset #36c; border-radius: 6mm">Text</div>'))[0];

		$sides = $this->sides($page);
		$this->assertCount(4, $sides);
		$this->assertMatchesRegularExpression('/(?:[-\d.]+ ){6}c /', $page);
		foreach ($sides as $side) {
			$this->assertNotNull($side['rings'][0]['inner']);
		}
	}

	/**
	 * Legacy mode draws the border flat in its own colour, as before
	 */
	public function testLegacyModeDrawsTheBorderFlat()
	{
		$page = $this->pages($this->render('<div style="border: 3mm groove #36c">Text</div>', ['cssMode' => 'legacy']))[0];

		$this->assertSame([], $this->sides($page));
		$this->assertStringContainsString('0.200 0.400 0.800 RG', $page);
	}

	/**
	 * A spot colour cannot be shaded, so a border in one is drawn as before
	 */
	public function testSpotColourIsDrawnAsBefore()
	{
		$mpdf = $this->mpdf();
		$mpdf->AddSpotColor('PANTONE286', 100, 66, 0, 2);
		$mpdf->WriteHTML('<div style="border: 3mm groove spot(PANTONE286, 100%)">Text</div>');
		$page = $this->pages($this->output($mpdf))[0];

		$this->assertSame([], $this->sides($page));
		$this->assertStringContainsString('/CS1 CS 1.000 SCN', $page);
	}

	/**
	 * The tones keep the alpha of a translucent colour
	 */
	public function testTranslucentColourKeepsItsAlpha()
	{
		$page = $this->pages($this->render('<div style="border: 3mm inset rgba(51, 102, 204, 0.5)">Text</div>'))[0];

		$sides = $this->sides($page);
		$this->assertCount(4, $sides);
		$this->assertMatchesRegularExpression('/rg \/GS\d+ gs /', $page);
	}

	/**
	 * A block cut by a page break is open where it is cut: the sides run square to the cut, and no top or bottom
	 * border is drawn there
	 */
	public function testBlockAcrossAPageIsOpenAtTheCut()
	{
		$pages = $this->pages($this->render('<div style="border: 3mm inset #36c">' . $this->filler(40) . '</div>'));

		$this->assertCount(3, $this->sides($pages[0]));
		$second = $this->sides($pages[1]);
		$this->assertCount(3, $second);
		// The second page draws the bottom, left and right sides; the left one starts square at the top of the page
		$left = $second[1]['region'];
		$this->assertEqualsWithDelta($left[1][1], $left[2][1], 0.01);
	}

	/**
	 * A legend sits in a gap cut out of the top border
	 */
	public function testLegendIsLeftAGapInTheTopBorder()
	{
		$page = $this->pages($this->render('<fieldset style="border: 3mm groove #36c"><legend>Legend</legend>Text</fieldset>'))[0];

		$this->assertMatchesRegularExpression('/q 0 0 [\d.]+ [\d.]+ re [-\d.]+ [-\d.]+ [\d.]+ [\d.]+ re W\* n q /', $page);
		$this->assertCount(4, $this->sides($page));
	}

	/**
	 * An inline element's sides are drawn around each piece of its text, open where it wraps: the first piece has no
	 * right side and the last no left
	 */
	public function testWrappedInlineElementIsOpenAtTheWrap()
	{
		$page = $this->pages($this->render('<div style="width: 50mm"><span style="border: 1mm ridge #36c">one two three four five six seven eight</span></div>'))[0];

		$tones = $this->sideTones($this->sides($page), $this->tones('#36c'));

		// top, bottom, left on the first line; top, bottom, right on the second
		$this->assertSame(
			[['light', 'dark'], ['dark', 'light'], ['light', 'dark'], ['light', 'dark'], ['dark', 'light'], ['dark', 'light']],
			$tones
		);
	}

	/**
	 * An image's border sides are drawn around its border box, top, left, right and bottom, and follow its rounded
	 * corners
	 */
	public function testImageBorderIsDrawnInTwoTones()
	{
		$image = __DIR__ . '/../data/img/bayeux2.jpg';
		$page = $this->pages($this->render('<img src="' . $image . '" style="width: 30mm; border: 2mm outset #36c">'))[0];

		$sides = $this->sides($page);
		$this->assertSame([['light'], ['light'], ['dark'], ['dark']], $this->sideTones($sides, $this->tones('#36c')));
		$box = $sides[0]['rings'][0]['outer'];
		$this->assertEqualsWithDelta(34, $box[2] - $box[0], 0.02);
		$this->assertEqualsWithDelta([$box[0] + 2, $box[1] + 2, $box[2] - 2, $box[3] - 2], $sides[0]['rings'][0]['inner'], 0.02);

		$rounded = $this->pages($this->render('<img src="' . $image . '" style="width: 30mm; border: 2mm outset #36c; border-radius: 4mm">'))[0];
		$this->assertCount(4, $this->sides($rounded));
		$this->assertMatchesRegularExpression('/(?:[-\d.]+ ){6}c (?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c |h )*f\*/', $rounded);
	}
}
