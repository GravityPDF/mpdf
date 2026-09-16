<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FontReader;

/**
 * Mark Array table: the class and anchor of each mark a mark attachment subtable covers.
 *
 *     uint16   markCount
 *     MarkRecord markRecords[markCount]
 *
 * and each MarkRecord is
 *
 *     uint16   markClass
 *     Offset16 markAnchorOffset    from the start of the MarkArray
 *
 * The records are in the order of the subtable's mark Coverage table, so a mark's Coverage Index is
 * which record is its own.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#mark-array-table
 */
class MarkArray
{

	/**
	 * @param int $offset Where the MarkArray starts, from the start of the file
	 * @param int $index  The mark's Coverage Index
	 *
	 * @return array The mark's Class, and the AnchorX and AnchorY of the point on it that attaches
	 */
	public static function record(FontReader $reader, $offset, $index)
	{
		$reader->seek($offset + 2 + $index * 4); // past markCount, to the record
		$class = $reader->readUInt16();
		list($x, $y) = Anchor::coordinates($reader, $offset + $reader->readUInt16());

		return ['Class' => $class, 'AnchorX' => $x, 'AnchorY' => $y];
	}
}
