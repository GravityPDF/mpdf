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
 * #150 was that same array standing a repackaged subtable past anything the field can hold.
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
	 * repackageTTF states an idRangeOffset of 0 for every segment, which resolves its glyphs through
	 * idDelta, so the glyphIdArray that followed was bytes no reader could reach.
	 *
	 * @dataProvider fontProvider
	 */
	public function testRepackageTtfWritesNoGlyphIdArray($font)
	{
		$cmap = $this->table($this->subsetter()->repackageTTF($this->file($font), 0, false, true), 'cmap');
		list($offset, $end, $segCount) = $this->format4Subtable($cmap);

		// format, length, language, segCountX2, searchRange, entrySelector, rangeShift and reservedPad,
		// then endCode, startCode, idDelta and idRangeOffset once per segment
		$this->assertSame(16 + 8 * $segCount, $end - $offset);
	}

	/**
	 * The field runs out at 8,189 segments and the widest font in reach repackages into 3,378, so the
	 * only way to stand a subtable past it is to build one.
	 */
	public function testRepackageTtfRefusesASubtableLongerThanItsLengthFieldCanState()
	{
		$this->expectException(\Mpdf\Exception\FontException::class);
		$this->expectExceptionMessage('repackages into a format 4 cmap subtable of 80032 bytes, more than its length field can state');

		$this->subsetter()->repackageTTF($this->fontOfOneSegmentPerCharacter(10000), 0, false, true);
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

		$reader = new BlobReader($cmap);
		$reader->seek($offset + 2);

		$this->assertSame($end - $offset, $reader->readUInt16());
	}

	/**
	 * @return array|null The format 4 subtable's start and end within the cmap and its segment count,
	 *                    or null if there is none
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

		$reader->seek($start + 6);
		$segCount = $reader->readUInt16() / 2;

		// The builders point three encoding records at the one subtable, so the end is the nearest
		// offset past it rather than the next one listed
		$end = strlen($cmap);
		foreach ($offsets as $offset) {
			if ($offset > $start && $offset < $end) {
				$end = $offset;
			}
		}

		return [$start, $end, $segCount];
	}

	/**
	 * @return string|null The named table's bytes, taken from the emitted font's own table directory
	 */
	private function table($program, $tag)
	{
		$tables = $this->tables($program);

		return isset($tables[$tag]) ? $tables[$tag] : null;
	}

	/**
	 * @return string[] Every table the font carries, by four-character tag
	 */
	private function tables($program)
	{
		$reader = new BlobReader($program);
		$reader->skip(4); // sfntVersion
		$tableCount = $reader->readUInt16();
		$reader->skip(6); // searchRange, entrySelector, rangeShift

		$tables = [];
		for ($i = 0; $i < $tableCount; $i++) {
			$tag = $reader->read(4);
			$reader->skip(4); // checksum
			$offset = FontReader::uint32($reader->read(4));
			$length = FontReader::uint32($reader->read(4));

			$tables[$tag] = substr($program, $offset, $length);
		}

		return $tables;
	}

	/**
	 * A font whose cmap puts every character of a run on the same glyph, so that no two characters
	 * run on together in both code and glyph and repackaging takes a segment for each.
	 *
	 * Built out of a font in the corpus with its cmap replaced, rather than committed, since it is
	 * of no interest to the golden masters and 20 KB of it is a glyphIdArray.
	 *
	 * @return string Where the font was written
	 */
	private function fontOfOneSegmentPerCharacter($characters)
	{
		$writer = new TableWriter();
		foreach ($this->tables(file_get_contents($this->file('angerthas'))) as $tag => $bytes) {
			$writer->add($tag, $tag === 'cmap' ? $this->cmapOfOneGlyph($characters) : $bytes);
		}

		return (new Cache($this->tmpDir))->write('one-segment-per-character.ttf', $writer->program());
	}

	/**
	 * @return string A cmap of one format 4 subtable, mapping $characters codes from U+0100 onto glyph 1
	 */
	private function cmapOfOneGlyph($characters)
	{
		$index = TableWriter::uint16s([0, 1, 3, 1]) . TableWriter::uint32(12);

		$subtable = TableWriter::uint16s(array_merge(
			[4, 0, 0, 4, 4, 1, 0], // format, length (set below), language, segCountX2, searchRange, entrySelector, rangeShift
			[0x0100 + $characters - 1, 0xFFFF, 0], // endCode, then reservedPad
			[0x0100, 0xFFFF], // startCode
			[0, 1], // idDelta
			[4, 0], // idRangeOffset: the glyphIdArray begins two segments past this field
			array_fill(0, $characters, 1) // glyphIdArray
		));

		return $index . TableWriter::setUInt16($subtable, 2, strlen($subtable));
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
