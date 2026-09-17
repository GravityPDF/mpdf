<?php

namespace Mpdf\Fonts;

/**
 * The fixed-width hexadecimal strings the OTL code writes characters as.
 *
 * Coverage tables, mark and ligature classes and substitution rules are all carried as text, and a
 * substitution of one character by several is their concatenation, which only works while every
 * character takes up the same number of characters. That is what this is for.
 *
 * Five digits is that width. It covers everything up to U+FFFFF; a character above that - plane 16,
 * the second private use area - comes back six digits wide and lines up with nothing. A six-digit
 * glyph holds two five-digit ones, U+100300 both U+10030 and U+0300, so a list of glyphs is never
 * searched for one with strpos(): set() and inList() test it glyph by glyph.
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

	/**
	 * A list of glyphs as a set, for a list asked about glyph after glyph.
	 *
	 * @param string $glyphs "|"-separated, with or without the space GDEF's classes put before each
	 *                       glyph: " 00300| 00301"
	 *
	 * @return true[] Glyph, as hex => true
	 */
	public static function set($glyphs)
	{
		$set = [];
		foreach (explode('|', $glyphs) as $glyph) {
			$glyph = trim($glyph);
			if ($glyph !== '') {
				$set[$glyph] = true;
			}
		}

		return $set;
	}

	/**
	 * Whether a list of glyphs names this one, for a list asked about too seldom to be worth a set.
	 *
	 * The glyph has to stand on its own: the list may separate glyphs with anything but a hex digit,
	 * so the one test serves GDEF's " 00300| 00301", a Coverage table's "00300|00301", the space-ended
	 * "0FE8E 0FE94 " of `finals`, and an ignore pattern's "((?:(?: 00300| 00301))*)".
	 *
	 * @param string $glyphs
	 * @param string $glyph As hex
	 *
	 * @return bool
	 */
	public static function inList($glyphs, $glyph)
	{
		$length = strlen($glyph);

		for ($at = strpos($glyphs, $glyph); $at !== false; $at = strpos($glyphs, $glyph, $at + 1)) {
			if (!self::isHexDigitAt($glyphs, $at - 1) && !self::isHexDigitAt($glyphs, $at + $length)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a match in a list is flanked by another hex digit, which makes it part of a longer glyph
	 * rather than a glyph of its own.
	 *
	 * @param string $string The list being searched
	 * @param int    $at     A position either side of a match, which may fall outside the string
	 *
	 * @return bool False before the start or past the end, where nothing flanks the match
	 */
	private static function isHexDigitAt($string, $at)
	{
		return $at >= 0 && isset($string[$at]) && strpos('0123456789ABCDEFabcdef', $string[$at]) !== false;
	}

}
