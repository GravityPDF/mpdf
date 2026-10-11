<?php

namespace Mpdf;

/**
 * An outer box-shadow is cut out of the box and fills the box grown by the spread, faded at its outer edge by the
 * blur. An inset box-shadow is drawn inside the padding box, above the background: clipped to the padding box,
 * filled from its edge in to the shape offset and shrunk by the spread, and faded at its inner edge by the blur.
 */
class BoxShadowTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The fill colour of #c00, the colour every shadow here is given
	 */
	const RED = '0.800 0.000 0.000 rg';

	/**
	 * @var RoundedBox
	 */
	private $roundedBox;

	/**
	 * @var float The height of the page the block was drawn on, in millimetres
	 */
	private $pageHeight;

	/**
	 * @var float The width of that page
	 */
	private $pageWidth;

	/**
	 * Holds the geometry the expected operators are built from
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->roundedBox = new RoundedBox();
	}

	/**
	 * The first page's content stream for a 60 x 30 mm block at 10 mm from the top left of the page, styled as given
	 *
	 * @param string $style
	 * @param array $config
	 *
	 * @return string
	 */
	private function stream($style, $config = [])
	{
		$mpdf = $this->mpdf($config + ['margin_left' => 10, 'margin_top' => 10, 'margin_header' => 0, 'margin_footer' => 0]);
		$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; ' . $style . '">x</div>');
		$this->pageHeight = $mpdf->h;
		$this->pageWidth = $mpdf->w;
		$pages = $this->pageContents($this->output($mpdf));

		return $pages[0];
	}

	/**
	 * The content streams of the first two pages for a 60 mm wide block at 10 mm from the left that breaks from the
	 * first page, which it leaves at 50 mm, onto the second, which it starts at 10 mm
	 *
	 * @param string $style
	 * @param array $config
	 *
	 * @return string[]
	 */
	private function splitStreams($style, $config = [])
	{
		$mpdf = $this->mpdf($config + ['format' => [80, 60], 'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 10, 'margin_header' => 0, 'margin_footer' => 0]);
		$mpdf->WriteHTML('<div style="' . $style . '">' . str_repeat('Line<br>', 12) . '</div>');
		$this->pageHeight = $mpdf->h;
		$this->pageWidth = $mpdf->w;

		return array_slice($this->pageContents($this->output($mpdf)), 0, 2);
	}

	/**
	 * A point in millimetres from the top left of the page, as the operators write it
	 *
	 * @param float $x
	 * @param float $y
	 *
	 * @return string
	 */
	private function point($x, $y)
	{
		return sprintf('%.3F %.3F', $x * Mpdf::SCALE, ($this->pageHeight - $y) * Mpdf::SCALE);
	}

	/**
	 * A rectangle operator for the box given in millimetres from the top left
	 *
	 * @param float $x
	 * @param float $y
	 * @param float $w
	 * @param float $h
	 *
	 * @return string
	 */
	private function rect($x, $y, $w, $h)
	{
		return sprintf('%.3F %.3F %.3F %.3F re ', $x * Mpdf::SCALE, ($this->pageHeight - $y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$h * Mpdf::SCALE);
	}

	/**
	 * The radii of a box whose every corner is rounded by $r, or square
	 *
	 * @param float $r
	 *
	 * @return array
	 */
	private function radii($r = 0)
	{
		return ['TL' => [$r, $r], 'TR' => [$r, $r], 'BR' => [$r, $r], 'BL' => [$r, $r]];
	}

	/**
	 * The clip an inset shadow opens with: the padding box given, clipped to with the nonzero rule, and the start of
	 * what the shadow draws inside it. The background's own clip to a borderless box is the same path, followed by a
	 * space.
	 *
	 * @param float $x0
	 * @param float $y0
	 * @param float $x1
	 * @param float $y1
	 * @param array $radii
	 *
	 * @return string
	 */
	private function insetClip($x0, $y0, $x1, $y1, $radii)
	{
		return ' q 0 w ' . $this->roundedBox->path($this->pageHeight, $x0, $y0, $x1, $y1, $radii) . ' W n' . "\n";
	}

	/**
	 * The end of the clip an outer shadow opens with: the whole page, less the box, so the shadow falls outside it
	 *
	 * @return string
	 */
	private function outerClip()
	{
		return sprintf('%.3F 0 l 0 0 l 0 %.3F l W n', $this->pageWidth * Mpdf::SCALE, $this->pageHeight * Mpdf::SCALE);
	}

	/**
	 * The whole clip an outer shadow opens with, cutting the square box given out of the page
	 *
	 * @param float $x0
	 * @param float $y0
	 * @param float $x1
	 * @param float $y1
	 *
	 * @return string
	 */
	private function outerClipAround($x0, $y0, $x1, $y1)
	{
		return ' q 0 w ' . $this->roundedBox->path($this->pageHeight, $x0, $y0, $x1, $y1, $this->radii())
			. sprintf('0 %.3F m %.3F %.3F l ', $this->pageHeight * Mpdf::SCALE, $this->pageWidth * Mpdf::SCALE, $this->pageHeight * Mpdf::SCALE)
			. $this->outerClip() . "\n";
	}

	/**
	 * The path of a square shape run clockwise from the top right, closed
	 *
	 * @param float $x0
	 * @param float $y0
	 * @param float $x1
	 * @param float $y1
	 *
	 * @return string
	 */
	private function squarePath($x0, $y0, $x1, $y1)
	{
		return $this->point($x1, $y0) . ' m ' . $this->point($x1, $y1) . ' l ' . $this->point($x0, $y1) . ' l '
			. $this->point($x0, $y0) . ' l ' . $this->point($x1, $y0) . ' l h ';
	}

	/**
	 * The operators that fill a square-cornered shadow between the rectangle given and the shape whose corners are
	 * given clockwise from the top right, in millimetres
	 *
	 * @param float[] $rect x, y, w, h
	 * @param float[] $shape x0, y0, x1, y1
	 *
	 * @return string
	 */
	private function squareFill($rect, $shape)
	{
		return $this->rect(...$rect) . $this->squarePath(...$shape) . 'f* Q';
	}

	/**
	 * The start of the fill path of a shadow whose shape is rounded: from the top of the top right corner, round it
	 *
	 * @param float $x1 The right edge of the shape
	 * @param float $y0 Its top edge
	 * @param float $r The radius of its corner
	 *
	 * @return string
	 */
	private function roundedCorner($x1, $y0, $r)
	{
		$mag = BoxShadow::MAG;

		return $this->point($x1 - $r, $y0) . ' m ' . $this->point($x1 - $r + $r * $mag, $y0) . ' ' . $this->point($x1, $y0 + $r - $r * $mag) . ' ' . $this->point($x1, $y0 + $r) . ' c ';
	}

	/**
	 * The two CSS modes: an inset shadow drawn outside the box is a misread value, so both draw it inside
	 *
	 * @return array[]
	 */
	public function modes()
	{
		return [
			'standard' => [[]],
			'legacy' => [['cssMode' => CssMode::LEGACY]],
		];
	}

	/**
	 * An inset shadow with an offset is clipped to the padding box, not to the page outside it, and fills the box
	 * from its edges to the box moved by the offset
	 *
	 * @dataProvider modes
	 *
	 * @param array $config
	 */
	public function testAnInsetShadowIsDrawnInsideThePaddingBox($config)
	{
		$stream = $this->stream('box-shadow: inset 2mm 2mm #c00', $config);

		$this->assertStringContainsString($this->insetClip(10, 10, 70, 40, $this->radii()), $stream);
		$this->assertStringNotContainsString($this->outerClip(), $stream);
		$this->assertStringContainsString('1 0 0 1 5.6693 -5.6693 cm', $stream);
		$this->assertStringContainsString(self::RED, $stream);
		$this->assertStringContainsString($this->squareFill([8, 8, 64, 34], [10, 10, 70, 40]), $stream);
	}

	/**
	 * The spread shrinks the shape the shadow surrounds by its length on every side
	 */
	public function testTheSpreadShrinksTheShapeTheShadowSurrounds()
	{
		$stream = $this->stream('box-shadow: inset 0 0 0 3mm #c00');

		$this->assertStringNotContainsString(' cm', $stream);
		$this->assertStringContainsString($this->squareFill([10, 10, 60, 30], [13, 13, 67, 37]), $stream);
	}

	/**
	 * A blur fades the inner edge through a translucent patch mesh over the ring the fade crosses, which starts
	 * half the blur outside the padding box and ends half the blur inside it
	 */
	public function testTheBlurFadesTheInnerEdgeThroughAMesh()
	{
		$stream = $this->stream('box-shadow: inset 0 0 4mm #c00');

		$clip = strpos($stream, $this->insetClip(10, 10, 70, 40, $this->radii()));
		$this->assertNotFalse($clip);
		$this->assertStringContainsString($this->roundedCorner(72, 8, 4), $stream);
		$this->assertStringContainsString(' q  ' . $this->rect(8, 8, 64, 34) . 'W n ', $stream);
		$this->assertGreaterThan($clip, strpos($stream, '/TGS1 gs /Sh1 sh'));
	}

	/**
	 * In a box with a border-radius the clip follows the padding edge's radii, the border's less, and the shape
	 * inside is rounded by those less the spread. mPDF adds the border to the 60 mm width and takes it from the 30 mm
	 * height.
	 */
	public function testTheShadowFollowsTheInnerRadii()
	{
		$stream = $this->stream('border: 2mm solid #000; border-radius: 6mm; box-shadow: inset 0 0 0 1mm #c00');

		$this->assertStringContainsString($this->insetClip(12, 12, 72, 38, $this->radii(4)), $stream);
		$this->assertStringContainsString($this->roundedCorner(71, 13, 3) . $this->point(71, 34) . ' l ', $stream);
	}

	/**
	 * An inset shadow is drawn after the background colour, so it lies over it
	 */
	public function testAnInsetShadowLiesAboveTheBackgroundColour()
	{
		$stream = $this->stream('background-color: #eee; box-shadow: inset 2mm 2mm #c00');

		$background = strpos($stream, '0.933 0.933 0.933 rg');
		$this->assertNotFalse($background);
		$this->assertGreaterThan($background, strpos($stream, $this->insetClip(10, 10, 70, 40, $this->radii())));
	}

	/**
	 * An inset shadow is drawn after a background gradient too
	 */
	public function testAnInsetShadowLiesAboveABackgroundGradient()
	{
		$stream = $this->stream('background-image: linear-gradient(#fff, #000); box-shadow: inset 2mm 2mm #c00');

		$gradient = strpos($stream, '/Sh1 sh');
		$this->assertNotFalse($gradient);
		$this->assertGreaterThan($gradient, strpos($stream, $this->insetClip(10, 10, 70, 40, $this->radii())));
	}

	/**
	 * A box with an outer and an inset shadow gets the outer one outside it, below the background, and the inset one
	 * inside it, above
	 */
	public function testAnOuterAndAnInsetShadowShareABox()
	{
		$stream = $this->stream('background-color: #eee; box-shadow: 2mm 2mm #00c, inset 2mm 2mm #c00');

		$this->assertSame(1, substr_count($stream, $this->outerClip()));
		$this->assertSame(1, substr_count($stream, $this->insetClip(10, 10, 70, 40, $this->radii())));
		$outer = strpos($stream, '0.000 0.000 0.800 rg');
		$background = strpos($stream, '0.933 0.933 0.933 rg');
		$inset = strpos($stream, self::RED);
		$this->assertLessThan($background, $outer);
		$this->assertLessThan($inset, $background);
	}

	/**
	 * A spread wider than the box closes the shape, and the shadow fills the box with nothing to fade to
	 */
	public function testASpreadWiderThanTheBoxFillsIt()
	{
		$stream = $this->stream('box-shadow: inset 0 0 2mm 20mm #c00');

		$this->assertStringContainsString(self::RED . "\n" . $this->rect(10, 10, 60, 30) . 'f Q', $stream);
		$this->assertStringNotContainsString(' sh', $stream);
	}

	/**
	 * An outer shadow with an offset is cut out of the box with the page around it, applies the offset as a
	 * transformation, and fills the box itself, which the offset moves clear
	 */
	public function testAnOuterShadowIsCutOutOfTheBox()
	{
		$stream = $this->stream('box-shadow: 2mm 2mm #c00');

		$clip = strpos($stream, $this->outerClipAround(10, 10, 70, 40) . '1 0 0 1 5.6693 -5.6693 cm' . "\n" . ' q ' . self::RED);
		$this->assertNotFalse($clip);
		$this->assertGreaterThan($clip, strpos($stream, $this->squarePath(10, 10, 70, 40) . 'f Q'));
		$this->assertStringNotContainsString(' sh', $stream);
	}

	/**
	 * The spread grows the shape by its length on every side, and rounds its square corners by that length
	 */
	public function testTheSpreadGrowsTheShape()
	{
		$stream = $this->stream('box-shadow: 0 0 0 3mm #c00');

		$this->assertStringNotContainsString(' cm', $stream);
		$this->assertStringContainsString($this->roundedCorner(73, 7, 3) . $this->point(73, 40) . ' l ', $stream);
	}

	/**
	 * A blur keeps the shape solid up to half the blur inside its edge, where square corners come to a point, and
	 * fades it through a translucent patch mesh out to half the blur outside it
	 */
	public function testTheBlurFadesTheOuterEdgeThroughAMesh()
	{
		$stream = $this->stream('box-shadow: 0 0 4mm #c00');

		$fill = strpos($stream, $this->squarePath(12, 12, 68, 38) . 'f Q');
		$this->assertNotFalse($fill);
		$this->assertGreaterThan($fill, strpos($stream, ' q  ' . $this->rect(8, 8, 64, 34) . 'W n '));
		$this->assertGreaterThan($fill, strpos($stream, '/TGS1 gs /Sh1 sh'));
	}

	/**
	 * An elliptical corner tighter than the blur on its short side only keeps its long radius, less half the blur,
	 * on the inner edge of the fade, so the solid part follows the curve
	 */
	public function testTheFadeFollowsTheLongRadiusOfAnEllipticalCorner()
	{
		$stream = $this->stream('border-radius: 12mm / 2mm; box-shadow: 0 0 6mm #c00');

		$this->assertStringContainsString(' q ' . self::RED . "\n" . $this->point(58, 13) . ' m ', $stream);
	}

	/**
	 * An outer shadow is cut out of the background's box, the padding box under background-clip: padding-box, and
	 * cast by the border box
	 */
	public function testAnOuterShadowIsCutOutOfTheBackgroundsBox()
	{
		$stream = $this->stream('border: 2mm solid #000; background-clip: padding-box; box-shadow: 0 0 0 1mm #c00');

		$this->assertStringContainsString($this->outerClipAround(12, 12, 72, 38), $stream);
		$this->assertStringContainsString($this->roundedCorner(75, 9, 1) . $this->point(75, 40) . ' l ', $stream);
	}

	/**
	 * The ways a block keeps its shadow on the edges where it breaks: box-decoration-break: clone, and legacy mode,
	 * which does not read box-decoration-break for shadows
	 *
	 * @return array[]
	 */
	public function shadowedBreaks()
	{
		return [
			'clone' => ['box-decoration-break: clone; ', []],
			'legacy' => ['', ['cssMode' => CssMode::LEGACY]],
		];
	}

	/**
	 * Under box-decoration-break: slice, the default, an outer shadow is cut off at the bottom of the first page and
	 * the top of the second, and its shape runs on past the page edge there, so none of its edge shows at the break
	 */
	public function testASlicedOuterShadowIsCutOffAtTheBreak()
	{
		list($first, $second) = $this->splitStreams('box-shadow: 0 0 0 2mm #c00');

		$this->assertStringContainsString(' q 0 w ' . $this->rect(0, 0, 80, 50) . 'W n ', $first);
		$this->assertStringContainsString($this->roundedCorner(72, 8, 2) . $this->point(72, 63) . ' l ', $first);

		$this->assertStringContainsString(' q 0 w ' . $this->rect(0, 10, 80, 50) . 'W n ', $second);
		$this->assertStringContainsString(' q ' . self::RED . "\n" . $this->point(70, -5) . ' m ', $second);
	}

	/**
	 * Under box-decoration-break: slice an inset shadow stays clipped to the part of the padding box on the page,
	 * and fills to a shape that runs on past the page edge where the block breaks, so it has no edge there
	 */
	public function testASlicedInsetShadowHasNoEdgeAtTheBreak()
	{
		list($first, $second) = $this->splitStreams('box-shadow: inset 0 0 0 2mm #c00');

		$this->assertStringContainsString($this->insetClip(10, 10, 70, 50, $this->radii()), $first);
		$this->assertStringContainsString($this->squareFill([10, 10, 60, 53], [12, 12, 68, 61]), $first);

		$this->assertStringContainsString(' q ' . self::RED . "\n" . sprintf('%.3F %.3F %.3F ', 10 * Mpdf::SCALE, ($this->pageHeight + 3) * Mpdf::SCALE, 60 * Mpdf::SCALE), $second);
		$this->assertStringContainsString($this->point(68, -1) . ' m ', $second);
	}

	/**
	 * Under box-decoration-break: clone, and in legacy mode, each part of a block on a page gets the whole shadow
	 * round it, the edges at the break included
	 *
	 * @dataProvider shadowedBreaks
	 *
	 * @param string $style
	 * @param array $config
	 */
	public function testAClonedShadowIsDrawnRoundEachPart($style, $config)
	{
		list($first, $second) = $this->splitStreams($style . 'box-shadow: 0 0 0 2mm #00c, inset 0 0 0 2mm #c00', $config);

		$this->assertStringNotContainsString($this->rect(0, 0, 80, 50) . 'W n ', $first);
		$this->assertStringContainsString($this->outerClipAround(10, 10, 70, 50), $first);
		$this->assertStringContainsString($this->roundedCorner(72, 8, 2) . $this->point(72, 50) . ' l ', $first);
		$this->assertStringContainsString($this->squareFill([10, 10, 60, 40], [12, 12, 68, 48]), $first);

		$this->assertStringContainsString($this->roundedCorner(72, 8, 2), $second);
		$this->assertStringContainsString($this->point(68, 12) . ' m ', $second);
	}
}
