<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\TestLogger;
use Mpdf\TTFontFile;

/**
 * SvgSource over TestEmoji-SVG, whose emoji each show another part of what an OpenType SVG glyph can
 * say - see build.py beside the font. Every glyph starts by turning SVG's y-down space into glyph
 * space; a group or mask is drawn within the font's bounding box, 20,-100 to 980,850, which is
 * 20,-850 to 980,100 in SVG's space.
 *
 * What the fixture cannot show - a document that cannot be read, one missing a glyph, a use that uses
 * itself - is shown with fonts built here: glyph 1 has an SVG document, glyph 2 is a triangle.
 */
class SvgSourceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use SyntheticFonts;

	const START = "q\n1 0 0 -1 0 0 cm\n";

	const BOX = [20, -850, 980, 100];

	/**
	 * @var \Mpdf\Fonts\FileReader
	 */
	private $reader;

	/**
	 * @var TestLogger
	 */
	private $logger;

	/**
	 * @var SvgSource
	 */
	private $source;

	/**
	 * @var RecordingResources
	 */
	private $resources;

	/**
	 * Opens the SVG fixture
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->logger = new TestLogger();
		$this->resources = new RecordingResources();

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->reader = $ttf->openFont(__DIR__ . '/../../../data/ttf/color/TestEmoji-SVG.ttf');
		$this->source = new SvgSource(new ColorFontFile($ttf, $this->reader, 1000, $this->logger));
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
	 * The face is filled in the palette's first colour through var(), each eye is a use of one
	 * rectangle moved by its x and y, and the mouth, in currentColor, sets no colour so is the text's
	 */
	public function testShapesAreFilledInTheirColoursAndCurrentColourIsTheTexts()
	{
		$content = $this->source->draw(12, $this->resources);

		$this->assertStringStartsWith(self::START . "q 1.000 0.800 0.200 rg\n950 -350 m\n", $content);
		$this->assertStringContainsString("q\n1 0 0 1 330 -560 cm\nq 0.000 0.000 0.000 rg\n0 0 m\n90 0 l\n90 160 l\n0 160 l\nh\nf\nQ\nQ\n", $content);
		$this->assertStringContainsString("q\n1 0 0 1 580 -560 cm\nq 0.000 0.000 0.000 rg\n", $content, 'the other eye, black by default');
		$this->assertStringEndsWith("Q\nQ\n300 -250 m\n700 -250 l\n500 -120 l\nh\nf\nQ\n", $content);
	}

	/**
	 * The heart's gradient is in user space, so its shading's points are the gradient's own; the shine,
	 * a shape with no stroke, takes its opacity into its fill rather than being drawn as a group
	 */
	public function testAGradientInUserSpaceAndOpacityTakenIntoAFill()
	{
		$content = $this->source->draw(13, $this->resources);

		$this->assertStringContainsString("540 -10 l\n541.51292 16.67523 525.20518 41.13684 500 50 c\nh\nW n\n/Sh1 sh\nQ\n", $content, 'the arc at the end of the path');
		$this->assertStringContainsString("q /GS0.50 gs 1.000 1.000 1.000 rg\n280 -580 m\n", $content);
		$this->assertSame([['coords' => [500, -750, 500, 50], 'stops' => [[0, [0.878, 0.141, 0.369]], [1, [1, 0.8, 0.2]]]]], $this->rounded($this->resources->shadings));
		$this->assertSame([], $this->resources->groups);
	}

	/**
	 * The man's gradient is in his face's bounding box, which takes the gradient's space to his; its
	 * focus is off centre, and its first stop, at 0.2, is also its colour from 0. His eyes are stroked,
	 * the second at half opacity.
	 */
	public function testAGradientInTheBoundingBoxAndStrokes()
	{
		$content = $this->source->draw(14, $this->resources);

		$this->assertStringContainsString("h\nW n\n900 0 0 900 50 -800 cm\n/Sh1 sh\nQ\n", $content);
		$this->assertSame([['coords' => [0.3, 0.3, 0, 0.5, 0.5, 0.6], 'stops' => [[0, [1, 1, 1]], [0.2, [1, 1, 1]], [1, [0.2, 0.4, 0.8]]]]], $this->rounded($this->resources->shadings));
		$this->assertStringContainsString("q 15 w 0 J 0 j 4 M 1.000 1.000 1.000 RG\n420 -480 m\n", $content);
		$this->assertStringContainsString("q /GS0.50 gs 15 w 0 J 0 j 4 M 1.000 1.000 1.000 RG\n670 -480 m\n", $content);
		$this->assertSame([], $this->resources->groups);
	}

	/**
	 * The woman is clipped by two rectangles, which clip together through a soft mask of them both, and
	 * her eyes overlap in a group whose opacity is the group's, so the overlap is no darker
	 */
	public function testAClipPathOfSeveralShapesIsAMaskAndAGroupsOpacityIsItsOwn()
	{
		$content = $this->source->draw(15, $this->resources);

		$this->assertStringStartsWith(self::START . "q\n/SM1 gs\nq 0.878 0.141 0.369 rg\n950 -350 m\n", $content);
		$this->assertStringEndsWith("q /GS0.80 gs\n/Fx1 Do\nQ\nQ\n", $content);

		list($mask, $box, $luminosity) = $this->resources->masks[0];
		$this->assertSame("0 g\n0 -900 m\n480 -900 l\n480 100 l\n0 100 l\nh\nf\n520 -900 m\n1000 -900 l\n1000 100 l\n520 100 l\nh\nf\n", $mask);
		$this->assertSame(self::BOX, $box);
		$this->assertFalse($luminosity, 'a mask of the shapes\' alpha');
		$this->assertSame(2, substr_count($this->resources->groups[0][0], ' rg'), 'both eyes in the one group');
	}

	/**
	 * The girl is a PNG, placed with its top row at its y
	 */
	public function testAnImageIsDrawnInItsBox()
	{
		$this->assertSame(self::START . "q\n1000 0 0 -1000 0 100 cm\n/I1 Do\nQ\nQ\n", $this->source->draw(16, $this->resources));
		$this->assertStringStartsWith("\x89PNG", $this->resources->images[0]);
	}

	/**
	 * A reflects its gradient, whose stops' opacity differs, so the gradient is masked; U repeats its
	 * radial gradient, which is turned and squashed by its gradientTransform
	 */
	public function testSpreadMethodsAndGradientTransforms()
	{
		$this->assertStringContainsString("900 0 0 900 50 -800 cm\nq /SM1 gs\n/Sh1 sh\nQ\n", $this->source->draw(17, $this->resources));
		$this->assertSame([-0.25, 0, 1.25, 0], $this->rounded($this->resources->shadings[0]['coords']), 'reflected out to the edges of the font\'s box');
		$this->assertCount(12, $this->resources->shadings[0]['stops']);
		$this->assertSame([[0, [1]], [0.167, [0.2]]], array_slice($this->rounded($this->resources->shadings[1]['stops']), 0, 2), 'the alpha, the span before the stops\' own reflected');

		$this->assertStringContainsString("W n\n0.86603 0.5 -0.25 0.43301 -108.0127 -296.89111 cm\n/Sh3 sh\n", $this->source->draw(18, $this->resources));
		$this->assertSame([500, -350, 0, 500, -350, 1950], $this->rounded($this->resources->shadings[2]['coords']), 'repeated out to the corners');
	}

	/**
	 * The black flag's stroke is dashed and round-joined. Its text is an element a font must not use, which
	 * is logged. The square is masked by the luminosity of a white rectangle clipped to the mask's region,
	 * a tenth of the square's bounding box beyond it each way, which the rectangle lies outside: the
	 * square is hidden, as a browser hides it.
	 */
	public function testAStrokesStyleAndWhatIsNotDrawn()
	{
		$content = $this->source->draw(19, $this->resources);

		$this->assertStringContainsString("q 30 w 0 J 1 j 4 M [60 30] 0 d 0.600 0.600 0.600 RG\n180 -400 m\n", $content);
		$this->assertStringContainsString("q\n/SM1 gs\nq 0.000 0.000 0.000 rg\n0 -100 m\n", $content, 'the masked square');
		$this->assertSame(["q -5 -105 60 60 re W n\nq 1.000 1.000 1.000 rg\n0 0 m\n1000 0 l\n1000 1000 l\n0 1000 l\nh\nf\nQ\nQ\n", [20, -850, 980, 100], true, false], $this->resources->masks[0]);
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 19 has a <text> element, which a font must not use'));
		$this->assertFalse($this->logger->hasWarningThatContains('mask'));
	}

	/**
	 * The skin tone's style attribute is applied, and the style element's rule, which would make it red,
	 * is not
	 */
	public function testTheStyleAttributeIsAppliedAndAStyleElementIsNot()
	{
		$this->assertStringStartsWith(self::START . "q 0.776 0.525 0.259 rg\n50 -800 m\n", $this->source->draw(20, $this->resources));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 20 has a <style> element, whose rules are not applied'));
	}

	/**
	 * The thumb is drawn as though the baseline were at y = 1000, and the root's viewBox moves it back
	 */
	public function testTheRootsViewBoxApplies()
	{
		$this->assertStringStartsWith(self::START . "1 0 0 1 0 -1000 cm\nq 1.000 0.800 0.200 rg\n150 1050 m\n", $this->source->draw(21, $this->resources));
	}

	/**
	 * The family and the keycap share a document. The family's element takes the transform and the fill
	 * of the group it is in; the keycap's frame is filled even-odd, so its middle is empty.
	 */
	public function testTwoGlyphsShareADocumentAndAGlyphTakesFromItsAncestors()
	{
		$family = $this->source->draw(22, $this->resources);
		$this->assertStringStartsWith(self::START . "1 0 0 1 0 -50 cm\nq\n0.4 0 0 0.4 0 -300 cm\nq 0.200 0.400 0.800 rg\n", $family);
		$this->assertStringContainsString("0.4 0 0 0.4 300 0 cm\nq 0.133 0.667 0.267 rg\n", $family);

		$this->assertStringStartsWith(self::START . "q 0.600 0.600 0.600 rg\n50 100 m\n", $this->source->draw(24, $this->resources));
		$this->assertStringContainsString("850 0 l\nh\nf*\nQ\n", $this->source->draw(24, $this->resources));
	}

	/**
	 * The flag of the regional indicators A and U is gzipped
	 */
	public function testAGzippedDocumentIsRead()
	{
		$this->assertSame(
			self::START . "q 0.200 0.400 0.800 rg\n50 -800 m\n950 -800 l\n950 100 l\n50 100 l\nh\nf\nQ\n"
			. "q 1.000 1.000 1.000 rg\n50 -450 m\n950 -450 l\n950 -250 l\n50 -250 l\nh\nf\nQ\nQ\n",
			$this->source->draw(23, $this->resources)
		);
	}

	/**
	 * The thumb with a skin tone takes its gradient's stops from another by href, and is clipped to the
	 * top half of its bounding box
	 */
	public function testAGradientTakesItsStopsByHrefAndAClipPathIsInTheBoundingBox()
	{
		$this->assertStringStartsWith(
			self::START . "q\n150 -800 m\n850 -800 l\n850 -375 l\n150 -375 l\nh\nW n\nq\n150 50 m\n",
			$this->source->draw(25, $this->resources)
		);
		$this->assertSame([['coords' => [0, 1, 0, 0], 'stops' => [[0, [0.776, 0.525, 0.259]], [1, [1, 0.8, 0.2]]]]], $this->rounded($this->resources->shadings));
	}

	/**
	 * A gradient's colours are interpolated premultiplied by alpha, as CPAL asks of SVG glyphs too: a
	 * transparent red stop fading into blue has no colour of its own, so the colours are blue throughout
	 * while the mask rises from 0 to 1
	 */
	public function testAGradientsColoursAreInterpolatedPremultiplied()
	{
		$this->synthetic([self::svg(
			'<linearGradient id="fade" gradientUnits="userSpaceOnUse" x2="10"><stop offset="0" stop-color="red" stop-opacity="0"/><stop offset="1" stop-color="blue"/></linearGradient>'
			. '<rect id="glyph1" width="10" height="10" fill="url(#fade)"/>'
		)])->draw(1, $this->resources);

		list($colours, $alphas) = $this->rounded($this->resources->shadings);
		$this->assertSame([[0, [0, 0, 1]], [1, [0, 0, 1]]], $colours['stops']);
		$this->assertSame([[0, [0]], [1, [1]]], $alphas['stops']);
	}

	/**
	 * The flag of England has no document, and the number sign no colour at all: each is left to the
	 * next source
	 */
	public function testAGlyphWithNoDocumentIsNotDrawn()
	{
		$this->assertNull($this->source->draw(26, $this->resources));
		$this->assertNull($this->source->draw(2, $this->resources));
	}

	/**
	 * A document that cannot be read leaves its glyphs to the next source, and is logged once
	 */
	public function testADocumentThatCannotBeReadIsLoggedOnce()
	{
		$source = $this->synthetic(['<svg xmlns="http://www.w3.org/2000/svg"><rect id="glyph1"'], 1, 2);

		$this->assertNull($source->draw(1, $this->resources));
		$this->assertNull($source->draw(2, $this->resources));
		$this->assertCount(1, $this->logger->records);
		$this->assertTrue($this->logger->hasWarningThatContains('cannot be read, and its glyphs are drawn from their outlines'));
	}

	/**
	 * A glyph whose document has no element for it, whose record runs past the table, or whose element
	 * draws nothing, is left to the next source
	 *
	 * @dataProvider undrawnGlyphs
	 *
	 * @param string $document The document
	 * @param int    $cut      Bytes to leave off the end of the SVG table
	 */
	public function testAGlyphTheDocumentDoesNotDrawIsNotDrawn($document, $cut = 0)
	{
		$this->assertNull($this->synthetic([$document], 1, 1, $cut)->draw(1, $this->resources));
	}

	/**
	 * @return array[]
	 */
	public function undrawnGlyphs()
	{
		return [
			'no element for it' => [self::svg('<rect id="glyph2" width="10" height="10"/>')],
			'a record past the table' => [self::svg('<rect id="glyph1" width="10" height="10"/>'), 1],
			'a transparent fill' => [self::svg('<rect id="glyph1" width="10" height="10" fill="transparent"/>')],
			'nothing drawn' => [self::svg('<g id="glyph1"><text>1</text><rect width="0" height="10"/><circle r="10" display="none"/></g>')],
			'an empty viewBox' => [self::svg('<rect id="glyph1" width="10" height="10"/>', ' viewBox="0 0 0 10"')],
		];
	}

	/**
	 * A use that uses itself is drawn only so far, and logged: each time round is two levels, the group
	 * and the use
	 */
	public function testAUseThatUsesItselfIsDrawnOnlySoFar()
	{
		$content = $this->synthetic([self::svg('<g id="glyph1"><rect width="10" height="10"/><use id="loop" xlink:href="#glyph1"/></g>')])->draw(1, $this->resources);

		$this->assertSame(32, substr_count($content, '10 10 l'));
		$this->assertTrue($this->logger->hasWarningThatContains('Colour glyph 1 has elements that use each other or nest too deep'));
	}

	/**
	 * A mask's content may be in the masked element's bounding box, and its region in user space; a mask
	 * that names nothing leaves the element unmasked, and one that draws nothing hides it
	 */
	public function testAMaskInEitherUnitsAndOneThatIsNotThere()
	{
		$this->synthetic([self::svg(
			'<mask id="half" maskContentUnits="objectBoundingBox" maskUnits="userSpaceOnUse" x="0" y="0" width="500" height="500">'
			. '<rect width="0.5" height="1" fill="white"/></mask>'
			. '<rect id="glyph1" x="100" y="100" width="200" height="100" mask="url(#half)"/>'
		)])->draw(1, $this->resources);

		$this->assertSame("q 0 0 500 500 re W n\n200 0 0 100 100 100 cm\nq 1.000 1.000 1.000 rg\n0 0 m\n0.5 0 l\n0.5 1 l\n0 1 l\nh\nf\nQ\nQ\n", $this->resources->masks[0][0]);
		$this->assertTrue($this->resources->masks[0][2], 'by luminosity');

		$unmasked = $this->synthetic([self::svg('<rect id="glyph1" width="10" height="10" mask="url(#none)"/>')])->draw(1, $this->resources);
		$this->assertStringStartsWith(self::START . "q 0.000 0.000 0.000 rg\n0 0 m\n", $unmasked);

		$this->assertNull($this->synthetic([self::svg('<mask id="empty"/><rect id="glyph1" width="10" height="10" mask="url(#empty)"/>')])->draw(1, $this->resources));
	}

	/**
	 * A shape drawn many times through use, or several paths with the same data, are parsed once for the
	 * document
	 */
	public function testPathDataIsParsedOncePerDocument()
	{
		$source = $this->synthetic([self::svg(
			'<defs><path id="shape" d="M0 0 L10 0 L10 10 Z"/></defs>'
			. '<g id="glyph1"><use xlink:href="#shape"/><use xlink:href="#shape" x="20"/><path d="M0 0 L10 0 L10 10 Z"/></g>'
		)]);

		$this->assertSame(3, substr_count($source->draw(1, $this->resources), "10 10 l\n"));

		$renderer = current(self::privately($source, 'documents'));
		$this->assertCount(1, self::privately($renderer, 'paths'));
	}

	/**
	 * Colours as SVG writes them, and var() as the fallback it names where the font has no palette
	 *
	 * @dataProvider colours
	 *
	 * @param string $fill     A fill
	 * @param string $expected The colour it sets
	 */
	public function testColours($fill, $expected)
	{
		$content = $this->synthetic([self::svg('<rect id="glyph1" width="10" height="10" fill="' . $fill . '"/>')])->draw(1, $this->resources);

		$this->assertStringStartsWith(self::START . $expected . "0 0 m\n", $content);
	}

	/**
	 * @return string[][]
	 */
	public function colours()
	{
		return [
			'hex' => ['#FF8000', "q 1.000 0.502 0.000 rg\n"],
			'short hex' => ['#f80', "q 1.000 0.533 0.000 rg\n"],
			'rgb()' => ['rgb(255, 0, 50%)', "q 1.000 0.000 0.500 rg\n"],
			'a keyword' => ['DarkOrange', "q 1.000 0.549 0.000 rg\n"],
			'var() with no palette' => ['var(--color0, teal)', "q 0.000 0.502 0.502 rg\n"],
			'var() with nothing to fall back on' => ['var(--color3)', "q 0.000 0.000 0.000 rg\n"],
			'a paint server that is not there, with a fallback' => ['url(#none) #00f', "q 0.000 0.000 1.000 rg\n"],
		];
	}

	/**
	 * A viewBox is fitted to the root's width and height, by default centred and scaled evenly
	 */
	public function testAViewBoxIsFittedToTheRootsSize()
	{
		$content = $this->synthetic([self::svg('<rect id="glyph1" width="10" height="10"/>', ' viewBox="0 0 100 50" width="500" height="500"')])->draw(1, $this->resources);

		$this->assertStringStartsWith(self::START . "5 0 0 5 0 125 cm\n", $content);
	}

	/**
	 * @param object $object
	 * @param string $name   One of its private properties
	 *
	 * @return mixed The property's value
	 */
	private static function privately($object, $name)
	{
		$property = new \ReflectionProperty($object, $name);
		// Needed before PHP 8.1, and deprecated from 8.5
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		return $property->getValue($object);
	}

	/**
	 * @param array $shadings Shadings as RecordingResources keeps them, or part of them
	 *
	 * @return array They, each number to three places
	 */
	private function rounded(array $shadings)
	{
		array_walk_recursive($shadings, function (&$value) {
			$value = is_float($value) ? round($value, 3) : $value;
			$value = is_float($value) && $value == (int) $value ? (int) $value : $value;
		});

		return $shadings;
	}

	/**
	 * @param string $content    The root's content
	 * @param string $attributes More attributes for the root
	 *
	 * @return string An SVG document
	 */
	private static function svg($content, $attributes = '')
	{
		return '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"' . $attributes . '>' . $content . '</svg>';
	}

	/**
	 * @param string[] $documents Each document
	 * @param int      $first     The first glyph the first document's record covers
	 * @param int      $last      The last
	 * @param int      $cut       Bytes to leave off the end of the SVG table
	 *
	 * @return SvgSource Over a font of glyphs 0 to 2, glyph 2 the triangle, with an SVG table of those
	 *                   documents, the first covering $first to $last and each after it one glyph more
	 */
	private function synthetic(array $documents, $first = 1, $last = 1, $cut = 0)
	{
		$list = pack('n', count($documents));
		$data = '';
		$at = 2 + 12 * count($documents);
		foreach ($documents as $i => $document) {
			$list .= pack('n2N2', $first + $i, $last + $i, $at + strlen($data), strlen($document));
			$data .= $document;
		}
		$table = pack('nN2', 0, 10, 0) . $list . $data;

		$tables = $this->glyphTables(['', '', $this->triangle()]) + ['SVG ' => substr($table, 0, strlen($table) - $cut)];
		list($ttf, $reader) = $this->openFont($this->sfnt($tables));

		return new SvgSource(new ColorFontFile($ttf, $reader, 1000, $this->logger));
	}
}
