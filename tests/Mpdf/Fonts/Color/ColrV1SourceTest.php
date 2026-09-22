<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\TestLogger;
use Mpdf\TTFontFile;

/**
 * ColrV1Source over TestEmoji-COLRv1, whose emoji each show another kind of paint - see build.py beside
 * the font. Its bounding box, which clips a glyph with no clip box of its own, runs from 20,-100 to
 * 980,850; the thumb's clip box from 50,-100 to 950,600.
 *
 * What the fixture cannot show - a paint graph that loops, runs past COLR or is ill-formed - is shown
 * with fonts built here, whose bounding box is a square of 1000 units: glyph 1 is a colour glyph, glyph
 * 2 a triangle.
 */
class ColrV1SourceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use SyntheticFonts;

	const CLIP = "q 20 -100 960 950 re W n\n";

	/**
	 * @var \Mpdf\Fonts\FileReader
	 */
	private $reader;

	/**
	 * @var TestLogger
	 */
	private $logger;

	/**
	 * @var ColrV1Source
	 */
	private $source;

	/**
	 * @var RecordingResources
	 */
	private $resources;

	/**
	 * Opens the COLRv1 fixture
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->logger = new TestLogger();
		$this->resources = new RecordingResources();

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->reader = $ttf->openFont(__DIR__ . '/../../../data/ttf/color/TestEmoji-COLRv1.ttf');
		$this->source = new ColrV1Source(new ColorFontFile($ttf, $this->reader, 1000, $this->logger));
	}

	/**
	 * Closes the font files the test opened, and removes the fonts it built
	 */
	protected function tear_down()
	{
		$this->reader->close();
		$this->closeFonts();

		parent::tear_down();
	}

	/**
	 * The face is its outline clipping a radial gradient from white to yellow, then its eyes filled
	 * black and its mouth in the colour of the text, all inside the font's bounding box
	 */
	public function testARadialGradientIsARadialShadingInsideTheOutline()
	{
		$content = $this->source->draw(12, $this->resources);

		$this->assertStringStartsWith(self::CLIP . "q\n950 350 m\n", $content);
		$this->assertStringContainsString("h\nW n\n/Sh1 sh\nQ\nq 0.000 0.000 0.000 rg\n330 400 m\n", $content, 'the eyes, black');
		$this->assertStringContainsString("f\nQ\n300 250 m\n", $content, 'the mouth, which sets no colour');
		$this->assertStringEndsWith("f\nQ\n", $content);

		$this->assertSame([['coords' => [400, 450, 0, 500, 350, 450], 'stops' => [[0, [1, 1, 1]], [1, [1, 0.8, 0.2]]]]], $this->resources->shadings);
	}

	/**
	 * The heart is a gradient over a square, kept to the heart by SRC_IN. The heart is one glyph in an
	 * opaque colour, so the square is clipped to its outline, with no soft mask
	 */
	public function testSrcInOfAnOpaqueGlyphClipsTheSourceToItsOutline()
	{
		$content = $this->source->draw(13, $this->resources);

		$heart = "500 -50 m\n80 400 l\n250 750 l\n500 600 l\n750 750 l\n920 400 l\nh\n";
		$this->assertStringStartsWith(self::CLIP . "q\n" . $heart . "W n\nq\n50 -100 m\n50 800 l\n", $content);
		$this->assertStringContainsString("W n\n/Sh1 sh\nQ\nQ\n", $content, 'the square, filled with the gradient');
		$this->assertSame([500, 750, 500, -50], $this->resources->shadings[0]['coords']);

		$this->assertSame([], $this->resources->masks);
		$this->assertSame([], $this->resources->groups);
	}

	/**
	 * The keycap is a grey square with the digit cut out of it by DEST_OUT. The digit is one contour in
	 * an opaque colour, so the square is clipped to the clip box less the digit, by even-odd
	 */
	public function testDestOutOfAnOpaqueGlyphOfOneContourClipsTheBackdropToWhereItIsNot()
	{
		$digit = "250 0 m\n250 600 l\n150 500 l\n150 600 l\n300 700 l\n350 700 l\n350 0 l\nh\n";
		$square = "q 0.600 0.600 0.600 rg\n50 -100 m\n50 800 l\n950 800 l\n950 -100 l\nh\nf\nQ\n";

		$this->assertSame(
			self::CLIP . "q 20 -100 960 950 re\n" . $digit . "W* n\n" . $square . "Q\nQ\n",
			$this->source->draw(24, $this->resources)
		);
		$this->assertSame([], $this->resources->masks);
	}

	/**
	 * A backdrop less than opaque is not its outline, so SRC_IN draws the source through a soft mask
	 */
	public function testSrcInOfAGlyphLessThanOpaqueIsASoftMask()
	{
		// PaintComposite SRC_IN, its source the red triangle, its backdrop the triangle in half-opaque red
		$paints = pack('C', 32) . self::u24(8) . pack('C', 5) . self::u24(19) . $this->solidTriangle() . $this->paintGlyph() . pack('Cnn', 2, 0, 0x2000);

		$this->assertSame("q 0 0 1000 1000 re W n\nq /SM1 gs /Fx1 Do Q\nQ\n", $this->synthetic($paints)->draw(1, $this->resources));

		list($mask, , , $inverted) = $this->resources->masks[0];
		$this->assertStringStartsWith("q /GS0.50 gs 1.000 0.000 0.000 rg\n0 0 m\n", $mask, 'the half-opaque triangle');
		$this->assertFalse($inverted);
	}

	/**
	 * A source of two triangles one over the other, which even-odd would leave empty, is cut out by
	 * DEST_OUT through an inverted soft mask
	 */
	public function testDestOutOfAGlyphOfTwoContoursIsAnInvertedSoftMask()
	{
		// PaintComposite DEST_OUT, its source glyph 3 in red, its backdrop the red triangle
		$paints = pack('C', 32) . self::u24(8) . pack('C', 8) . self::u24(19) . $this->paintGlyph(3) . pack('Cnn', 2, 0, 0x4000) . $this->solidTriangle();

		$this->assertSame("q 0 0 1000 1000 re W n\nq /SM1 gs /Fx1 Do Q\nQ\n", $this->synthetic($paints, [], [$this->twoTriangles()])->draw(1, $this->resources));

		list($mask, , , $inverted) = $this->resources->masks[0];
		$this->assertSame(2, substr_count($mask, " m\n"), 'both triangles');
		$this->assertTrue($inverted);
	}

	/**
	 * The family's faces are multiplied onto a yellow square: the square, then the faces as a group
	 * blended with it, the two drawn as an isolated group so the faces blend with nothing under the
	 * glyph
	 */
	public function testABlendModeBlendsTheSourceWithTheBackdropAlone()
	{
		$this->assertSame(self::CLIP . "/Fx2 Do\nQ\n", $this->source->draw(22, $this->resources));

		list($faces, $composite) = $this->resources->groups;
		$this->assertStringStartsWith("q 0.200 0.400 0.800 rg\n", $faces[0]);
		$this->assertFalse($faces[2]);
		$this->assertStringStartsWith("q 1.000 0.800 0.200 rg\n50 -100 m\n", $composite[0]);
		$this->assertStringEndsWith("f\nQ\nq /Multiply gs /Fx1 Do Q\n", $composite[0]);
		$this->assertTrue($composite[2], 'isolated');
	}

	/**
	 * The woman's face is a linear gradient from 400 to 600 reflected across the bounding box, from
	 * opaque red to red at a quarter: two spans each way cover it, so the shading runs from 0 to 1000,
	 * and its alpha is a soft mask of the brightness of a grey shading of the same shape
	 */
	public function testAReflectedGradientWhoseAlphaVariesIsMaskedByAGreyShading()
	{
		$content = $this->source->draw(15, $this->resources);

		$this->assertStringContainsString("W n\nq /SM1 gs\n/Sh1 sh\nQ\nQ\n", $content);

		list($colour, $alpha) = $this->resources->shadings;
		$this->assertSame([0, 0, 1000, 0], $colour['coords']);
		$this->assertSame($colour['coords'], $alpha['coords']);

		$this->assertSame(
			[[0, [1]], [0.2, [0.25]], [0.2, [0.25]], [0.4, [1]], [0.4, [1]], [0.6, [0.25]], [0.6, [0.25]], [0.8, [1]], [0.8, [1]], [1, [0.25]]],
			$alpha['stops'],
			'the stops, which the font lists last first, back to front every other span'
		);
		$this->assertSame(["/Sh2 sh\n", [20, -100, 980, 850], true, false], $this->resources->masks[0]);
	}

	/**
	 * The girl's face is green and white rings of 120 units: a radial gradient repeated out to the
	 * bounding box's furthest corner, six rings out
	 */
	public function testARepeatedRadialGradientIsDrawnOutToTheCornersOfTheClip()
	{
		$this->source->draw(16, $this->resources);

		$shading = $this->resources->shadings[0];
		$this->assertSame([500, 350, 0, 500, 350, 720], $shading['coords']);
		$this->assertCount(12, $shading['stops']);
		$this->assertSame([1 / 6, [1, 1, 1]], $shading['stops'][1]);
		$this->assertSame([1 / 6, [0.2 * 2 / 3, 0.2 * 10 / 3, 0.2 * 4 / 3]], $shading['stops'][2]);
	}

	/**
	 * Each transform is a cm, the paints under it drawn in its space
	 *
	 * @dataProvider transforms
	 *
	 * @param int    $glyph
	 * @param string $matrices Each cm, from the outermost in
	 */
	public function testATransformIsACm($glyph, $matrices)
	{
		$this->assertStringStartsWith($matrices, substr($this->source->draw($glyph, $this->resources), strlen(self::CLIP)));
	}

	/**
	 * @return array[] Each glyph drawn under transforms, and its cm operators
	 */
	public function transforms()
	{
		return [
			'an affine transform' => [17, "q 0.89999 0.10001 -0.10001 0.89999 80 0 cm\n"],
			'a uniform scale about the centre' => [14, "q 0.79999 0 0 0.79999 100.0061 70.00427 cm\n"],
			'a rotation, then a skew' => [19, "q 0.98482 -0.17361 0.17361 0.98482 0 0 cm\nq 1 0 -0.26788 1 0 0 cm\n"],
			'a move, then a scale' => [25, "q 1 0 0 1 100 0 cm\nq 0.79999 0 0 1 0 0 cm\n"],
		];
	}

	/**
	 * The thumb is turned about its centre and clipped to its own clip box, not the font's
	 */
	public function testAGlyphIsClippedToItsClipBox()
	{
		$this->assertStringStartsWith("q 50 -100 900 700 re W n\nq 0.93972 0.34194 -0.34194 0.93972 158.36664 -148.36569 cm\n", $this->source->draw(21, $this->resources));
	}

	/**
	 * The regional indicator U draws A, turned, then a stripe over it
	 */
	public function testPaintColrGlyphDrawsTheOtherGlyph()
	{
		$a = $this->source->draw(17, $this->resources);
		$u = $this->source->draw(18, $this->resources);

		$this->assertStringStartsWith(substr($a, 0, -2), $u);
		$this->assertStringEndsWith("q 0.878 0.141 0.369 rg\n50 250 m\n50 450 l\n950 450 l\n950 250 l\nh\nf\nQ\nQ\n", $u);
	}

	/**
	 * The skin tone is a sweep gradient, which is filled in its middle stop's colour, and logged
	 */
	public function testASweepGradientIsItsMiddleStopAndIsLogged()
	{
		$this->assertSame(self::CLIP . "q\n50 -100 m\n50 800 l\n950 800 l\n950 -100 l\nh\nW n\nq 0.878 0.141 0.369 rg\n20 -100 960 950 re f\nQ\nQ\nQ\n", $this->source->draw(20, $this->resources));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 20 has a sweep gradient'));
	}

	/**
	 * The flag of England's white square is a PaintVarSolid, read as the PaintSolid it varies
	 */
	public function testAVarPaintIsReadAsThePaintItVaries()
	{
		$this->assertStringStartsWith(self::CLIP . "q 1.000 1.000 1.000 rg\n50 -100 m\n", $this->source->draw(26, $this->resources));
	}

	/**
	 * A glyph with no version 1 paint is left to the next source: ColrV0Source, where the font has
	 * version 0 layers, then its outline
	 */
	public function testAGlyphWithNoPaintIsNotDrawn()
	{
		$this->assertNull($this->source->draw(2, $this->resources), 'the number sign');
	}

	/**
	 * A solid colour under a glyph fills the glyph's outline, as a version 0 layer does
	 */
	public function testASyntheticSolidGlyphFillsItsOutline()
	{
		$this->assertSame("q 0 0 1000 1000 re W n\nq 1.000 0.000 0.000 rg\n0 0 m\n500 700 l\n1000 0 l\nh\nf\nQ\nQ\n", $this->synthetic($this->solidTriangle())->draw(1, $this->resources));
	}

	/**
	 * A PaintVarLinearGradient's colour line is a VarColorLine, whose stops are four bytes longer
	 */
	public function testAVarGradientReadsItsVarColorLine()
	{
		// PaintGlyph, PaintVarLinearGradient, then its VarColorLine: two stops, each with a varIndexBase
		$paints = $this->paintGlyph()
			. pack('C', 5) . self::u24(20) . pack('n6', 0, 0, 1000, 0, 0, 1000) . pack('N', 0)
			. pack('Cn', 0, 2) . pack('n3N', 0, 0, 0x4000, 0) . pack('n3N', 0x4000, 0xFFFF, 0x4000, 0);

		$this->synthetic($paints)->draw(1, $this->resources);

		$this->assertSame([['coords' => [0, 0, 1000, 0], 'stops' => [[0, [1, 0, 0]], [1, [0, 0, 0]]]]], $this->resources->shadings);
	}

	/**
	 * A colour line of one stop is that colour throughout
	 */
	public function testAGradientOfOneStopIsASolidFill()
	{
		$paints = $this->paintGlyph() . $this->linear([0, 0, 1000, 0, 0, 1000]) . pack('Cn', 0, 1) . pack('n3', 0, 0, 0x4000);

		$this->assertStringContainsString("W n\nq 1.000 0.000 0.000 rg\n0 0 1000 1000 re f\nQ\n", $this->synthetic($paints)->draw(1, $this->resources));
		$this->assertSame([], $this->resources->shadings);
	}

	/**
	 * Circles from radius 10 to 50 over stops from -0.5 to 1 would start at a radius of -10: the
	 * shading starts where the radius is 0 instead, a sixth of the way along, in the colour there
	 */
	public function testAPaddedRadialGradientIsCutWhereItsRadiusIsZero()
	{
		$stops = pack('Cn', 0, 2) . pack('n3', -0x2000, 0, 0x4000) . pack('n3', 0x4000, 0xFFFF, 0x4000);
		$paints = $this->paintGlyph() . $this->radial([0, 0, 10, 100, 0, 50]) . $stops;

		$this->synthetic($paints)->draw(1, $this->resources);

		$shading = $this->resources->shadings[0];
		$this->assertEqualsWithDelta([-25, 0, 0, 100, 0, 50], $shading['coords'], 1e-9);
		$this->assertEqualsWithDelta([[0, [5 / 6, 0, 0]], [1, [0, 0, 0]]], $shading['stops'], 1e-9);
	}

	/**
	 * A repeated gradient whose radius grows 40 a span from 10 reaches 0 three quarters into the span
	 * before the stops': the shading starts there, a quarter of the way from red to black
	 */
	public function testARepeatedRadialGradientIsCutWhereItsRadiusIsZero()
	{
		$stops = pack('Cn', 1, 2) . pack('n3', 0, 0, 0x4000) . pack('n3', 0x4000, 0xFFFF, 0x4000);
		$paints = $this->paintGlyph() . $this->radial([500, 500, 10, 500, 500, 50]) . $stops;

		$this->synthetic($paints)->draw(1, $this->resources);

		$shading = $this->resources->shadings[0];
		$this->assertEqualsWithDelta([500, 500, 0], array_slice($shading['coords'], 0, 3), 1e-9);
		$this->assertEqualsWithDelta([0, [0.25, 0, 0]], $shading['stops'][0], 1e-9);
		$this->assertEqualsWithDelta([0.25 / 18.25, [0, 0, 0]], $shading['stops'][1], 1e-9);
	}

	/**
	 * Two stops at one offset are a hard edge across the gradient there
	 */
	public function testStopsAtOneOffsetAreAHardEdge()
	{
		$stops = pack('Cn', 0, 2) . pack('n3', 0x2000, 0, 0x4000) . pack('n3', 0x2000, 0xFFFF, 0x4000);
		$paints = $this->paintGlyph() . $this->linear([0, 0, 1000, 0, 0, 1000]) . $stops;

		$this->synthetic($paints)->draw(1, $this->resources);

		$this->assertEqualsWithDelta(
			[['coords' => [-500, 0, 1500, 0], 'stops' => [[0, [1, 0, 0]], [0.5, [1, 0, 0]], [0.5, [0, 0, 0]], [1, [0, 0, 0]]]]],
			$this->resources->shadings,
			1e-9
		);
	}

	/**
	 * A composite mode the spec does not define draws nothing, as CLEAR does, and PLUS, which PDF cannot
	 * draw, is the source over the backdrop; each is logged
	 */
	public function testAnUnknownCompositeModeIsClearAndPlusIsSourceOver()
	{
		// PaintComposite, its source then its backdrop each the red triangle
		$composite = function ($mode) {
			return pack('C', 32) . self::u24(8) . pack('C', $mode) . self::u24(19) . $this->solidTriangle() . $this->solidTriangle();
		};

		$this->assertNull($this->synthetic($composite(28))->draw(1, $this->resources));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 1 has composite mode 28, which is not in the spec and draws nothing'));

		$triangle = "q 1.000 0.000 0.000 rg\n0 0 m\n500 700 l\n1000 0 l\nh\nf\nQ\n";
		$this->assertSame("q 0 0 1000 1000 re W n\n" . $triangle . $triangle . "Q\n", $this->synthetic($composite(12))->draw(1, $this->resources));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 1 has composite mode PLUS'));
	}

	/**
	 * A paint graph that leads back to where it started stops there, and says so
	 */
	public function testAGraphThatLoopsIsDrawnOnlySoFar()
	{
		// PaintColrGlyph of glyph 1, itself
		$this->assertNull($this->synthetic(pack('Cn', 11, 1))->draw(1, $this->resources));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 1 has a paint graph that loops or nests too deep'));
	}

	/**
	 * A paint of a format the spec does not define draws nothing, and is logged
	 */
	public function testAPaintOfAnUnknownFormatIsLogged()
	{
		$this->assertNull($this->synthetic(pack('C', 33))->draw(1, $this->resources));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 1 has a paint of format 33'));
	}

	/**
	 * A graph that runs past COLR, names layers the LayerList does not have, or is ill-formed as the
	 * spec says draws nothing
	 *
	 * @dataProvider graphsDrawingNothing
	 *
	 * @param string $paints Glyph 1's paints
	 * @param int[]  $layers The LayerList, as where each layer's paint is among them
	 */
	public function testAGraphThatCannotBeDrawnDrawsNothing($paints, array $layers = [])
	{
		$this->assertNull($this->synthetic($paints, $layers)->draw(1, $this->resources));
	}

	/**
	 * @return array[] Glyph 1's paints, each drawing nothing
	 */
	public function graphsDrawingNothing()
	{
		$stops = pack('Cn', 0, 2) . pack('n3', 0, 0, 0x4000) . pack('n3', 0x4000, 0, 0x4000);

		return [
			'a paint cut short' => [pack('C', 10) . "\0"],
			'a child past the end of COLR' => [$this->paintGlyph(2, 0x7FFF00)],
			'layers past the LayerList' => [pack('CCN', 1, 2, 0) . $this->solidTriangle(), [6]],
			'a transform past the end of COLR' => [pack('C', 12) . self::u24(7) . self::u24(0x7FFF00) . $this->solidTriangle()],
			'a scale to nothing' => [pack('C', 16) . self::u24(8) . pack('n2', 0, 0) . pack('Cnn', 2, 0, 0x4000)],
			'a transparent colour' => [$this->paintGlyph() . pack('Cnn', 2, 0, 0)],
			'a colour line of no stops' => [$this->paintGlyph() . $this->linear([0, 0, 1000, 0, 0, 1000]) . pack('Cn', 0, 0)],
			'a linear gradient whose second point is its first' => [$this->paintGlyph() . $this->linear([0, 0, 0, 0, 0, 1000]) . $stops],
			'a linear gradient whose points are in a line' => [$this->paintGlyph() . $this->linear([0, 0, 1000, 0, 500, 0]) . $stops],
			'a radial gradient of one circle twice' => [$this->paintGlyph() . $this->radial([500, 500, 100, 500, 500, 100]) . $stops],
			'a radial gradient whose stops all lie where its radius is below 0' => [
				$this->paintGlyph() . $this->radial([0, 0, 10, 0, 0, 50]) . pack('Cn', 0, 2) . pack('n3', -2 * 0x4000, 0, 0x4000) . pack('n3', -1 * 0x4000, 0, 0x4000),
			],
		];
	}

	/**
	 * @param int[] $circles x0, y0, r0, x1, y1, r1
	 *
	 * @return string A PaintRadialGradient, its ColorLine to follow it
	 */
	private function radial(array $circles)
	{
		return pack('C', 6) . self::u24(16) . pack('n6', ...$circles);
	}

	/**
	 * @param int $glyph The glyph whose outline clips, the triangle unless another is given
	 * @param int $child Where the child paint is, from this one: straight after it unless otherwise
	 *
	 * @return string A PaintGlyph
	 */
	private function paintGlyph($glyph = 2, $child = 6)
	{
		return pack('C', 10) . self::u24($child) . pack('n', $glyph);
	}

	/**
	 * @return string A PaintGlyph of the triangle, filled with the palette's red
	 */
	private function solidTriangle()
	{
		return $this->paintGlyph() . pack('Cnn', 2, 0, 0x4000);
	}

	/**
	 * @param int[] $points x0, y0, x1, y1, x2, y2
	 *
	 * @return string A PaintLinearGradient, its ColorLine to follow it
	 */
	private function linear(array $points)
	{
		return pack('C', 4) . self::u24(16) . pack('n6', ...$points);
	}

	/**
	 * @param int $value
	 *
	 * @return string An Offset24
	 */
	private static function u24($value)
	{
		return substr(pack('N', $value), 1);
	}

	/**
	 * @param string   $paints Glyph 1's paints, one after another from its root, each child offset
	 *                         counted from the paint that names it
	 * @param int[]    $layers The LayerList, as where each layer's paint is among $paints
	 * @param string[] $glyphs Glyphs from 3 on, as glyf holds them
	 *
	 * @return ColrV1Source Over a font of glyphs 0 to 2, glyph 2 the triangle, and any given after
	 *                      them, and a COLR of those paints beside a CPAL of one colour, opaque red
	 */
	private function synthetic($paints, array $layers = [], array $glyphs = [])
	{
		// The header, the BaseGlyphList of glyph 1, the LayerList, then the paints
		$start = 48 + 4 * count($layers);
		$colr = pack('n2N2nN5', 1, 0, 0, 0, 0, 34, 44, 0, 0, 0)
			. pack('NnN', 1, 1, $start - 34)
			. pack('N', count($layers));
		foreach ($layers as $at) {
			$colr .= pack('N', $start + $at - 44);
		}

		$tables = $this->glyphTables(array_merge(['', '', $this->triangle()], $glyphs)) + ['COLR' => $colr . $paints, 'CPAL' => $this->cpal()];
		list($ttf, $reader) = $this->openFont($this->sfnt($tables));

		return new ColrV1Source(new ColorFontFile($ttf, $reader, 1000, $this->logger));
	}
}
