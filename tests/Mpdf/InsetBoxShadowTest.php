<?php

namespace Mpdf;

/**
 * An inset box-shadow is drawn inside the padding box, above the background: clipped to the padding box, filled from
 * its edge in to the shape offset and shrunk by the spread, and faded at its inner edge by the blur
 */
class InsetBoxShadowTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The fill colour of #c00, the colour every shadow here is given
	 */
	const RED = '0.800 0.000 0.000 rg';

	/**
	 * @var float The height of the page the block was drawn on, in millimetres
	 */
	private $pageHeight;

	/**
	 * @var float The width of that page
	 */
	private $pageWidth;

	/**
	 * @var string The document the block was drawn in
	 */
	private $pdf;

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
		$this->pdf = $this->output($mpdf);
		$pages = $this->pageContents($this->pdf);

		return $pages[0];
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
	 * The clip an inset shadow opens with: the padding box given, clipped to with the nonzero rule, and the state the
	 * shadow is then drawn in opened. The background's own clip to a borderless box is the same path, followed by a
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
		return ' q 0 w ' . (new RoundedBox())->path($this->pageHeight, $x0, $y0, $x1, $y1, $radii) . ' W n' . "\n q ";
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
		list($x0, $y0, $x1, $y1) = $shape;

		return call_user_func_array([$this, 'rect'], $rect)
			. $this->point($x1, $y0) . ' m ' . $this->point($x1, $y1) . ' l ' . $this->point($x0, $y1) . ' l '
			. $this->point($x0, $y0) . ' l ' . $this->point($x1, $y0) . ' l h f* Q';
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
		$mag = InsetBoxShadow::MAG;

		return $this->point($x1 - $r, $y0) . ' m ' . $this->point($x1 - $r + $r * $mag, $y0) . ' ' . $this->point($x1, $y0 + $r - $r * $mag) . ' ' . $this->point($x1, $y0 + $r) . ' c ';
	}

	/**
	 * An inset shadow with an offset is clipped to the padding box, not to the page outside it, and fills the box
	 * from its edges to the box moved by the offset
	 */
	public function testAnInsetShadowIsDrawnInsideThePaddingBox()
	{
		$stream = $this->stream('box-shadow: inset 2mm 2mm #c00');

		$this->assertStringContainsString($this->insetClip(10, 10, 70, 40, $this->radii()), $stream);
		$this->assertStringNotContainsString($this->outerClip(), $stream);
		$this->assertStringContainsString(' q 1 0 0 1 5.6693 -5.6693 cm', $stream);
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
		$this->assertStringContainsString('/ShadingType 6', $this->pdf);
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
	 * An inset shadow drawn outside the box is a misread value, so legacy mode draws it inside too
	 */
	public function testLegacyModeDrawsTheInsetShadowInsideToo()
	{
		$stream = $this->stream('box-shadow: inset 2mm 2mm #c00', ['cssMode' => CssMode::LEGACY]);

		$this->assertStringContainsString($this->insetClip(10, 10, 70, 40, $this->radii()), $stream);
		$this->assertStringNotContainsString($this->outerClip(), $stream);
		$this->assertStringContainsString(' q 1 0 0 1 5.6693 -5.6693 cm', $stream);
	}

	/**
	 * A spread wider than the box closes the shape, and the shadow fills the box without a warning
	 */
	public function testASpreadWiderThanTheBoxFillsIt()
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<div style="width: 60mm; height: 30mm; box-shadow: inset 0 0 2mm 20mm #c00">x</div>');
		});
		$pages = $this->pageContents($pdf);

		$this->assertStringContainsString(self::RED, $pages[0]);
		$this->assertStringContainsString('/Sh1 sh', $pages[0]);
	}
}
