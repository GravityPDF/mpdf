<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
use Mpdf\Css\NormalizeProperties;
use Mpdf\Css\Outline;
use Psr\Log\NullLogger;

/**
 * CSS outline: read from outline and its longhands, and drawn in standard mode outside the border box of a block, a
 * table cell, an image or each piece of an inline element, outline-offset away from it and after the content. It
 * takes no space. The rings are read back from the page as the bounding boxes of their two edges, in millimetres.
 */
class OutlineTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @var \Mpdf\Mpdf The document the converters work for
	 */
	private $converters;

	/**
	 * @var \Mpdf\SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	private $colorConverter;

	/**
	 * Builds the converters the parser takes
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->converters = new Mpdf();
		$colorModeConverter = new ColorModeConverter();
		$this->sizeConverter = new SizeConverter(96, 11, $this->converters, new NullLogger());
		$this->colorConverter = new ColorConverter($this->converters, $colorModeConverter, new ColorSpaceRestrictor($this->converters, $colorModeConverter));
	}

	/**
	 * Lets the converters go
	 */
	protected function tear_down()
	{
		unset($this->converters, $this->sizeConverter, $this->colorConverter);

		parent::tear_down();
	}

	/**
	 * The outline the parser reads from the given properties
	 *
	 * @param array $properties
	 *
	 * @return array|null
	 */
	private function parse(array $properties)
	{
		return Outline::parse($properties, $this->sizeConverter, $this->colorConverter, 4);
	}

	/**
	 * Each outline ring filled on a page: the bounding boxes of its outer and inner edges, as [x0, y0, x1, y1] in
	 * millimetres from the top left of an A4 page, and the colour it is filled with
	 *
	 * @param string $stream
	 *
	 * @return array[] Keyed outer, inner and colour
	 */
	private function rings($stream)
	{
		preg_match_all('/q ((?:(?:[-\d.]+ ){4}re W n )?)([\d. ]+rg) ((?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c |h )+)f\* Q/', $stream, $matches, PREG_SET_ORDER);

		$rings = [];
		foreach ($matches as $match) {
			$edges = array_values(array_filter(array_map('trim', explode(' h ', ' ' . $match[3]))));
			$rings[] = [
				'outer' => $this->bounds($edges[0]),
				'inner' => isset($edges[1]) ? $this->bounds($edges[1]) : null,
				'colour' => $match[2],
				'clipped' => $match[1] !== '',
			];
		}

		return $rings;
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
		$numbers = array_map('floatval', preg_split('/\s+/', trim(preg_replace('/[mlch]/', ' ', $path))));
		$xs = $ys = [];
		for ($i = 0; $i + 1 < count($numbers); $i += 2) {
			$xs[] = $numbers[$i] / Mpdf::SCALE;
			$ys[] = 297 - $numbers[$i + 1] / Mpdf::SCALE;
		}

		return [round(min($xs), 2), round(min($ys), 2), round(max($xs), 2), round(max($ys), 2)];
	}

	/**
	 * Asserts a box is another grown by a distance on every side
	 *
	 * @param float[] $box
	 * @param float[] $inside
	 * @param float $by
	 */
	private function assertGrownBy($box, $inside, $by)
	{
		$this->assertEqualsWithDelta([$inside[0] - $by, $inside[1] - $by, $inside[2] + $by, $inside[3] + $by], $box, 0.02);
	}

	/**
	 * The parser reads each longhand: width, style, colour and offset
	 */
	public function testReadsTheLonghands()
	{
		$outline = $this->parse(['OUTLINE-STYLE' => 'dashed', 'OUTLINE-WIDTH' => '2mm', 'OUTLINE-COLOR' => '#ff0000', 'OUTLINE-OFFSET' => '1em']);

		$this->assertSame('dashed', $outline['style']);
		$this->assertEqualsWithDelta(2, $outline['w'], 0.001);
		$this->assertEqualsWithDelta(4, $outline['offset'], 0.001);
		$this->assertSame($this->colorConverter->convert('#ff0000'), $outline['c']);
	}

	/**
	 * Nothing is drawn with no style, none, hidden, a width of 0 or a transparent colour, and auto is drawn solid
	 * with the initial width, medium
	 */
	public function testStylesThatDrawNothing()
	{
		$this->assertNull($this->parse(['OUTLINE-WIDTH' => '2mm', 'OUTLINE-COLOR' => 'red']));
		$this->assertNull($this->parse(['OUTLINE-STYLE' => 'none', 'OUTLINE-COLOR' => 'red']));
		$this->assertNull($this->parse(['OUTLINE-STYLE' => 'hidden', 'OUTLINE-COLOR' => 'red']));
		$this->assertNull($this->parse(['OUTLINE-STYLE' => 'solid', 'OUTLINE-WIDTH' => '0', 'OUTLINE-COLOR' => 'red']));
		$this->assertNull($this->parse(['OUTLINE-STYLE' => 'solid', 'OUTLINE-COLOR' => 'transparent']));

		$outline = $this->parse(['OUTLINE-STYLE' => 'auto', 'OUTLINE-COLOR' => 'red']);
		$this->assertSame('solid', $outline['style']);
		$this->assertEqualsWithDelta(3 * 25.4 / 96, $outline['w'], 0.001);
		$this->assertSame(0, $outline['offset']);
	}

	/**
	 * The shorthand sets all three longhands, in any order, an omitted part taking its initial value. auto is a style
	 * and invert a colour; a value that is not an outline is dropped
	 *
	 * @dataProvider shorthandProvider
	 *
	 * @param string $value
	 * @param array $expected
	 */
	public function testShorthand($value, array $expected)
	{
		$normalizer = new NormalizeProperties($this->converters, $this->sizeConverter, $this->colorConverter);

		$this->assertSame($expected, $normalizer->normalize(['OUTLINE' => $value]));
	}

	/**
	 * Outline shorthands and the longhands they set
	 *
	 * @return array
	 */
	public function shorthandProvider()
	{
		return [
			'all three' => ['2px dashed red', ['OUTLINE-WIDTH' => '2px', 'OUTLINE-STYLE' => 'dashed', 'OUTLINE-COLOR' => 'red']],
			'reordered' => ['#00f thick dotted', ['OUTLINE-WIDTH' => 'thick', 'OUTLINE-STYLE' => 'dotted', 'OUTLINE-COLOR' => '#00f']],
			'style alone' => ['solid', ['OUTLINE-WIDTH' => 'medium', 'OUTLINE-STYLE' => 'solid', 'OUTLINE-COLOR' => 'currentcolor']],
			'auto' => ['auto 1mm', ['OUTLINE-WIDTH' => '1mm', 'OUTLINE-STYLE' => 'solid', 'OUTLINE-COLOR' => 'currentcolor']],
			'invert' => ['solid invert', ['OUTLINE-WIDTH' => 'medium', 'OUTLINE-STYLE' => 'solid', 'OUTLINE-COLOR' => 'currentcolor']],
			'not an outline' => ['solid wavy', []],
		];
	}

	/**
	 * A block's outline is a ring outside its border box, outline-offset away, as wide as outline-width, drawn over
	 * its content
	 */
	public function testBlockOutlineIsARingOutsideTheBorderBox()
	{
		$page = $this->pages($this->render('<div style="width: 40mm; border: 1mm solid #00f; outline: 2mm solid #f00; outline-offset: 3mm">Text</div>'))[0];

		$rings = $this->rings($page);
		$this->assertCount(1, $rings);
		$this->assertSame('1.000 0.000 0.000 rg', $rings[0]['colour']);
		// 40 mm of content and 2 mm of borders, from the left margin of 15 mm and the top margin of 16 mm
		$inner = $rings[0]['inner'];
		$this->assertEqualsWithDelta([15 - 3, 16 - 3, 57 + 3], array_slice($inner, 0, 3), 0.02);
		$this->assertGrownBy($rings[0]['outer'], $inner, 2);
		$this->assertGreaterThan(strpos($page, '(Text)'), strpos($page, 'f* Q'), 'the outline is drawn after the text');
	}

	/**
	 * An outline takes no space: what follows the block is where it is without one
	 */
	public function testOutlineTakesNoSpace()
	{
		$html = '<div style="border: 1mm solid #00f; %s">One</div><p>Two</p>';
		$without = $this->pages($this->render(sprintf($html, '')))[0];
		$with = $this->pages($this->render(sprintf($html, 'outline: 5mm solid #f00; outline-offset: 2mm')))[0];

		$this->assertSame(1, preg_match('/BT ([-\d.]+ [-\d.]+) Td\s+\(Two\)/', $without, $a));
		$this->assertSame(1, preg_match('/BT ([-\d.]+ [-\d.]+) Td\s+\(Two\)/', $with, $b));
		$this->assertSame($a[1], $b[1]);
	}

	/**
	 * An outline with no colour is drawn in the element's colour
	 */
	public function testOutlineWithNoColourTakesTheElementsColour()
	{
		$rings = $this->rings($this->pages($this->render('<div style="color: #0f0; outline: 1mm solid">Text</div>'))[0]);

		$this->assertSame('0.000 1.000 0.000 rg', $rings[0]['colour']);
	}

	/**
	 * A rounded block's outline follows its corners: the outer edge is still the border box grown by offset and width
	 */
	public function testRoundedOutlineFollowsTheCorners()
	{
		$page = $this->pages($this->render('<div style="width: 40mm; border-radius: 3mm; background: #ccc; outline: 1mm solid #f00; outline-offset: 1mm">&nbsp;</div>'))[0];

		$rings = $this->rings($page);
		$this->assertGrownBy($rings[0]['outer'], $rings[0]['inner'], 1);
		$this->assertEqualsWithDelta([15 - 2, 16 - 2, 55 + 2], array_slice($rings[0]['outer'], 0, 3), 0.02);
		preg_match('/rg ((?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c )+)h /', $page, $outer);
		$this->assertSame(4, substr_count($outer[1], ' c ') / 4, 'each corner of the outer edge is an arc of four curves');
	}

	/**
	 * Under the legacy CSS mode, nothing is drawn
	 */
	public function testLegacyModeDrawsNoOutline()
	{
		$html = '<div style="outline: 2mm solid #f00">Block</div><p><span style="outline: 1mm solid #f00">span</span></p>'
			. '<table><tr><td style="outline: 1mm solid #f00">cell</td></tr></table>';

		$this->assertSame([], $this->rings($this->pages($this->render($html, ['cssMode' => CssMode::LEGACY]))[0]));
		$this->assertCount(3, $this->rings($this->pages($this->render($html))[0]));
	}

	/**
	 * A block split across two pages leaves its outline open at the cut on each, as a clip to the part on that page
	 */
	public function testBlockSplitAcrossPagesIsOpenAtTheCut()
	{
		$pages = $this->pages($this->render('<div style="outline: 1mm solid #f00">' . str_repeat('Line<br>', 80) . '</div>'));

		$this->assertGreaterThanOrEqual(2, count($pages));
		foreach ([0, 1] as $page) {
			$rings = $this->rings($pages[$page]);
			$this->assertCount(1, $rings);
			$this->assertTrue($rings[0]['clipped'], 'the outline on page ' . ($page + 1) . ' is clipped at the cut');
		}
	}

	/**
	 * An image's outline is around its border box
	 */
	public function testImageOutline()
	{
		$page = $this->pages($this->render('<img src="' . __DIR__ . '/../data/img/bayeux2.jpg" style="width: 30mm; height: 10mm; border: 1mm solid #00f; outline: 1mm solid #f00; outline-offset: 2mm">'))[0];

		$rings = $this->rings($page);
		$this->assertCount(1, $rings);
		$box = $rings[0]['inner'];
		$this->assertEqualsWithDelta(32 + 4, $box[2] - $box[0], 0.02);
		$this->assertEqualsWithDelta(12 + 4, $box[3] - $box[1], 0.02);
	}

	/**
	 * A cell's outline is drawn after the cell borders, which a collapsed table draws only once the table is laid out,
	 * and around the outer edges of its borders there; in the separated model it is around the cell, inside the
	 * border spacing
	 */
	public function testCellOutline()
	{
		$collapsed = $this->pages($this->render('<table style="border-collapse: collapse"><tr><td style="border: 2mm solid #00f; outline: 1mm solid #f00">Cell</td></tr></table>'))[0];
		$rings = $this->rings($collapsed);
		$this->assertCount(1, $rings);
		$this->assertGreaterThan(strrpos($collapsed, ' RG'), strpos($collapsed, 'f* Q'), 'the outline is drawn after the borders');

		$separate = $this->pages($this->render('<table style="border-collapse: separate; border-spacing: 4mm"><tr><td style="border: 2mm solid #00f; outline: 1mm solid #f00">Cell</td></tr></table>'))[0];
		$inner = $this->rings($separate)[0]['inner'];
		$this->assertEqualsWithDelta($rings[0]['inner'][2] - $rings[0]['inner'][0], $inner[2] - $inner[0], 0.02, 'the cell is as wide with its borders in both models');
	}

	/**
	 * An inline element that wraps onto two lines is outlined on each, open on the right of the first piece and on the
	 * left of the second
	 */
	public function testInlineOutlineIsOpenWhereTheElementWraps()
	{
		$page = $this->pages($this->render('<p style="width: 40mm">Start <span style="outline: 0.5mm solid #f00">an outlined span that wraps onto another line</span> end</p>'))[0];

		$rings = $this->rings($page);
		$this->assertGreaterThanOrEqual(2, count($rings));
		$first = reset($rings);
		$last = end($rings);
		$this->assertTrue($first['clipped']);
		$this->assertTrue($last['clipped']);
		$this->assertGreaterThan($first['inner'][1], $last['inner'][1], 'the last piece is on a lower line');
	}

	/**
	 * Two neighbouring inline elements with the same outline are outlined apart, each closed
	 */
	public function testNeighbouringInlineOutlinesAreApart()
	{
		$rings = $this->rings($this->pages($this->render('<p><span style="outline: 0.5mm solid #f00">one</span><span style="outline: 0.5mm solid #f00">two</span></p>'))[0]);

		$this->assertCount(2, $rings);
		$this->assertFalse($rings[0]['clipped']);
		$this->assertFalse($rings[1]['clipped']);
	}

	/**
	 * A double outline is two rings, each a third of the width; dashed and dotted ones are stroked along the middle
	 */
	public function testStyles()
	{
		$page = $this->pages($this->render('<div style="width: 30mm; outline: 3mm double #f00">&nbsp;</div>'))[0];
		preg_match('/rg ((?:[-\d.]+ [-\d.]+ [ml] |h )+)f\* Q/', $page, $m);
		$edges = array_map([$this, 'bounds'], array_values(array_filter(array_map('trim', explode(' h ', ' ' . $m[1])))));
		$this->assertCount(4, $edges);
		$box = $edges[1];
		$this->assertEqualsWithDelta([15, 16, 45], array_slice($box, 0, 3), 0.02);
		$this->assertGrownBy($edges[0], $box, 1);
		$this->assertGrownBy($edges[1], $box, 0);
		$this->assertGrownBy($edges[2], $box, 3);
		$this->assertGrownBy($edges[3], $box, 2);

		$dashed = $this->pages($this->render('<div style="outline: 1mm dashed #f00">&nbsp;</div>'))[0];
		$this->assertMatchesRegularExpression('/0 J 0 j \[8\.504 8\.504\] 0 d [^Q]+h S Q/', $dashed);
		$dotted = $this->pages($this->render('<div style="outline: 1mm dotted #f00">&nbsp;</div>'))[0];
		$this->assertMatchesRegularExpression('/1 J 1 j \[0 5\.669\] 0 d [^Q]+h S Q/', $dotted);
	}

	/**
	 * A groove, ridge, inset or outset outline is filled in two tones, darker and lighter than its colour, each
	 * clipped to the top left or the bottom right half of the box
	 */
	public function testThreeDStylesAreTwoTone()
	{
		$converter = $this->colorConverter;
		$dark = $this->converters->SetFColor($converter->darken($converter->convert('#3366cc')), true);
		$light = $this->converters->SetFColor($converter->lighten($converter->convert('#3366cc')), true);

		$inset = $this->pages($this->render('<div style="outline: 2mm inset #3366cc">&nbsp;</div>'))[0];
		$this->assertSame(1, substr_count($inset, 'W n ' . $dark));
		$this->assertSame(1, substr_count($inset, 'W n ' . $light));
		$this->assertLessThan(strpos($inset, 'W n ' . $light), strpos($inset, 'W n ' . $dark), 'inset is dark at the top left');

		$groove = $this->pages($this->render('<div style="outline: 2mm groove #3366cc">&nbsp;</div>'))[0];
		$this->assertSame(2, substr_count($groove, 'W n ' . $dark));
		$this->assertSame(2, substr_count($groove, 'W n ' . $light));
	}
}
