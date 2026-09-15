<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FontReader;

/**
 * The rules a contextual or chained-contextual lookup matches with.
 *
 * GSUB Types 5 and 6 and GPOS Types 7 and 8 are the same four layouts twice over - one substitutes
 * where the other positions, but they read identically - and each layout is written once here.
 * What a rule names at each position differs by format: Format 1 names glyph ids, Format 2 names
 * classes of a Class Definition table, Format 3 names Coverage tables. All three are uint16s on the
 * wire, so the reading is one thing and the meaning is the caller's.
 *
 * Position 0 of an input sequence is never listed. Formats 1 and 2 reach a rule set through the
 * subtable's Coverage table or its input Class Definition, so whatever got there is position 0 and
 * the rule spells out only the rest - which is why an input count is always one more than the
 * values that follow it.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2#sequence-context-format-1-simple-glyph-contexts
 */
class SequenceRule
{

	/**
	 * A plain rule, which the spec calls SequenceRule and SequenceClassRule and the older names call
	 * SubRule, SubClassRule, PosRule and PosClassRule:
	 *
	 *     uint16   glyphCount
	 *     uint16   seqLookupCount
	 *     uint16   inputSequence[glyphCount - 1]      starting at position 1
	 *     SequenceLookupRecord seqLookupRecords[seqLookupCount]
	 *
	 * The reader is left at the records, which is where a caller that matched wants it.
	 *
	 * @return array [$input, $recordCount]: the input sequence from position 1, and how many lookup
	 *               records follow it
	 */
	public static function plain(FontReader $reader)
	{
		$glyphCount = $reader->readUInt16();
		$recordCount = $reader->readUInt16();

		return [self::values($reader, $glyphCount - 1), $recordCount];
	}

	/**
	 * A chained rule - ChainedSequenceRule and ChainedSequenceClassRule, or ChainSubRule,
	 * ChainSubClassRule, ChainPosRule and ChainPosClassRule:
	 *
	 *     uint16   backtrackGlyphCount
	 *     uint16   backtrackSequence[backtrackGlyphCount]
	 *     uint16   inputGlyphCount
	 *     uint16   inputSequence[inputGlyphCount - 1]    starting at position 1
	 *     uint16   lookaheadGlyphCount
	 *     uint16   lookaheadSequence[lookaheadGlyphCount]
	 *     uint16   seqLookupCount
	 *     SequenceLookupRecord seqLookupRecords[seqLookupCount]
	 *
	 * The count of records is left unread, because it sits past all three sequences: a caller that
	 * decides on the sequences alone - which is every applier, matching the context before it does
	 * anything about it - stops here and never touches the rest of a rule that did not match.
	 *
	 * Backtrack is in glyph sequence order, which runs away from the input rather than towards it:
	 * position 0 is the glyph immediately before the input.
	 *
	 * @return array [$backtrack, $input, $lookahead], the input from position 1
	 */
	public static function chained(FontReader $reader)
	{
		$backtrack = self::values($reader, $reader->readUInt16());
		$input = self::values($reader, $reader->readUInt16() - 1);
		$lookahead = self::values($reader, $reader->readUInt16());

		return [$backtrack, $input, $lookahead];
	}

	/**
	 * A sequence of positions, each one uint16: a glyph id in a Format 1 rule, a class number in a
	 * Format 2 one.
	 *
	 * @param int $count Values to read from the current position, in glyph sequence order
	 *
	 * @return int[]
	 */
	public static function values(FontReader $reader, $count)
	{
		$values = [];
		for ($i = 0; $i < $count; $i++) {
			$values[] = $reader->readUInt16();
		}

		return $values;
	}

	/**
	 * A sequence of positions, each an Offset16 to a Coverage table, which is how Format 3 spells a
	 * rule - it carries one rule and names the glyphs it matches a table at a time rather than a
	 * position at a time.
	 *
	 * Reading the offsets is kept apart from following them because a Format 3 subtable interleaves
	 * up to three of these with the counts between them: every offset has to be read through before
	 * any is followed, or the next count gets read from wherever the last Coverage table ended.
	 *
	 * @param int $base  Where the subtable starts, which the offsets are measured from
	 * @param int $count Offsets to read from the current position, in glyph sequence order
	 *
	 * @return int[] Absolute, from the start of the file
	 */
	public static function coverageOffsets(FontReader $reader, $base, $count)
	{
		$offsets = [];
		for ($i = 0; $i < $count; $i++) {
			$offsets[] = $base + $reader->readUInt16();
		}

		return $offsets;
	}

	/**
	 * The lookups a matched rule runs, and where in the match each one runs:
	 *
	 *     uint16   sequenceIndex       which glyph of the matched input to apply the lookup at
	 *     uint16   lookupListIndex     which lookup to apply
	 *
	 * @param int $count Records to read from the current position
	 *
	 * @return array Each a SequenceIndex and a LookupListIndex
	 */
	public static function lookupRecords(FontReader $reader, $count)
	{
		$records = [];
		for ($i = 0; $i < $count; $i++) {
			$records[] = [
				'SequenceIndex' => $reader->readUInt16(),
				'LookupListIndex' => $reader->readUInt16(),
			];
		}

		return $records;
	}
}
