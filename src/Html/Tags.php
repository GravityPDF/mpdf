<?php

namespace Mpdf\Html;

/**
 * Finds the tags in HTML, reading a value quoted after "=" whole, so a "<" or ">" inside it does not end the tag.
 * Any "<" starts a tag, and comments and raw text are not told apart, so this is not yet a browser's tokenizer
 */
class Tags
{

	/**
	 * An "=", and the quoted value after it if there is one, read whole so a ">" inside it, as in
	 * style="content: '>'", does not end the tag. Any other quote, or one never closed, is an ordinary character
	 */
	const QUOTED_VALUE_PATTERN = '=\s*+(?:"[^"]*+"|\'[^\']*+\')?';

	/**
	 * A tag, with its content between the angle brackets captured
	 */
	const TAG_PATTERN = '<((?:[^>=]++|' . self::QUOTED_VALUE_PATTERN . ')*+)>';

	/**
	 * Splits HTML into text and the content of its tags, text first and the two alternating
	 *
	 * @param string $html
	 *
	 * @return string[]
	 */
	public static function split($html)
	{
		return preg_split('/' . self::TAG_PATTERN . '/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
	}

	/**
	 * Drops the tags whose name is not among the enabled ones, keeping what is between them. strip_tags() would also
	 * delete a "<" or ">" inside a quoted attribute value of a tag it keeps
	 *
	 * @param string $html
	 * @param string $enabledTags The names kept, as strip_tags() takes them: "<a><b>…"
	 *
	 * @return string
	 */
	public static function removeUnsupported($html, $enabledTags)
	{
		preg_match_all('/<(\w+)>/', $enabledTags, $names);

		// A kept tag is skipped whole, so the search does not start again inside one of its values
		$kept = '(?=<\/?(?:' . implode('|', $names[1]) . ')(?:\s|\/?>))' . self::TAG_PATTERN . '(*SKIP)(*FAIL)';

		return preg_replace('/' . $kept . '|' . self::TAG_PATTERN . '/i', '', $html);
	}


	/**
	 * Writes each tag of the given names as one that closes itself, with " />": "<br>", "<br/>" and
	 * "<input value="a > b">" become "<br />", "<br />" and "<input value="a > b" />"
	 *
	 * @param string $html
	 * @param string $names The tag names, separated by "|"
	 *
	 * @return string
	 */
	public static function closeSelfClosing($html, $names)
	{
		$html = preg_replace('/(<(?:' . $names . ')(?:[^>=\/]++|' . self::QUOTED_VALUE_PATTERN . ')*+)>/i', '\\1 />', $html);

		return preg_replace('/(<(?:' . $names . '))\/>/i', '\\1 />', $html);
	}

}
