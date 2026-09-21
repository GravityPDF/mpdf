<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\FileReader;
use Mpdf\Fonts\FontReader;

/**
 * loca: where each glyph starts in glyf, one entry more than there are glyphs, the last saying where
 * the final glyph ends. The short format halves its offsets.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/loca
 */
class Loca
{

	/**
	 * @param int $loca             Where loca starts
	 * @param int $indexToLocFormat head's: 0 for the short format, 1 for the long
	 * @param int $numGlyphs        maxp's glyph count
	 *
	 * @return int[] Glyph id => where it starts in glyf, with one past the end appended
	 *
	 * @throws \Mpdf\Exception\FontException If head names a format that is neither
	 */
	public static function offsets(FileReader $reader, $loca, $indexToLocFormat, $numGlyphs)
	{
		if ($indexToLocFormat == 0) {
			$offsets = [];
			foreach (unpack('n*', $reader->bytesAt($loca, ($numGlyphs + 1) * 2)) as $offset) {
				$offsets[] = $offset * 2;
			}

			return $offsets;
		}

		if ($indexToLocFormat == 1) {
			return array_values(unpack('N*', $reader->bytesAt($loca, ($numGlyphs + 1) * 4)));
		}

		throw new \Mpdf\Exception\FontException('Unknown location table format ' . $indexToLocFormat);
	}

	/**
	 * Where one glyph starts and ends in glyf, read on its own rather than with the whole table
	 *
	 * @param int $loca             Where loca starts
	 * @param int $indexToLocFormat head's: 0 for the short format, 1 for the long
	 * @param int $glyph            The glyph id
	 *
	 * @return int[]|null [start, end], or null where loca ends first or head names a format that is
	 *                    neither
	 */
	public static function range(FontReader $reader, $loca, $indexToLocFormat, $glyph)
	{
		if ($indexToLocFormat == 0) {
			$range = $reader->fieldsAt($loca + $glyph * 2, 4, 'n2');

			return $range === null ? null : [$range[0] * 2, $range[1] * 2];
		}

		return $indexToLocFormat == 1 ? $reader->fieldsAt($loca + $glyph * 4, 8, 'N2') : null;
	}
}
