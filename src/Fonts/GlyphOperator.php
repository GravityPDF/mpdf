<?php

namespace Mpdf\Fonts;

class GlyphOperator
{

	const WORDS = 1 << 0;

	const XY_VALUES = 1 << 1;

	const SCALE = 1 << 3;

	const MORE = 1 << 5;

	const XYSCALE = 1 << 6;

	const TWOBYTWO = 1 << 7;

	const SCALED_OFFSET = 1 << 11;

	/**
	 * How many bytes of a compound glyph's component record follow its flags and glyph index: the two
	 * arguments, as words or bytes, then whichever transformation the flags say is there.
	 *
	 * @param int $flags The component's flags
	 *
	 * @return int
	 */
	public static function argumentsLength($flags)
	{
		$length = ($flags & self::WORDS) ? 4 : 2;

		if ($flags & self::SCALE) {
			$length += 2;
		} elseif ($flags & self::XYSCALE) {
			$length += 4;
		} elseif ($flags & self::TWOBYTWO) {
			$length += 8;
		}

		return $length;
	}
}
