<?php

namespace Mpdf\Fonts;

/**
 * The fixed-width hexadecimal strings the OTL code writes characters as.
 *
 * Coverage tables, mark and ligature classes and substitution rules are all carried as text and
 * compared as text: GlyphClassMarks is one run of them that membership is tested against with
 * strpos(), and a substitution of one character by several is their concatenation. Both only work
 * while every character takes up the same number of characters, which is what this is for.
 *
 * Five digits is that width. It covers everything up to U+FFFFF; a character above that - plane 16,
 * the second private use area - comes back six digits wide and lines up with nothing.
 */
class GlyphString
{

	/**
	 * @param int $codepoint A Unicode code point
	 *
	 * @return string It as five upper-case hex digits
	 */
	public static function of($codepoint)
	{
		return str_pad(strtoupper(dechex($codepoint)), 5, '0', STR_PAD_LEFT);
	}

}
