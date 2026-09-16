<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FontReader;

/**
 * Value Record: what a GPOS rule does to a glyph's placement and advance.
 *
 * A record carries no format of its own. The subtable states a ValueFormat, a bit per field, and
 * only the fields whose bit is set are written, in bit order:
 *
 *     0x0001   int16    xPlacement
 *     0x0002   int16    yPlacement
 *     0x0004   int16    xAdvance
 *     0x0008   int16    yAdvance
 *     0x0010   Offset16 xPlaDeviceOffset
 *     0x0020   Offset16 yPlaDeviceOffset
 *     0x0040   Offset16 xAdvDeviceOffset
 *     0x0080   Offset16 yAdvDeviceOffset
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#value-record
 */
class ValueRecord
{

	/**
	 * Read one record from wherever the reader is, and leave the reader past it.
	 *
	 * Only the first three fields are kept. mPDF lays text out horizontally, so the vertical advance
	 * means nothing to it, and a device table adjusts for a pixel grid a PDF does not have; both are
	 * stepped over.
	 *
	 * @param int $valueFormat The subtable's ValueFormat for this record
	 *
	 * @return array Whichever of XPlacement, YPlacement and XAdvance the format states, in font units.
	 *               A field the format leaves out is absent rather than 0: the shaper tells the two
	 *               apart.
	 */
	public static function read(FontReader $reader, $valueFormat)
	{
		$kept = [0x0001 => 'XPlacement', 0x0002 => 'YPlacement', 0x0004 => 'XAdvance'];
		$record = [];

		for ($bit = 0x0001; $bit <= 0x0080; $bit <<= 1) {
			if (!($valueFormat & $bit)) {
				continue;
			}

			if (isset($kept[$bit])) {
				$record[$kept[$bit]] = $reader->readInt16();
			} else {
				$reader->skip(2);
			}
		}

		return $record;
	}

	/**
	 * How many bytes a record of this format takes, which is what an array of them is stepped
	 * through by.
	 *
	 * Every set bit counts, the eight reserved ones included, where read() stops at 0x0080. A font
	 * setting a reserved bit is malformed either way; this is how the size has always been worked out.
	 *
	 * @param int $valueFormat
	 *
	 * @return int
	 */
	public static function size($valueFormat)
	{
		for ($fields = 0; $valueFormat; $fields++) {
			$valueFormat &= $valueFormat - 1; // clear the lowest set bit
		}

		return 2 * $fields;
	}
}
