<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TestLogger;
use Mpdf\TTFontFile;

/**
 * GlyphOutline against the paths fontTools draws for the same glyphs - see
 * tests/data/glyphoutline/build.py. Noto Sans gives real outlines: curves with runs of off-curve
 * points, several contours, a composite of two glyphs and a composite of three. The colour fixture
 * adds a contour of off-curve points alone and a scaled, moved component.
 */
class GlyphOutlineTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use Color\SyntheticFonts;

	/**
	 * Removes the fonts the test built
	 */
	protected function tear_down()
	{
		$this->closeFonts();

		parent::tear_down();
	}

	/**
	 * @dataProvider glyphs
	 *
	 * @param string $file     A font under tests/data
	 * @param int    $glyph    The glyph id
	 * @param string $expected The path fontTools draws for it
	 */
	public function testAGlyphIsDrawnAsFontToolsDrawsIt($file, $glyph, $expected)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$reader = $ttf->openFont(__DIR__ . '/../../data/' . $file);

		try {
			$path = (new GlyphOutline($ttf, $reader, new TestLogger()))->path($glyph);
		} finally {
			$reader->close();
		}

		$actual = preg_split('/\s+/', trim($path));
		$expected = preg_split('/\s+/', trim($expected));
		$this->assertCount(count($expected), $actual, $path);

		// The cubic's control points are worked out in a different order from fontTools', so the
		// third place can round the other way
		foreach ($expected as $i => $token) {
			if (is_numeric($token)) {
				$this->assertEqualsWithDelta((float) $token, (float) $actual[$i], 0.0015, sprintf('token %d of %s', $i, $path));
			} else {
				$this->assertSame($token, $actual[$i], sprintf('token %d of %s', $i, $path));
			}
		}
	}

	/**
	 * @return array[] Each font, glyph and path in tests/data/glyphoutline/paths.json
	 */
	public function glyphs()
	{
		$cases = [];
		foreach (json_decode(file_get_contents(__DIR__ . '/../../data/glyphoutline/paths.json'), true) as $name => $case) {
			list($file) = explode(' ', $name);
			$cases[$name] = [$file, $case['glyph'], $case['path']];
		}

		return $cases;
	}

	/**
	 * The triangle the malformed glyphs below are cut from draws as itself, and so does a composite
	 * moving it
	 */
	public function testASyntheticGlyphIsDrawn()
	{
		$composite = pack('n5', 0xFFFF, 0, 0, 1000, 700) . pack('n2', GlyphOperator::WORDS | GlyphOperator::XY_VALUES, 0) . pack('n2', 10, 20);

		$outline = $this->outline([$this->triangle(), $composite]);

		$this->assertSame("0 0 m\n500 700 l\n1000 0 l\nh\n", $outline->path(0));
		$this->assertSame("10 20 m\n510 720 l\n1010 20 l\nh\n", $outline->path(1));
	}

	/**
	 * A composite scaled by F2DOT14's nearest to a third (0x1555) draws its fractional points to three
	 * decimal places
	 */
	public function testAScaledCompositeIsDrawnToThreePlaces()
	{
		$composite = pack('n5', 0xFFFF, 0, 0, 1000, 700) . pack('n2', GlyphOperator::WORDS | GlyphOperator::XY_VALUES | GlyphOperator::SCALE, 0) . pack('n3', 0, 0, 0x1555);

		$this->assertSame("0 0 m\n166.656 233.319 l\n333.313 0 l\nh\n", $this->outline([$this->triangle(), $composite])->path(1));
	}

	/**
	 * A glyph that runs short of what it says it holds draws nothing and warns of nothing, rather than
	 * reading past its end
	 *
	 * @dataProvider malformed
	 *
	 * @param string $glyph The glyph's glyf data
	 */
	public function testAGlyphThatRunsShortDrawsNothing($glyph)
	{
		$this->assertSame('', $this->outline([$glyph])->path(0));
	}

	/**
	 * @return array[] Glyphs that run short of what they say they hold
	 */
	public function malformed()
	{
		$triangle = $this->triangle();
		$header = pack('n5', 1, 0, 0, 1000, 700);

		return [
			'shorter than its header' => [substr($triangle, 0, 6)],
			'more contours than it holds' => [pack('n5', 200, 0, 0, 1000, 700) . pack('n2', 2, 0)],
			'instructions longer than the glyph' => [$header . pack('n2', 2, 500) . "\1\1\1"],
			'fewer flags than points' => [$header . pack('n2', 2, 0) . "\1"],
			'a flag repeated past the end' => [$header . pack('n2', 2, 0) . "\x09"],
			'coordinates cut short' => [substr($triangle, 0, -3)],
			'contours ending before the last' => [pack('n5', 2, 0, 0, 1000, 700) . pack('n3', 2, 1, 0) . "\1\1\1" . pack('n6', 0, 500, 500, 0, 700, 0x10000 - 700)],
			'a component cut short' => [pack('n5', 0xFFFF, 0, 0, 1000, 700) . pack('n2', GlyphOperator::WORDS | GlyphOperator::XY_VALUES, 0) . pack('n', 10)],
			'a component of itself' => [pack('n5', 0xFFFF, 0, 0, 1000, 700) . pack('n2', GlyphOperator::XY_VALUES, 0) . "\0\0"],
		];
	}

	/**
	 * @param string[] $glyphs Each glyph's glyf data, by glyph id
	 *
	 * @return GlyphOutline Over a font of those glyphs
	 */
	private function outline(array $glyphs)
	{
		list($ttf, $reader) = $this->openFont($this->sfnt($this->glyphTables($glyphs)));

		return new GlyphOutline($ttf, $reader, new TestLogger());
	}
}
