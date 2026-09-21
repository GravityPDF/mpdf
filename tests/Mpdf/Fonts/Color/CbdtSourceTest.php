<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\TTFontFile;

/**
 * CbdtSource over TestEmoji-CBDT, whose 64ppem strike holds one run of emoji in each of the five
 * CBLC index formats, and whose 32ppem strike holds them all again in the first.
 *
 * The bitmaps are 64 pixels square at 1000 units to the em, drawn from 100 units below the baseline,
 * so each is placed 1000 units square with its bottom edge at -93.75: its top is 58 pixels up, the
 * nearest pixel to 900 units.
 *
 * What the fixture cannot show - sparse glyph ids, and indexes that name no bitmap or name one past
 * the end of the file - is shown with fonts of nothing but a CBLC and a CBDT table, built here.
 */
class CbdtSourceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Fonts\FileReader
	 */
	private $reader;

	/**
	 * @var CbdtSource
	 */
	private $source;

	/**
	 * @var RecordingResources
	 */
	private $resources;

	/**
	 * @var \Mpdf\Fonts\FileReader[] The fonts source() opened
	 */
	private $opened = [];

	/**
	 * @var string[] The fonts source() wrote
	 */
	private $written = [];

	/**
	 * Opens the CBDT fixture and reads its largest strike's index
	 */
	protected function set_up()
	{
		parent::set_up();

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->reader = $ttf->openFont(__DIR__ . '/../../../data/ttf/color/TestEmoji-CBDT.ttf');

		$this->source = new CbdtSource($ttf, $this->reader, 1000);
		$this->resources = new RecordingResources();
	}

	/**
	 * Closes the font file set_up() opened, and removes the fonts the test built
	 */
	protected function tear_down()
	{
		$this->reader->close();

		foreach ($this->opened as $reader) {
			$reader->close();
		}
		foreach ($this->written as $file) {
			unlink($file);
		}

		parent::tear_down();
	}

	/**
	 * A bitmap whose image cannot be drawn leaves the glyph drawing nothing, as a glyph with no bitmap
	 * does
	 */
	public function testAGlyphWhoseImageIsRefusedDrawsNothing()
	{
		$this->resources->refuse = true;

		$this->assertNull($this->source->draw(12, $this->resources));
	}

	/**
	 * Each index subtable layout leads to the glyph's PNG in the largest strike, placed at its bearings
	 *
	 * @dataProvider glyphs
	 *
	 * @param int $glyph A glyph the fixture files under that layout
	 */
	public function testEachIndexFormatFindsItsGlyphsBitmap($glyph)
	{
		$this->assertSame('q 1000.000 0 0 1000.000 0.000 -93.750 cm /I1 Do Q', $this->source->draw($glyph, $this->resources));

		$this->assertCount(1, $this->resources->images);
		$this->assertSame("\x89PNG", substr($this->resources->images[0], 0, 4));
		$this->assertSame([64, 64], array_values(unpack('N2', substr($this->resources->images[0], 16, 8))), 'the largest strike');
	}

	/**
	 * @return array[] A glyph from each of the fixture's index subtables, by its index and image format
	 */
	public function glyphs()
	{
		return [
			'index format 1, image format 17' => [12],
			'index format 3, image format 18' => [15],
			'index format 2, image format 19' => [17],
			'index format 4, image format 17' => [20],
			'index format 5, image format 19' => [26],
		];
	}

	/**
	 * A constant-size subtable pads each image to the longest, and the PNG's own length is what is read
	 */
	public function testAPaddedImageIsReadToItsOwnLength()
	{
		$this->source->draw(22, $this->resources);

		$this->assertSame('IEND', substr($this->resources->images[0], -8, 4));
	}

	/**
	 * A glyph no subtable covers draws nothing and registers no image
	 */
	public function testAGlyphWithNoBitmapIsNotDrawn()
	{
		$this->assertNull($this->source->draw(1, $this->resources), 'the space');
		$this->assertSame([], $this->resources->images);
	}

	/**
	 * The fixture lists its 64ppem strike first; listed after the 32ppem one, it is still the strike
	 * drawn
	 *
	 * @dataProvider glyphs
	 *
	 * @param int $glyph A glyph the fixture files under that layout
	 */
	public function testTheLargestStrikeIsDrawnWhereverItIsListed($glyph)
	{
		$font = file_get_contents(__DIR__ . '/../../../data/ttf/color/TestEmoji-CBDT.ttf');
		$cblc = $this->tableOffset($font, 'CBLC');

		// The two BitmapSize records swapped, each still pointing at its own subtables
		$font = substr_replace($font, substr($font, $cblc + 56, 48) . substr($font, $cblc + 8, 48), $cblc + 8, 96);

		$this->assertSame('q 1000.000 0 0 1000.000 0.000 -93.750 cm /I1 Do Q', $this->source(['font' => $font])->draw($glyph, $this->resources));
		$this->assertSame([64, 64], array_values(unpack('N2', substr($this->resources->images[0], 16, 8))));
	}

	/**
	 * Index formats 4 and 5 list the glyphs they hold, which need not be every glyph from first to
	 * last: each glyph is found by its id, not its distance from the first, whichever is drawn first
	 *
	 * @dataProvider sparseSubtables
	 *
	 * @param array  $subtable The subtable, listing glyphs 10, 25 and 40
	 * @param string $data     Their glyph data
	 */
	public function testASparseSubtableFindsEachGlyphByItsId(array $subtable, $data)
	{
		$source = $this->synthetic([[64, [$subtable]]], $data);

		foreach ([25, 10, 40] as $glyph) {
			$this->assertSame(
				sprintf('q 1000.000 0 0 1000.000 0.000 -93.750 cm /I%d Do Q', count($this->resources->images) + 1),
				$source->draw($glyph, $this->resources)
			);
		}

		$this->assertSame(['twenty-five', 'ten', 'forty'], $this->resources->images);
	}

	/**
	 * @return array[] Glyphs 10, 25 and 40 in a subtable of index format 4 and one of index format 5
	 */
	public function sparseSubtables()
	{
		$records = [$this->smallGlyph('ten'), $this->smallGlyph('twenty-five'), $this->smallGlyph('forty')];
		$ends = [strlen($records[0]), strlen($records[0] . $records[1]), strlen(implode('', $records))];

		return [
			'index format 4, image format 17' => [
				[10, 40, 4, 17, pack('N', 3) . pack('n*', 10, 0, 25, $ends[0], 40, $ends[1], 0, $ends[2])],
				implode('', $records),
			],
			'index format 5, image format 19' => [
				[10, 40, 5, 19, pack('N', 16) . $this->bigMetrics() . pack('N', 3) . pack('n*', 10, 25, 40)],
				str_pad(pack('N', 3) . 'ten', 16, "\0") . str_pad(pack('N', 11) . 'twenty-five', 16, "\0") . str_pad(pack('N', 5) . 'forty', 16, "\0"),
			],
		];
	}

	/**
	 * An index that names no bitmap for a glyph, or names one past the end of the file, draws nothing,
	 * registers no image and raises no warning
	 *
	 * @dataProvider indexesNamingNoBitmap
	 *
	 * @param int     $glyph   The glyph drawn
	 * @param array[] $strikes The font's strikes, as cblc() takes them
	 * @param string  $data    Its glyph data
	 */
	public function testAnIndexNamingNoBitmapDrawsNothing($glyph, array $strikes, $data)
	{
		$this->assertNull($this->synthetic($strikes, $data)->draw($glyph, $this->resources));
		$this->assertSame([], $this->resources->images);
	}

	/**
	 * @return array[] A glyph, and strikes and glyph data that name no bitmap for it
	 */
	public function indexesNamingNoBitmap()
	{
		$png = $this->smallGlyph('png');
		$offsets = pack('N2', 0, strlen($png));
		$sparse = pack('N', 2) . pack('n*', 10, 0, 40, strlen($png), 0, 2 * strlen($png));

		return [
			'no strikes' => [5, [], $png],
			'a strike of 0ppem' => [5, [[0, [[5, 5, 1, 17, $offsets]]]], $png],
			'an index format CBLC does not define' => [5, [[64, [[5, 5, 6, 17, $offsets]]]], $png],
			'image format 1, which is not PNG' => [5, [[64, [[5, 5, 1, 1, $offsets]]]], $png],
			'image format 20, which CBDT does not define' => [5, [[64, [[5, 5, 1, 20, $offsets]]]], $png],
			'image format 19 under index format 1, which has no metrics to give it' => [5, [[64, [[5, 5, 1, 19, $offsets]]]], $png],
			'a dataLength of 0' => [5, [[64, [[5, 5, 1, 17, pack('N2', 0, 9)]]]], $this->smallGlyph('')],
			'equal offsets in index format 1' => [6, [[64, [[5, 7, 1, 17, pack('N4', 0, strlen($png), strlen($png), 2 * strlen($png))]]]], $png . $png],
			'equal offsets in index format 3' => [6, [[64, [[5, 7, 3, 17, pack('n4', 0, strlen($png), strlen($png), 2 * strlen($png))]]]], $png . $png],
			'a glyph format 4 covers but does not list' => [25, [[64, [[10, 40, 4, 17, $sparse]]]], $png . $png],
			'a glyph format 5 covers but does not list' => [25, [[64, [[10, 40, 5, 19, pack('N', 8) . $this->bigMetrics() . pack('N', 2) . pack('n*', 10, 40)]]]], pack('N2', 4, 0) . pack('N2', 4, 0)],
			'a format 4 list of more glyphs than it covers' => [10, [[64, [[10, 10, 4, 17, $sparse]]]], $png . $png],
			'a glyph offset past the end of the file' => [5, [[64, [[5, 5, 1, 17, pack('N2', 0x7FFFFF00, 0x7FFFFF20)]]]], $png],
			'a dataLength past the end of CBDT' => [5, [[64, [[5, 5, 1, 17, pack('N2', 0, 12)]]]], pack('CCccCN', 64, 64, 0, 58, 64, 1000) . 'png'],
			'a subtable past the end of the file' => [5, [[64, [[5, 5, 1, 17, $offsets, 0x7FFFFF00]]]], $png],
			'format 1 offsets running past the end of the file' => [5, [[64, [[5, 5, 1, 17, pack('N', 0)]]]], $png],
			'a format 2 header running past the end of the file' => [5, [[64, [[5, 5, 2, 19, pack('N', 16)]]]], $png],
			'a format 4 list running past the end of the file' => [10, [[64, [[10, 40, 4, 17, pack('N', 2) . pack('n*', 10, 0)]]]], $png],
			'a format 5 list running past the end of the file' => [10, [[64, [[10, 40, 5, 19, pack('N', 8) . $this->bigMetrics() . pack('N', 2) . pack('n', 10)]]]], $png],
		];
	}

	/**
	 * A file cut short partway through an image draws nothing, though the table directory says the
	 * image is all there
	 */
	public function testAnImageCutShortByTheEndOfTheFileIsNotDrawn()
	{
		$png = $this->smallGlyph('png');
		$cblc = $this->cblc([[64, [[5, 5, 1, 17, pack('N2', 0, strlen($png))]]]]);

		$this->assertNull($this->source(['CBLC' => $cblc, 'CBDT' => "\0\3\0\0" . $png], 2)->draw(5, $this->resources));
		$this->assertSame([], $this->resources->images);
	}

	/**
	 * @param string $png The image
	 *
	 * @return string A glyph of image format 17: 64 pixels square, 58 of them above the baseline
	 */
	private function smallGlyph($png)
	{
		return pack('CCccCN', 64, 64, 0, 58, 64, strlen($png)) . $png;
	}

	/**
	 * @return string BigGlyphMetrics for a glyph 64 pixels square, 58 of them above the baseline
	 */
	private function bigMetrics()
	{
		return pack('CCccCccC', 64, 64, 0, 58, 64, -32, 0, 64);
	}

	/**
	 * A font of a CBDT table and then a CBLC table, and nothing else, drawn from
	 *
	 * @param array[] $strikes As cblc() takes them
	 * @param string  $data    The glyph data, which every subtable's offsets are measured from
	 *
	 * @return CbdtSource
	 */
	private function synthetic(array $strikes, $data)
	{
		return $this->source(['CBDT' => "\0\3\0\0" . $data, 'CBLC' => $this->cblc($strikes)]);
	}

	/**
	 * A CBLC table: the header, a BitmapSize record per strike, then each strike's IndexSubTableArray
	 * followed by its subtables
	 *
	 * @param array[] $strikes Each a ppem and its subtables, each of those its first glyph, last glyph,
	 *                         index format and image format, the subtable after its header, and
	 *                         optionally an offset from the array to put in place of where the
	 *                         subtable is. Every subtable's glyph data starts just past CBDT's header.
	 *
	 * @return string
	 */
	private function cblc(array $strikes)
	{
		$records = '';
		$arrays = '';
		$start = 8 + 48 * count($strikes);

		foreach ($strikes as $strike) {
			list($ppem, $subtables) = $strike;
			$array = '';
			$bodies = '';
			foreach ($subtables as $subtable) {
				$offset = isset($subtable[5]) ? $subtable[5] : 8 * count($subtables) + strlen($bodies);
				$array .= pack('nnN', $subtable[0], $subtable[1], $offset);
				$bodies .= pack('nnN', $subtable[2], $subtable[3], 4) . $subtable[4];
			}

			// indexSubTableArrayOffset, indexTablesSize, numberOfIndexSubTables, colorRef, both line
			// metrics, the first and last glyph, ppemX, ppemY, bitDepth and flags
			$records .= pack('N4', $start + strlen($arrays), strlen($array . $bodies), count($subtables), 0)
				. str_repeat("\0", 24) . pack('n2C4', 0, 0, $ppem, $ppem, 32, 1);
			$arrays .= $array . $bodies;
		}

		return pack('n2N', 3, 0, count($strikes)) . $records . $arrays;
	}

	/**
	 * Writes a font and reads its bitmap index
	 *
	 * @param string[] $tables Tag => the table, in the order they are written; or 'font' => a whole font
	 * @param int      $cut    Bytes to leave off the end of the file, which the table directory still
	 *                         counts
	 *
	 * @return CbdtSource
	 */
	private function source(array $tables, $cut = 0)
	{
		if (isset($tables['font'])) {
			$font = $tables['font'];
		} else {
			$directory = '';
			$body = '';
			$offset = 12 + 16 * count($tables);
			foreach ($tables as $tag => $table) {
				$directory .= $tag . pack('N3', 0, $offset + strlen($body), strlen($table));
				$body .= $table;
			}
			$font = pack('Nn4', 0x00010000, count($tables), 0, 0, 0) . $directory . $body;
			$font = substr($font, 0, strlen($font) - $cut);
		}

		$file = tempnam(sys_get_temp_dir(), 'mpdf-cbdt-');
		file_put_contents($file, $font);
		$this->written[] = $file;

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../../tmp/mpdf/ttfontdata')), 'win');
		$this->opened[] = $reader = $ttf->openFont($file);

		return new CbdtSource($ttf, $reader, 1000);
	}

	/**
	 * @param string $font The font's bytes
	 * @param string $tag  One of its tables
	 *
	 * @return int Where the table starts
	 */
	private function tableOffset($font, $tag)
	{
		$tables = unpack('n', substr($font, 4, 2));
		for ($i = 0; $i < $tables[1]; $i++) {
			if (substr($font, 12 + 16 * $i, 4) === $tag) {
				$offset = unpack('N', substr($font, 12 + 16 * $i + 8, 4));

				return $offset[1];
			}
		}

		$this->fail('No ' . $tag);
	}
}
