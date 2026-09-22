<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\TTFontFile;
use Psr\Log\NullLogger;

/**
 * ColrV0Source over TestEmoji-COLRv0, drawn in its first palette: the grinning face is a yellow face,
 * black eyes and a mouth in the colour of the text; the heart is red with a highlight of half-opaque
 * white.
 *
 * What the fixture cannot show - COLR and CPAL tables that run short or name what they do not hold - is
 * shown with fonts built here: glyph 1 is a colour glyph of one layer, glyph 2, a triangle.
 */
class ColrV0SourceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use SyntheticFonts;

	/**
	 * @var \Mpdf\Fonts\FileReader
	 */
	private $reader;

	/**
	 * @var ColrV0Source
	 */
	private $source;

	/**
	 * Opens the COLRv0 fixture
	 */
	protected function set_up()
	{
		parent::set_up();

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->reader = $ttf->openFont(__DIR__ . '/../../../data/ttf/color/TestEmoji-COLRv0.ttf');
		$this->source = new ColrV0Source(new ColorFontFile($ttf, $this->reader, 1000, new NullLogger()));
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
	 * A coloured layer sets its colour inside q and Q, so the layer after it is drawn in whatever
	 * colour the text is
	 */
	public function testEachLayerIsFilledInItsColourAndAForegroundLayerInTheTexts()
	{
		$layers = preg_split('/(?<=f\n)/', $this->source->draw(12, new RecordingResources()), -1, PREG_SPLIT_NO_EMPTY);

		$this->assertCount(3, $layers);
		$this->assertStringStartsWith("q 1.000 0.800 0.200 rg\n950 350 m\n", $layers[0], 'the face, yellow');
		$this->assertStringStartsWith("Q\nq 0.000 0.000 0.000 rg\n", $layers[1], 'the eyes, black');
		$this->assertStringStartsWith("Q\n300 250 m\n", $layers[2], 'the mouth, which sets no colour');
	}

	/**
	 * The heart's highlight is white at 0x80
	 */
	public function testALayerLessThanOpaqueIsFilledThroughAGraphicsState()
	{
		$content = $this->source->draw(13, new RecordingResources());

		$this->assertStringContainsString("q /GS0.50 gs 1.000 1.000 1.000 rg\n", $content, 'the highlight, white at 0x80');
	}

	/**
	 * A glyph COLR has no record of is left to the next source, its outline
	 */
	public function testAGlyphWithNoLayersIsNotDrawn()
	{
		$this->assertNull($this->source->draw(2, new RecordingResources()), 'the number sign');
	}

	/**
	 * A layer is filled in the colour its palette index names
	 */
	public function testASyntheticLayerIsFilledInItsColour()
	{
		$this->assertSame("q 1.000 0.000 0.000 rg\n0 0 m\n500 700 l\n1000 0 l\nh\nf\nQ\n", $this->synthetic($this->colr(), $this->cpal())->draw(1, new RecordingResources()));
	}

	/**
	 * A layer naming a colour the palette does not hold is filled in the colour of the text, as one
	 * naming 0xFFFF is
	 *
	 * @dataProvider palettesWithoutTheColour
	 *
	 * @param string $colr The COLR table
	 * @param string $cpal The CPAL table
	 */
	public function testALayerWhoseColourIsNotThereIsInTheColourOfTheText($colr, $cpal)
	{
		$this->assertSame("0 0 m\n500 700 l\n1000 0 l\nh\nf\n", $this->synthetic($colr, $cpal)->draw(1, new RecordingResources()));
	}

	/**
	 * @return array[] COLR and CPAL tables that leave glyph 1's layer no colour
	 */
	public function palettesWithoutTheColour()
	{
		return [
			'the foreground' => [$this->colr(0xFFFF), $this->cpal()],
			'an index past the palette' => [$this->colr(1), $this->cpal()],
			'no palettes' => [$this->colr(), pack('n4N', 0, 1, 0, 1, 12) . "\0\0\xFF\xFF"],
			'a first palette past the colour records' => [$this->colr(), pack('n4Nn', 0, 1, 1, 1, 14, 1) . "\0\0\xFF\xFF"],
			'colour records past the end of the file' => [$this->colr(), pack('n4Nn', 0, 1, 1, 1, 0x7FFFFF00, 0)],
			'a CPAL cut short' => [$this->colr(), pack('n2', 0, 1)],
		];
	}

	/**
	 * A COLR table that runs short, or a colour glyph whose layers run past the ones it counts, draws
	 * nothing
	 *
	 * @dataProvider tablesNamingNoLayers
	 *
	 * @param string $colr The COLR table
	 */
	public function testATableNamingNoLayersDrawsNothing($colr)
	{
		$this->assertNull($this->synthetic($colr, $this->cpal())->draw(1, new RecordingResources()));
	}

	/**
	 * @return array[] COLR tables that give glyph 1 no layers to draw
	 */
	public function tablesNamingNoLayers()
	{
		return [
			'a COLR cut short' => [pack('n2', 0, 1)],
			'base records past the end of the file' => [pack('n2N2n', 0, 50000, 14, 14, 1)],
			'layers past the ones it counts' => [pack('n2N2n', 0, 1, 14, 20, 1) . pack('n3', 1, 0, 2) . pack('n2', 2, 0)],
			'layer records past the end of the file' => [pack('n2N2n', 0, 1, 14, 0x7FFFFF00, 1) . pack('n3', 1, 0, 1)],
		];
	}

	/**
	 * @param int $index The palette index glyph 1's one layer, glyph 2, is filled with
	 *
	 * @return string A COLR table of version 0
	 */
	private function colr($index = 0)
	{
		// version, numBaseGlyphRecords, baseGlyphRecordsOffset, layerRecordsOffset, numLayerRecords
		return pack('n2N2n', 0, 1, 14, 20, 1) . pack('n3', 1, 0, 1) . pack('n2', 2, $index);
	}

	/**
	 * @param string $colr The COLR table
	 * @param string $cpal The CPAL table
	 *
	 * @return ColrV0Source Over a font of glyphs 0 to 2, glyph 2 the triangle, and those tables
	 */
	private function synthetic($colr, $cpal)
	{
		$tables = $this->glyphTables(['', '', $this->triangle()]) + ['COLR' => $colr, 'CPAL' => $cpal];
		list($ttf, $reader) = $this->openFont($this->sfnt($tables));

		return new ColrV0Source(new ColorFontFile($ttf, $reader, 1000, new NullLogger()));
	}
}
