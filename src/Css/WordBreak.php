<?php

namespace Mpdf\Css;

/**
 * Where word-break and overflow-wrap let a line break, read from the text parameters Mpdf::setCSS() keeps them in
 * under CssMode::STANDARD. word-wrap is read as overflow-wrap.
 *
 * When no element sets either property, a word too long for its line is cut at the edge, as mPDF has always done: on
 * paper an overflowing word is lost. Once one of them is set, a word overflows unless they let it break, as in a
 * browser.
 */
final class WordBreak
{

	/**
	 * The values each property takes, as NormalizeProperties keeps them
	 */
	const VALUES = [
		'WORD-BREAK' => ['normal', 'break-all', 'keep-all', 'break-word'],
		'OVERFLOW-WRAP' => ['normal', 'break-word', 'anywhere'],
	];

	/**
	 * Whether a value is one the property takes
	 *
	 * @param string $property WORD-BREAK or OVERFLOW-WRAP
	 * @param string $value Lowercased
	 *
	 * @return bool
	 */
	public static function isValid($property, $value)
	{
		return in_array($value, self::VALUES[$property], true);
	}

	/**
	 * Whether a line may break between any two letters, so it is filled to its edge (word-break: break-all)
	 *
	 * @param array|null $textparam
	 *
	 * @return bool
	 */
	public static function breaksAll($textparam)
	{
		return self::wordBreak($textparam) === 'break-all';
	}

	/**
	 * Whether CJK text keeps its letters together, breaking only at spaces and punctuation (word-break: keep-all)
	 *
	 * @param array|null $textparam
	 *
	 * @return bool
	 */
	public static function keepsAll($textparam)
	{
		return self::wordBreak($textparam) === 'keep-all';
	}

	/**
	 * Whether a word too long for its line is broken at the edge of the line rather than overflowing it
	 *
	 * @param array|null $textparam
	 *
	 * @return bool
	 */
	public static function breaksLongWords($textparam)
	{
		if (!isset($textparam['word-break']) && !isset($textparam['overflow-wrap'])) {
			return true;
		}

		return self::narrowsMinContent($textparam) || self::overflowWrap($textparam) === 'break-word';
	}

	/**
	 * Whether a table cell's narrowest width is taken from its widest letter rather than its widest word. Unlike
	 * overflow-wrap: break-word, anywhere and break-all count the breaks they allow in the narrowest width, and
	 * word-break: break-word is read as overflow-wrap: anywhere
	 *
	 * @param array|null $textparam
	 *
	 * @return bool
	 */
	public static function narrowsMinContent($textparam)
	{
		$wordBreak = self::wordBreak($textparam);

		return $wordBreak === 'break-all' || $wordBreak === 'break-word' || self::overflowWrap($textparam) === 'anywhere';
	}

	/**
	 * @param array|null $textparam
	 *
	 * @return string
	 */
	private static function wordBreak($textparam)
	{
		return isset($textparam['word-break']) ? $textparam['word-break'] : 'normal';
	}

	/**
	 * @param array|null $textparam
	 *
	 * @return string
	 */
	private static function overflowWrap($textparam)
	{
		return isset($textparam['overflow-wrap']) ? $textparam['overflow-wrap'] : 'normal';
	}
}
