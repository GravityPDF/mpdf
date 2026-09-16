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
 * #150 was that same array standing a repackaged subtable past anything the field can hold, and #156
 * the two subset builders writing it as well.
 */
class FontSubsetterCmapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Where the run fontOfOneSegmentPerCharacter maps begins. It starts at the space so that the font
	 * maps one, which makeSubsetSIP substitutes for an ASCII character the cmap does not reach.
	 */
	const FIRST_CODE = 0x20;

	/**
	 * Where the parser caches what it reads. Mpdf\Cache creates it.
	 *
	 * @var string
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-cmap';

	/**
	 * Every segment states an idRangeOffset of 0, which resolves its glyphs through idDelta, so the
	 * subtable ends at the segment arrays and the glyphIdArray that used to follow them was bytes no
	 * reader could reach.
	 *
	 * @dataProvider builderProvider
	 */
	public function testDeclaresTheLengthOfASubtableThatEndsAtItsSegmentArrays($font, $method)
	{
		$cmap = $this->table($this->build($this->file($font), $method, $this->pangram($font)), 'cmap');
		$this->assertNotNull($cmap, 'The font program carries no cmap table');

		$subtable = $this->format4Subtable($cmap);
		$this->assertNotNull($subtable, 'The cmap carries no format 4 subtable');

		list($offset, $end, $segCount) = $subtable;

		// format, length, language, segCountX2, searchRange, entrySelector, rangeShift and reservedPad,
		// then endCode, startCode, idDelta and idRangeOffset once per segment
		$this->assertSame(16 + 8 * $segCount, $end - $offset);

		$reader = new BlobReader($cmap);
		$reader->seek($offset + 2);

		$this->assertSame($end - $offset, $reader->readUInt16());
	}

	/**
	 * The field runs out at 8,189 segments and nothing in reach comes near - the widest font repackages
	 * into 3,378 and subsets into 824 - so the only way to stand a subtable past it is to build a font
	 * for the purpose.
	 *
	 * @dataProvider overflowProvider
	 */
	public function testRefusesASubtableLongerThanItsLengthFieldCanState($method, $message)
	{
		$this->expectException(\Mpdf\Exception\FontException::class);
		$this->expectExceptionMessage($message);

		$characters = 10000;
		$this->build(
			$this->fontOfOneSegmentPerCharacter($characters),
			$method,
			range(self::FIRST_CODE, self::FIRST_CODE + $characters - 1)
		);
	}

	/**
	 * makeSubsetSIP writes a format 6 subtable under (1,0) ahead of its format 4 one under (3,0). Its
	 * length is a uint16 too, and at two bytes a character it runs out at 32,762 - which a subset in a
	 * document never nears, at 255 characters, but the two wide synthetic fonts' whole character sets
	 * pass. Past it the subtable is left out, since the format 4 one maps the same codes whole.
	 *
	 * @dataProvider format6Provider
	 */
	public function testDeclaresTheLengthOfEverySubtableMakeSubsetSipWrites($characters, array $encodings)
	{
		$font = 'Blank-WideCmap-Synthetic';
		$subset = array_keys($this->parser()->getCTG($this->file($font), 0, false, 0));
		sort($subset);

		$cmap = $this->table($this->build($this->file($font), 'makeSubsetSIP', array_slice($subset, 0, $characters)), 'cmap');
		$this->assertNotNull($cmap, 'The font program carries no cmap table');

		$records = $this->encodingRecords($cmap);

		$reader = new BlobReader($cmap);
		$offsets = array_values($records);
		$offsets[] = strlen($cmap);
		for ($i = 0; $i < count($records); $i++) {
			$reader->seek($offsets[$i] + 2);
			$this->assertSame($offsets[$i + 1] - $offsets[$i], $reader->readUInt16());
		}

		$this->assertSame($encodings, array_keys($records));
	}

	public function format6Provider()
	{
		return [
			'the most a format 6 subtable holds' => [32762, ['1,0', '3,0']],
			'one character more' => [32763, ['3,0']],
		];
	}

	/**
	 * repackageTTF maps the glyphs the cmap does not reach into the Private Use Area as well, so it
	 * takes one segment more than the subset builders do.
	 */
	public function overflowProvider()
	{
		return [
			'makeSubset' => ['makeSubset', 'subsets into a format 4 cmap subtable of 80024 bytes'],
			'makeSubsetSIP' => ['makeSubsetSIP', 'subsets into a format 4 cmap subtable of 80024 bytes'],
			'repackageTTF' => ['repackageTTF', 'repackages into a format 4 cmap subtable of 80032 bytes'],
		];
	}

	/**
	 * Every builder over the whole corpus, as the golden masters take it, so that a font added to
	 * tests/data/ttf is measured here too.
	 */
	public function builderProvider()
	{
		$master = new SubsetGoldenMaster();

		$cases = [];
		foreach (array_keys($master->fonts()) as $font) {
			foreach (['makeSubset', 'makeSubsetSIP', 'repackageTTF'] as $method) {
				$cases[$font . ' ' . $method] = [$font, $method];
			}
		}

		return $cases;
	}

	/**
	 * repackageTTF rewrites the whole font rather than a subset, so it takes no character list. It
	 * only builds a cmap of its own under useOTL; without it the original table is copied over.
	 *
	 * @return string The font program the builder emits
	 */
	private function build($file, $method, array $subset)
	{
		$subsetter = $this->subsetter();

		return $method === 'repackageTTF'
			? $subsetter->repackageTTF($file, 0, false, true)
			: $subsetter->$method($file, $subset, 0, false, false);
	}

	/**
	 * @return array|null The format 4 subtable's start and end within the cmap and its segment count,
	 *                    or null if there is none
	 */
	private function format4Subtable($cmap)
	{
		$offsets = array_values($this->encodingRecords($cmap));
		$reader = new BlobReader($cmap);

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
	 * @return int[] Each encoding record's subtable offset, keyed "platform,encoding" in the order listed
	 */
	private function encodingRecords($cmap)
	{
		$reader = new BlobReader($cmap);
		$reader->skip(2); // version
		$subtableCount = $reader->readUInt16();

		$records = [];
		for ($i = 0; $i < $subtableCount; $i++) {
			$encoding = $reader->readUInt16() . ',' . $reader->readUInt16();
			$records[$encoding] = FontReader::uint32($reader->read(4));
		}

		return $records;
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
	 * @return string A cmap of one format 4 subtable, mapping $characters codes from FIRST_CODE onto glyph 1
	 */
	private function cmapOfOneGlyph($characters)
	{
		$index = TableWriter::uint16s([0, 1, 3, 1]) . TableWriter::uint32(12);

		$subtable = TableWriter::uint16s(array_merge(
			[4, 0, 0, 4, 4, 1, 0], // format, length (set below), language, segCountX2, searchRange, entrySelector, rangeShift
			[self::FIRST_CODE + $characters - 1, 0xFFFF, 0], // endCode, then reservedPad
			[self::FIRST_CODE, 0xFFFF], // startCode
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
