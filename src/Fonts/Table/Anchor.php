<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FontReader;

/**
 * Anchor table: the point on a glyph that a mark attaches to, or that joins to a neighbour.
 *
 *     uint16   anchorFormat        1, 2 or 3
 *     int16    xCoordinate
 *     int16    yCoordinate
 *
 * Format 2 follows the coordinates with a contour point and Format 3 with two device table offsets.
 * A contour point needs the hinted outline and a device table a pixel grid, and a PDF has neither,
 * so all three formats read as the coordinates alone.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#anchor-tables
 */
class Anchor
{

	/**
	 * @param int $offset Where the anchor table starts, from the start of the file
	 *
	 * @return int[] [$x, $y], in font units
	 */
	public static function coordinates(FontReader $reader, $offset)
	{
		$reader->seek($offset + 2); // past anchorFormat

		return [$reader->readInt16(), $reader->readInt16()];
	}
}
