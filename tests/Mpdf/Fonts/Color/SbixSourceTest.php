<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\TestLogger;
use Mpdf\TTFontFile;

/**
 * SbixSource over TestEmoji-sbix, whose 64ppem strike holds a record of every graphic type: the man
 * and the grinning face are PNGs, the woman a 'dupe' of the man, the girl a JPEG, the regional
 * indicator U a 'flip' of the thumb, and the skin tone a 'tiff'. Its 32ppem strike is all PNGs.
 *
 * Each bitmap is 64 pixels square at 1000 units to the em, placed with its bottom left corner 6
 * pixels below the baseline: -93.75 units.
 *
 * What the fixture cannot show - chains of records, and records that run past the table or the file -
 * is shown with fonts of nothing but a maxp and an sbix table, built here.
 */
class SbixSourceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use SyntheticFonts;

	/**
	 * @var \Mpdf\Fonts\FileReader
	 */
	private $reader;

	/**
	 * @var SbixSource
	 */
	private $source;

	/**
	 * @var RecordingResources
	 */
	private $resources;

	/**
	 * @var TestLogger Handed to every source the test reads
	 */
	private $logger;

	/**
	 * Opens the sbix fixture and finds its largest strike
	 */
	protected function set_up()
	{
		parent::set_up();

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->reader = $ttf->openFont(__DIR__ . '/../../../data/ttf/color/TestEmoji-sbix.ttf');

		$this->logger = new TestLogger();
		$this->source = new SbixSource($ttf, $this->reader, 1000, $this->logger);
		$this->resources = new RecordingResources();
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
	 * A PNG is drawn from the 64ppem strike, not the 32ppem one, placed by its bottom left corner
	 */
	public function testAPngIsPlacedByItsBottomLeftCornerFromTheLargestStrike()
	{
		$this->assertSame('q 1000.000 0 0 1000.000 0.000 -93.750 cm /I1 Do Q', $this->source->draw(12, $this->resources));
		$this->assertSame("\x89PNG", substr($this->resources->images[0], 0, 4));
		$this->assertSame([64, 64], array_values(unpack('N2', substr($this->resources->images[0], 16, 8))));
	}

	/**
	 * A 'jpg ' record hands its JPEG over as it stands
	 */
	public function testAJpegIsDrawnAsOne()
	{
		$this->assertNotNull($this->source->draw(16, $this->resources));
		$this->assertSame("\xFF\xD8", substr($this->resources->images[0], 0, 2));
	}

	/**
	 * A 'dupe' record draws the glyph it names
	 */
	public function testADupeDrawsTheGlyphItNames()
	{
		$this->source->draw(14, $this->resources);
		$this->source->draw(15, $this->resources);

		$this->assertSame($this->resources->images[0], $this->resources->images[1], 'the woman is drawn with the man\'s image');
	}

	/**
	 * A mirrored image is drawn from its right edge back, so it covers the same box
	 */
	public function testAFlipDrawsTheGlyphItNamesMirrored()
	{
		$this->assertSame('q -1000.000 0 0 1000.000 1000.000 -93.750 cm /I1 Do Q', $this->source->draw(18, $this->resources));

		$this->source->draw(21, $this->resources);
		$this->assertSame($this->resources->images[1], $this->resources->images[0], 'with the thumb\'s image');
	}

	/**
	 * A 'flip' of a 'flip' is drawn the right way round, and a 'dupe' of a 'flip' mirrored, each over
	 * the box the image it ends at is placed in
	 */
	public function testAFlipOfAFlipIsTheRightWayRound()
	{
		$source = $this->synthetic($this->sbix([[64, [
			$this->record('flip', pack('n', 1)),
			$this->record('flip', pack('n', 2)),
			$this->record('png ', 'png', 2, -6),
			$this->record('dupe', pack('n', 1)),
		]]]));

		$this->assertSame('q 1000.000 0 0 1000.000 31.250 -93.750 cm /I1 Do Q', $source->draw(0, $this->resources));
		$this->assertSame('q -1000.000 0 0 1000.000 1031.250 -93.750 cm /I2 Do Q', $source->draw(3, $this->resources));
	}

	/**
	 * A 'tiff' record is not drawn, and says so
	 */
	public function testATiffIsNotDrawnAndSaysSo()
	{
		$this->assertNull($this->source->draw(20, $this->resources));
		$this->assertSame('Colour glyph 20 is an sbix "tiff" bitmap, which mPDF does not draw', $this->logger->records[0]['message']);
	}

	/**
	 * An image that cannot be drawn leaves the glyph drawing nothing, as a glyph with no record does
	 */
	public function testAGlyphWhoseImageIsRefusedDrawsNothing()
	{
		$this->resources->refuse = true;

		$this->assertNull($this->source->draw(12, $this->resources));
		$this->assertNull($this->source->draw(15, $this->resources), 'nor a dupe of it');
	}

	/**
	 * The space has an empty record, and a glyph past the font's last has none
	 */
	public function testAGlyphWithNoRecordIsNotDrawn()
	{
		$this->assertNull($this->source->draw(1, $this->resources), 'the space');
		$this->assertNull($this->source->draw(1000, $this->resources), 'a glyph the font does not have');
		$this->assertSame([], $this->logger->records);
	}

	/**
	 * A table that names no bitmap for a glyph, or names one past the end of the table or the file,
	 * draws nothing, registers no image and raises no warning
	 *
	 * @dataProvider tablesNamingNoBitmap
	 *
	 * @param string $sbix The table, of a font of one glyph
	 * @param int    $cut  Bytes to leave off the end of the file, which the table directory still counts
	 */
	public function testATableNamingNoBitmapDrawsNothing($sbix, $cut)
	{
		$this->assertNull($this->synthetic($sbix, 1, $cut)->draw(0, $this->resources));
		$this->assertSame([], $this->resources->images);
		$this->assertSame([], $this->logger->records);
	}

	/**
	 * @return array[] An sbix table and a cut that leave glyph 0 no bitmap
	 */
	public function tablesNamingNoBitmap()
	{
		$png = $this->record('png ', 'png');

		return [
			'no strikes' => [$this->sbix([]), 0],
			'a strike of 0ppem' => [$this->sbix([[0, [$png]]]), 0],
			'a strike count past the end of the table' => [pack('n2N', 1, 1, 1000000), 0],
			'a strike past the end of the file' => [pack('n2N2', 1, 1, 1, 0x7FFFFF00), 0],
			'an empty record' => [$this->sbix([[64, ['']]]), 0],
			'a record of nothing but its header' => [$this->sbix([[64, [$this->record('png ', '')]]]), 0],
			'a record running past the end of the table' => [pack('n2N2', 1, 1, 1, 12) . pack('n2N2', 64, 72, 12, 0x7FFFFF00), 0],
			'a record cut short by the end of the file' => [$this->sbix([[64, [$png]]]), 2],
			'offsets cut short by the end of the file' => [$this->sbix([[64, [$png]]]), 12],
			'a dupe shorter than a glyph id' => [$this->sbix([[64, [$this->record('dupe', "\0")]]]), 0],
			'a dupe of itself' => [$this->sbix([[64, [$this->record('dupe', pack('n', 0))]]]), 0],
			'a dupe of a glyph past the font\'s last' => [$this->sbix([[64, [$this->record('dupe', pack('n', 1)), $png]]]), 0],
		];
	}

	/**
	 * @param string $type   The graphic type
	 * @param string $data   What follows it
	 * @param int    $x      originOffsetX, in pixels
	 * @param int    $y      originOffsetY, in pixels
	 *
	 * @return string A glyph's record
	 */
	private function record($type, $data, $x = 0, $y = 0)
	{
		return pack('n2', $x & 0xFFFF, $y & 0xFFFF) . $type . $data;
	}

	/**
	 * An sbix table: the header, then each strike, each its ppem, a resolution of 72, an offset per
	 * glyph and one past the last, then the records
	 *
	 * @param array[] $strikes Each a ppem and a record per glyph, '' for a glyph it has no bitmap for
	 *
	 * @return string
	 */
	private function sbix(array $strikes)
	{
		$offsets = '';
		$bodies = '';
		$start = 8 + 4 * count($strikes);

		foreach ($strikes as $strike) {
			list($ppem, $records) = $strike;
			$glyphOffsets = '';
			$data = '';
			$header = 4 + 4 * (count($records) + 1);
			foreach ($records as $record) {
				$glyphOffsets .= pack('N', $header + strlen($data));
				$data .= $record;
			}
			$glyphOffsets .= pack('N', $header + strlen($data));

			$offsets .= pack('N', $start + strlen($bodies));
			$bodies .= pack('n2', $ppem, 72) . $glyphOffsets . $data;
		}

		return pack('n2N', 1, 1, count($strikes)) . $offsets . $bodies;
	}

	/**
	 * Writes a font of a maxp and an sbix table and finds its largest strike
	 *
	 * @param string $sbix      The sbix table
	 * @param int    $numGlyphs How many glyphs maxp says the font has
	 * @param int    $cut       Bytes to leave off the end of the file, which the table directory still
	 *                          counts
	 *
	 * @return SbixSource
	 */
	private function synthetic($sbix, $numGlyphs = 4, $cut = 0)
	{
		list($ttf, $reader) = $this->openFont($this->sfnt(['maxp' => pack('Nn', 0x00005000, $numGlyphs), 'sbix' => $sbix], $cut));

		return new SbixSource($ttf, $reader, 1000, $this->logger);
	}
}
