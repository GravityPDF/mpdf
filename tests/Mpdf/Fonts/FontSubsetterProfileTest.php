<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * With _RECALC_PROFILE defined true, makeSubset works out head's bounds, hhea's extremes and maxp's
 * maxima from the glyphs it keeps rather than copying the original font's.
 *
 * The constant is read when FontSubsetter loads and cannot be redefined afterwards, so each case runs
 * in a process of its own that defines it first. The expected values are what fontTools' maxp.recalc()
 * and hhea.recalc() work out for the same program.
 */
class FontSubsetterProfileTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Poppins' glyph for the acute accent, a component of Aacute
	 */
	const ACUTE = 774;

	/**
	 * @var string
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-profile';

	/**
	 * A is simple, Aacute a compound of A and acute, and the soft hyphen a compound of hyphen, which is
	 * itself a compound of minus - so the subset holds simple glyphs, a compound and a nested compound.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider fontProvider
	 */
	public function testRecalculatesTheMaximaOfTheGlyphsASubsetKeeps($emptyAcute, array $expected)
	{
		$this->assertFalse(class_exists(FontSubsetter::class, false), 'FontSubsetter was loaded before the constant could be defined');
		define('_RECALC_PROFILE', true);

		$file = GoldenMaster::FONT_DIR . '/Poppins-Regular.ttf';
		if ($emptyAcute) {
			$tables = $this->tables(file_get_contents($file));
			$file = $this->writeFont('empty-acute.ttf', $this->withGlyph($tables, self::ACUTE, ''));
		}

		$subsetter = new FontSubsetter(new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win'));
		$program = $subsetter->makeSubset($file, [0x41, 0xAD, 0xC1], 0, false, false);

		$reader = new BlobReader($this->tables($program)['maxp']);
		$reader->skip(6); // version, numGlyphs
		$maxima = [
			'maxPoints' => $reader->readUInt16(),
			'maxContours' => $reader->readUInt16(),
			'maxCompositePoints' => $reader->readUInt16(),
			'maxCompositeContours' => $reader->readUInt16(),
		];
		$reader->skip(14); // maxZones to maxSizeOfInstructions
		$maxima['maxComponentElements'] = $reader->readUInt16();
		$maxima['maxComponentDepth'] = $reader->readUInt16();

		$this->assertSame($expected, $maxima);
	}

	public function fontProvider()
	{
		return [
			'Poppins' => [false, [
				'maxPoints' => 16,
				'maxContours' => 5,
				'maxCompositePoints' => 15,
				'maxCompositeContours' => 3,
				'maxComponentElements' => 2,
				'maxComponentDepth' => 2,
			]],
			// An empty glyph has no outline to record, so Aacute reaches a component with nothing to add
			'Poppins with an empty acute' => [true, [
				'maxPoints' => 16,
				'maxContours' => 5,
				'maxCompositePoints' => 11,
				'maxCompositeContours' => 2,
				'maxComponentElements' => 2,
				'maxComponentDepth' => 2,
			]],
		];
	}

	/**
	 * Every case subsets the space alone, which keeps .notdef, the space and glyph 2. In both Noto fonts
	 * .notdef and the space are empty, and .notdef's advance of 600 is the widest; glyph 2 is the only
	 * outline. In Blank-WideCmap-Synthetic no glyph has one.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider boundsProvider
	 */
	public function testRecalculatesTheBoundsOfTheGlyphsASubsetKeeps($font, $edit, array $expected)
	{
		$this->assertFalse(class_exists(FontSubsetter::class, false), 'FontSubsetter was loaded before the constant could be defined');
		define('_RECALC_PROFILE', true);

		$subsetter = new FontSubsetter(new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win'));
		$tables = $this->tables($subsetter->makeSubset($this->editedFont($font, $edit), [0x20], 0, false, false));

		$head = new BlobReader($tables['head']);
		$head->skip(36); // version to created and modified
		$hhea = new BlobReader($tables['hhea']);
		$hhea->skip(10); // version, ascender, descender, lineGap

		$this->assertSame($expected, [
			'xMin' => $head->readInt16(),
			'yMin' => $head->readInt16(),
			'xMax' => $head->readInt16(),
			'yMax' => $head->readInt16(),
			'advanceWidthMax' => $hhea->readUInt16(),
			'minLeftSideBearing' => $hhea->readInt16(),
			'minRightSideBearing' => $hhea->readInt16(),
			'xMaxExtent' => $hhea->readInt16(),
		]);
	}

	public function boundsProvider()
	{
		return [
			// Glyph 2 sits right of the origin, so no bound or side bearing reaches down to zero
			'Every outline right of the origin' => ['NotoMusic-GSUB52-Subset', null, [
				'xMin' => 50,
				'yMin' => -16,
				'xMax' => 351,
				'yMax' => 284,
				'advanceWidthMax' => 600,
				'minLeftSideBearing' => 50,
				'minRightSideBearing' => 50,
				'xMaxExtent' => 351,
			]],
			'Every outline above the baseline' => ['NotoSansCoptic-GSUB81-Subset', null, [
				'xMin' => -313,
				'yMin' => 831,
				'xMax' => 313,
				'yMax' => 877,
				'advanceWidthMax' => 600,
				'minLeftSideBearing' => -313,
				'minRightSideBearing' => -313,
				'xMaxExtent' => 313,
			]],
			// Glyph 2's box moved to (-400, -300)-(-100, -50), hmtx's side bearing of -313 left as it is
			'Every outline left of the origin and below the baseline' => ['NotoSansCoptic-GSUB81-Subset', 'moveOutline', [
				'xMin' => -400,
				'yMin' => -300,
				'xMax' => -100,
				'yMax' => -50,
				'advanceWidthMax' => 600,
				'minLeftSideBearing' => -313,
				'minRightSideBearing' => 13,
				'xMaxExtent' => -13,
			]],
			'No outline at all' => ['Blank-WideCmap-Synthetic', null, [
				'xMin' => 0,
				'yMin' => 0,
				'xMax' => 0,
				'yMax' => 0,
				'advanceWidthMax' => 500,
				'minLeftSideBearing' => 0,
				'minRightSideBearing' => 0,
				'xMaxExtent' => 0,
			]],
			// .notdef given a glyph header with no contours and a box far wider than glyph 2's
			'A header with no contours' => ['NotoMusic-GSUB52-Subset', 'contourlessHeader', [
				'xMin' => 50,
				'yMin' => -16,
				'xMax' => 351,
				'yMax' => 284,
				'advanceWidthMax' => 600,
				'minLeftSideBearing' => 50,
				'minRightSideBearing' => 50,
				'xMaxExtent' => 351,
			]],
			// Glyph 2's advance raised to 33000, which reads as -32536 if taken for an int16. Not higher: its
			// right side bearing rises with it, and hhea holds that as an int16
			'An advance wider than an int16 holds' => ['NotoMusic-GSUB52-Subset', 'wideAdvance', [
				'xMin' => 50,
				'yMin' => -16,
				'xMax' => 351,
				'yMax' => 284,
				'advanceWidthMax' => 33000,
				'minLeftSideBearing' => 50,
				'minRightSideBearing' => 32649,
				'xMaxExtent' => 351,
			]],
		];
	}

	/**
	 * The corpus has no outline wholly left of the origin and below the baseline, no glyph header
	 * without contours, and no advance above 32767, so boundsProvider()'s edits make them.
	 *
	 * @return string Where the font is
	 */
	private function editedFont($font, $edit)
	{
		$file = GoldenMaster::FONT_DIR . '/' . $font . '.ttf';
		if ($edit === null) {
			return $file;
		}

		$tables = $this->tables(file_get_contents($file));

		if ($edit === 'moveOutline') {
			$offsets = array_values(unpack('n*', $tables['loca']));
			$tables['glyf'] = TableWriter::replace(
				$tables['glyf'],
				2 * $offsets[2] + 2, // past numberOfContours
				TableWriter::int16(-400) . TableWriter::int16(-300) . TableWriter::int16(-100) . TableWriter::int16(-50)
			);
		} elseif ($edit === 'contourlessHeader') {
			$header = TableWriter::int16(0) . TableWriter::int16(-1000) . TableWriter::int16(-1000)
				. TableWriter::int16(1000) . TableWriter::int16(1000);
			$tables = $this->withGlyph($tables, 0, $header);
		} elseif ($edit === 'wideAdvance') {
			$tables['hmtx'] = TableWriter::setUInt16($tables['hmtx'], 2 * 4, 33000);
		}

		return $this->writeFont($font . '-' . $edit . '.ttf', $tables);
	}

	/**
	 * Replace one glyph's bytes in a font whose loca is the short format, offsets stored halved, and
	 * move every glyph after it by the difference.
	 *
	 * @param string[] $tables    Every table the font carries, by tag
	 * @param int      $glyphIdx  The glyph to replace
	 * @param string   $bytes     What to put in its place, of an even length; '' to leave it empty
	 *
	 * @return string[] The tables, with glyf and loca rewritten
	 */
	private function withGlyph(array $tables, $glyphIdx, $bytes)
	{
		$offsets = array_values(unpack('n*', $tables['loca']));
		$start = 2 * $offsets[$glyphIdx];
		$oldLength = 2 * ($offsets[$glyphIdx + 1] - $offsets[$glyphIdx]);
		$shift = (strlen($bytes) - $oldLength) / 2;
		for ($i = $glyphIdx + 1; $i < count($offsets); $i++) {
			$offsets[$i] += $shift;
		}

		$tables['glyf'] = substr($tables['glyf'], 0, $start) . $bytes . substr($tables['glyf'], $start + $oldLength);
		$tables['loca'] = TableWriter::uint16s($offsets);

		return $tables;
	}

	/**
	 * @param string[] $tables Every table the font carries, by tag
	 *
	 * @return string Where the font was written
	 */
	private function writeFont($name, array $tables)
	{
		$writer = new TableWriter();
		foreach ($tables as $tag => $bytes) {
			$writer->add($tag, $bytes);
		}

		return (new Cache($this->tmpDir))->write($name, $writer->program());
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
}
