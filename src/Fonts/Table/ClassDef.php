<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FontReader;

/**
 * Class Definition table: which class each glyph belongs to.
 *
 * Used where a rule applies to a kind of glyph rather than to a list of them - the class-based
 * contextual lookups, GPOS pair positioning, and GDEF's own glyph and mark attachment classes.
 *
 * Format 1, a class per glyph over a contiguous run:
 *
 *     uint16   classFormat         set to 1
 *     uint16   startGlyphID
 *     uint16   glyphCount
 *     uint16   classValueArray[glyphCount]
 *
 * Format 2, a class per range:
 *
 *     uint16   classFormat         set to 2
 *     uint16   classRangeCount
 *     ClassRangeRecord classRangeRecords[classRangeCount]
 *
 * and each ClassRangeRecord is
 *
 *     uint16   startGlyphID
 *     uint16   endGlyphID
 *     uint16   class
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2#class-definition-table
 */
class ClassDef
{

	/**
	 * Where the Class Definition table a subtable points at starts.
	 *
	 * A null offset means the subtable states no such table, and every glyph is then in class 0 -
	 * which is what a chained context says where a backtrack or lookahead position matches anything.
	 * Adding it to the subtable's own start instead reads the subtable header as a Class Definition
	 * table, whose format is the subtable's own and whose count is one of its offsets, and the
	 * ranges are read off the end of the table (#326).
	 *
	 * The Coverage offsets beside them are read without this test: the spec requires those.
	 *
	 * @param int $subtableOffset Where the subtable starts; the reader is at the ClassDef's offset
	 *
	 * @return int From the start of the file, or 0 where the subtable states no such table
	 */
	public static function offset(FontReader $reader, $subtableOffset)
	{
		$classDef = $reader->readUInt16();

		return $classDef ? $subtableOffset + $classDef : 0;
	}

	/**
	 * Read the Class Definition table at $offset, as pairs() reads one.
	 *
	 * @param int $offset From the start of the file, as offset() gives it: 0 is no table at all, and
	 *                    assigns no glyph to any class
	 *
	 * @return array[] One [glyphID, class] pair per glyph the table assigns, in table order
	 */
	public static function pairsAt(FontReader $reader, $offset)
	{
		if (!$offset) {
			return [];
		}

		$reader->seek($offset);

		return self::pairs($reader);
	}

	/**
	 * Read a Class Definition table from wherever the reader is.
	 *
	 * Any glyph the table does not mention belongs to class 0, and the spec says so rather than
	 * listing them. That is not expanded here: the callers each decide what class 0 means to them,
	 * and expanding it would mean inventing every glyph in the font.
	 *
	 * Pairs rather than a glyph => class map, so that a malformed font whose ranges overlap reads back
	 * exactly as it is written - once per record, in record order - rather than silently collapsing.
	 *
	 * @return array[] One [glyphID, class] pair per glyph the table assigns, in table order
	 */
	public static function pairs(FontReader $reader)
	{
		$pairs = [];
		$format = $reader->readUInt16();

		if ($format === 1) {
			$startGlyphID = $reader->readUInt16();
			$glyphCount = $reader->readUInt16();
			for ($i = 0; $i < $glyphCount; $i++) {
				$pairs[] = [$startGlyphID + $i, $reader->readUInt16()];
			}

			return $pairs;
		}

		if ($format === 2) {
			$rangeCount = $reader->readUInt16();
			for ($r = 0; $r < $rangeCount; $r++) {
				$startGlyphID = $reader->readUInt16();
				$endGlyphID = $reader->readUInt16();
				$class = $reader->readUInt16();
				for ($glyphID = $startGlyphID; $glyphID <= $endGlyphID; $glyphID++) {
					$pairs[] = [$glyphID, $class];
				}
			}
		}

		return $pairs;
	}

	/**
	 * Read a Class Definition table from wherever the reader is, as the glyphs of each class.
	 *
	 * For a caller that needs a glyph's place within its class and not only its membership: GPOS
	 * pair positioning indexes into a class by position, and GDEF's glyph and mark attachment
	 * classes are kept as one list per class. Class 0 is kept where the table states it, as pairs()
	 * keeps it.
	 *
	 * @return array[] class => glyph IDs in table order, lowest class first
	 */
	public static function glyphsByClass(FontReader $reader)
	{
		$glyphsByClass = [];
		foreach (self::pairs($reader) as $pair) {
			$glyphsByClass[$pair[1]][] = $pair[0];
		}

		ksort($glyphsByClass);

		return $glyphsByClass;
	}
}
