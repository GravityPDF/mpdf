<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FontReader;

/**
 * Every glyph a GSUB table can put into a run of text.
 *
 * A colour font is laid out from its cmap and its GSUB alone: a glyph neither reaches is only ever
 * drawn inside another glyph, as a COLR layer is, and needs no character code of its own. Noto
 * Color Emoji COLRv1 has 41,863 glyphs, most of them layers, which would outrun the Private Use
 * codes mPDF hands out many times over.
 *
 * Only the lookup types that output a glyph are read: single, multiple, alternate, ligature and
 * reverse chaining. The contextual types only name nested lookups, which are in the list too.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub
 */
class GsubOutputs
{

	/**
	 * @param FontReader $reader  The font
	 * @param array      $lookups The LookupList as TTFontFile reads it, extensions resolved and each
	 *                            subtable offset absolute
	 *
	 * @return true[] Glyph id => true
	 */
	public static function glyphs(FontReader $reader, array $lookups)
	{
		$glyphs = [];
		foreach ($lookups as $lookup) {
			foreach ($lookup['Subtables'] as $subtable) {
				foreach (self::outputs($reader, $lookup['Type'], $subtable) as $glyph) {
					$glyphs[$glyph] = true;
				}
			}
		}

		return $glyphs;
	}

	/**
	 * Whether a glyph is among the components of any ligature after its first, which is where U+FE0F
	 * stands in a sequence that includes it
	 *
	 * @param FontReader $reader  The font
	 * @param array      $lookups As glyphs() takes them
	 * @param int        $glyph   The glyph id
	 *
	 * @return bool
	 */
	public static function inLigatures(FontReader $reader, array $lookups, $glyph)
	{
		foreach ($lookups as $lookup) {
			if ($lookup['Type'] != 4) {
				continue;
			}
			foreach ($lookup['Subtables'] as $subtable) {
				foreach (self::ligatures($reader, $subtable) as $ligature) {
					// ligatureGlyph, componentCount, then the components after the first
					$reader->seek($ligature + 2);
					if (in_array($glyph, SequenceRule::values($reader, $reader->readUInt16() - 1), true)) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @param FontReader $reader   The font
	 * @param int        $subtable A Ligature Substitution subtable's absolute offset
	 *
	 * @return int[] Where each of its Ligature tables starts
	 */
	private static function ligatures(FontReader $reader, $subtable)
	{
		// ligatureSetCount, past the format and coverageOffset
		$reader->seek($subtable + 4);
		$sets = [[]];
		foreach (SequenceRule::coverageOffsets($reader, $subtable, $reader->readUInt16()) as $set) {
			$sets[] = SequenceRule::ruleOffsets($reader, $set);
		}

		return call_user_func_array('array_merge', $sets);
	}

	/**
	 * @param FontReader $reader   The font
	 * @param int        $type     The lookup type, extensions resolved
	 * @param int        $subtable The subtable's absolute offset
	 *
	 * @return int[] The glyphs one subtable can output
	 */
	private static function outputs(FontReader $reader, $type, $subtable)
	{
		$reader->seek($subtable);
		$format = $reader->readUInt16();

		switch ($type) {
			case 1:
				$coverage = $subtable + $reader->readUInt16();
				if ($format !== 1) {
					return SequenceRule::values($reader, $reader->readUInt16());
				}
				$delta = $reader->readInt16();
				$reader->seek($coverage);
				$outputs = [];
				foreach (Coverage::glyphs($reader) as $glyph) {
					$outputs[] = ($glyph + $delta) & 0xFFFF;
				}

				return $outputs;

			case 2:
			case 3:
				// A Sequence table and an AlternateSet are the same shape: a count and the glyphs
				$reader->skip(2); // coverageOffset
				$outputs = [];
				foreach (SequenceRule::coverageOffsets($reader, $subtable, $reader->readUInt16()) as $set) {
					$reader->seek($set);
					$outputs = array_merge($outputs, SequenceRule::values($reader, $reader->readUInt16()));
				}

				return $outputs;

			case 4:
				$outputs = [];
				foreach (self::ligatures($reader, $subtable) as $ligature) {
					$reader->seek($ligature);
					$outputs[] = $reader->readUInt16();
				}

				return $outputs;

			case 8:
				$reader->skip(2); // coverageOffset
				$reader->skip(2 * $reader->readUInt16()); // backtrackCoverageOffsets
				$reader->skip(2 * $reader->readUInt16()); // lookaheadCoverageOffsets

				return SequenceRule::values($reader, $reader->readUInt16());
		}

		return [];
	}
}
