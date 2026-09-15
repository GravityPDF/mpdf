<?php

namespace Mpdf\Fonts;

// Claim the font is for a Macintosh rather than for Windows. A host can set it before mPDF loads;
// nothing in mPDF sets it, and no font it writes has ever wanted it.
if (!defined('_TTF_MAC_HEADER')) {
	define('_TTF_MAC_HEADER', false);
}

/**
 * Writes the big-endian numbers an OpenType file is made of, and assembles the tables written into
 * a font program.
 *
 * The mirror of FontReader. Every method that emits a number is named for the data type in the
 * spec's own table, so that a line building a structure can be read straight against the structure
 * it is building, the same way the reader's can:
 *
 *     uint16  segCountX2
 *     uint16  searchRange
 *     uint16  endCode[segCount]
 *
 * The three that patch a value into a table already built are named for the same types. A font is
 * full of offsets and lengths that are only known once the thing they point at has been written, so
 * the subsetter reserves the field, writes the rest, and comes back - which the readers have no
 * counterpart for.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/otff#data-types
 */
class TableWriter
{

	/**
	 * The tables written so far, by four-character tag
	 *
	 * @var string[]
	 */
	private $tables = [];

	/**
	 * uint16: an unsigned 16-bit integer. Also Offset16, which the spec measures from the start of
	 * some enclosing table, and UFWORD, which is a uint16 in font design units.
	 */
	public static function uint16($value)
	{
		return pack('n', $value);
	}

	/**
	 * int16: a signed 16-bit integer, two's complement. Also FWORD.
	 *
	 * PHP has no pack format for a signed big-endian integer before 7.2 and this library supports
	 * 5.6, but none is needed on this side: pack('n') keeps the low sixteen bits, and for a negative
	 * integer those are already its two's complement. It is named apart from uint16 so that a line
	 * writing a field says which the spec calls for, the way the reader's two do.
	 */
	public static function int16($value)
	{
		return pack('n', $value);
	}

	/**
	 * uint32: an unsigned 32-bit integer. Also Offset32, and Tag where one is held as a number.
	 */
	public static function uint32($value)
	{
		return pack('N', $value);
	}

	/**
	 * A run of uint16s, which is most of what a cmap or a loca is
	 *
	 * @param int[] $values
	 */
	public static function uint16s(array $values)
	{
		// pack('n*', ...$values) would say this in one line, but argument unpacking of a string-keyed
		// array is a fatal before PHP 8.1 and this library supports 5.6. Five times faster than
		// concatenating pack('n', $v) per entry, which is what this replaced.
		return call_user_func_array('pack', array_merge(['n*'], array_values($values)));
	}

	/**
	 * Replace the uint16 at $offset in $table with $value.
	 *
	 * @return string The table, with the field replaced
	 */
	public static function setUInt16($table, $offset, $value)
	{
		return self::replace($table, $offset, self::uint16($value));
	}

	/**
	 * Replace the int16 at $offset in $table with $value.
	 *
	 * @return string The table, with the field replaced
	 */
	public static function setInt16($table, $offset, $value)
	{
		return self::replace($table, $offset, self::int16($value));
	}

	/**
	 * Replace the bytes at $offset in $table with $bytes, leaving its length unchanged.
	 *
	 * @return string The table, with the bytes replaced
	 */
	public static function replace($table, $offset, $bytes)
	{
		return substr_replace($table, $bytes, $offset, strlen($bytes));
	}

	/**
	 * Add one finished table to the font being built.
	 *
	 * The head table states a checksum for the whole file, which cannot be known until every table
	 * including this one is in place, so the field is zeroed here and filled in by program().
	 */
	public function add($tag, $data)
	{
		if ($tag === 'head') {
			$data = self::replace($data, 8, "\0\0\0\0");
		}

		$this->tables[$tag] = $data;
	}

	/**
	 * Assemble everything added into one font program: the header, the table directory, and the
	 * tables themselves, each padded to a multiple of four bytes.
	 *
	 * @return string The font program, ready to embed
	 */
	public function program()
	{
		$numTables = count($this->tables);

		// searchRange, entrySelector and rangeShift describe a binary search over the directory:
		// the largest power of two not more than numTables, times sixteen, its log, and what is left
		$searchRange = 1;
		$entrySelector = 0;
		while ($searchRange * 2 <= $numTables) {
			$searchRange *= 2;
			$entrySelector += 1;
		}
		$searchRange *= 16;
		$rangeShift = $numTables * 16 - $searchRange;

		$sfntVersion = _TTF_MAC_HEADER ? 0x74727565 : 0x00010000;
		$program = pack('Nnnnn', $sfntVersion, $numTables, $searchRange, $entrySelector, $rangeShift);

		// Table directory, in tag order, which is what a reader binary searches
		$tables = $this->tables;
		ksort($tables);
		$offset = 12 + $numTables * 16;
		$headStart = 0;

		foreach ($tables as $tag => $data) {
			if ($tag === 'head') {
				$headStart = $offset;
			}
			$checksum = TableChecksum::of($data);
			$program .= $tag . pack('nn', $checksum[0], $checksum[1]) . pack('NN', $offset, strlen($data));
			$offset += (strlen($data) + 3) & ~3;
		}

		foreach ($tables as $data) {
			$data .= "\0\0\0";
			$program .= substr($data, 0, (strlen($data) & ~3));
		}

		// head's checksumAdjustment: 0xB1B0AFBA less the checksum of everything, which is why add()
		// had to zero it first
		$adjustment = TableChecksum::subtract([0xB1B0, 0xAFBA], TableChecksum::of($program));

		return self::replace($program, $headStart + 8, pack('nn', $adjustment[0], $adjustment[1]));
	}
}
