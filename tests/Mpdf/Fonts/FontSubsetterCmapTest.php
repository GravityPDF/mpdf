<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * The format 4 cmap subtable each of the three builders writes declares the length it occupies.
 *
 * The subset golden masters pin the bytes, so they would catch this field moving, but they cannot say
 * whether it is right: a wrong value is as stable as a right one. #140 was a field short by the whole
 * of glyphIdArray, which no PDF reader ever opens - a subsetted font is embedded as Type0/Identity-H
 * and glyphs are resolved through the CIDToGIDMap beside it - and which every font tool refuses.
 */
class FontSubsetterCmapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Where the parser caches what it reads. Mpdf\Cache creates it.
	 *
	 * @var string
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-cmap';

	/**
	 * @dataProvider fontProvider
	 */
	public function testMakeSubsetDeclaresTheLengthItWrote($font)
	{
		$this->assertDeclaredLength($this->subsetter()->makeSubset($this->file($font), $this->pangram($font), 0, false, false));
	}

	/**
	 * @dataProvider fontProvider
	 */
	public function testMakeSubsetSipDeclaresTheLengthItWrote($font)
	{
		$this->assertDeclaredLength($this->subsetter()->makeSubsetSIP($this->file($font), $this->pangram($font), 0, false, 0));
	}

	/**
	 * repackageTTF rewrites the whole font rather than a subset, so it takes no character list. It
	 * only builds a cmap of its own under useOTL; without it the original table is copied over.
	 *
	 * @dataProvider fontProvider
	 */
	public function testRepackageTtfDeclaresTheLengthItWrote($font)
	{
		$this->assertDeclaredLength($this->subsetter()->repackageTTF($this->file($font), 0, false, true));
	}

	/**
	 * The whole corpus, as the golden masters take it, so that a font added to tests/data/ttf is
	 * measured here too.
	 */
	public function fontProvider()
	{
		$master = new SubsetGoldenMaster();

		return $master->fonts();
	}

	private function assertDeclaredLength($program)
	{
		$cmap = $this->table($program, 'cmap');
		$this->assertNotNull($cmap, 'The font program carries no cmap table');

		$subtable = $this->format4Subtable($cmap);
		$this->assertNotNull($subtable, 'The cmap carries no format 4 subtable');

		list($offset, $end) = $subtable;

		if ($end - $offset > 0xFFFF) {
			$this->markTestSkipped(sprintf('#150: the subtable is %d bytes, more than its uint16 length can state', $end - $offset));
		}

		$reader = new BlobReader($cmap);
		$reader->seek($offset + 2);

		$this->assertSame($end - $offset, $reader->readUInt16());
	}

	/**
	 * @return array|null The format 4 subtable's start and end within the cmap, or null if there is none
	 */
	private function format4Subtable($cmap)
	{
		$reader = new BlobReader($cmap);
		$reader->skip(2); // version
		$subtableCount = $reader->readUInt16();

		$offsets = [];
		for ($i = 0; $i < $subtableCount; $i++) {
			$reader->skip(4); // platform, encoding
			$offsets[] = FontReader::uint32($reader->read(4));
		}

		$start = null;
		foreach ($offsets as $offset) {
			$reader->seek($offset);
			if ($reader->readUInt16() === 4) {
				$start = $offset;
				break;
			}
		}

		if ($start === null) {
			return null;
		}

		// The builders point three encoding records at the one subtable, so the end is the nearest
		// offset past it rather than the next one listed
		$end = strlen($cmap);
		foreach ($offsets as $offset) {
			if ($offset > $start && $offset < $end) {
				$end = $offset;
			}
		}

		return [$start, $end];
	}

	/**
	 * @return string|null The named table's bytes, taken from the emitted font's own table directory
	 */
	private function table($program, $tag)
	{
		$reader = new BlobReader($program);
		$reader->skip(4); // sfntVersion
		$tableCount = $reader->readUInt16();
		$reader->skip(6); // searchRange, entrySelector, rangeShift

		for ($i = 0; $i < $tableCount; $i++) {
			$wanted = $reader->read(4) === $tag;
			$reader->skip(4); // checksum
			$offset = FontReader::uint32($reader->read(4));
			$length = FontReader::uint32($reader->read(4));

			if ($wanted) {
				return substr($program, $offset, $length);
			}
		}

		return null;
	}

	/**
	 * A space, the digits and both cases of the alphabet - four contiguous runs, so the subtable
	 * segments several ways rather than collapsing to one.
	 *
	 * @return int[] Those of them the font maps, as Unicode code points
	 */
	private function pangram($font)
	{
		$characters = array_merge([0x20], range(0x30, 0x39), range(0x41, 0x5A), range(0x61, 0x7A));

		return array_values(array_intersect($characters, array_keys($this->parser()->getCTG($this->file($font), 0, false, 0))));
	}

	private function file($font)
	{
		return GoldenMaster::FONT_DIR . '/' . $font . '.ttf';
	}

	private function subsetter()
	{
		return new FontSubsetter($this->parser());
	}

	private function parser()
	{
		return new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win');
	}
}
