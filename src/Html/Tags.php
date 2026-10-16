<?php

namespace Mpdf\Html;

/**
 * Finds the tags in HTML and reads their attributes, a value quoted after "=" whole, so a "<" or ">" inside it does not
 * end the tag. Any "<" starts a tag, and comments and raw text are not told apart, so this is not yet a browser's
 * tokenizer
 */
class Tags
{

	/**
	 * An "=", and the quoted value after it if there is one, read whole so a ">" inside it, as in
	 * style="content: '>'", does not end the tag. Any other quote, or one never closed, is an ordinary character
	 */
	const QUOTED_VALUE_PATTERN = '=\s*+(?:"[^"]*+"|\'[^\']*+\')?';

	/**
	 * A tag's content between its angle brackets, or the part of it after the name
	 */
	const CONTENT_PATTERN = '(?:[^>=]++|' . self::QUOTED_VALUE_PATTERN . ')*+';

	/**
	 * A tag, with its content between the angle brackets captured
	 */
	const TAG_PATTERN = '<(' . self::CONTENT_PATTERN . ')>';

	/**
	 * A name, then a value that may be double quoted, single quoted, unquoted, or absent altogether, as it is for
	 * boolean attributes such as "selected". Names start with a letter, so the "/" that closes an empty tag is not one
	 */
	const ATTRIBUTE_PATTERN = '/([a-zA-Z][\w:.\-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"]+)))?/';

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
	 * A pattern for the start tags of the given names, their content captured as TAG_PATTERN captures it. Every other
	 * tag is skipped whole, so one written inside its attribute value, as in title="draw <svg>", is not found
	 *
	 * @param string $names The tag names, separated by "|"
	 *
	 * @return string The pattern, without delimiters
	 */
	public static function startTagPattern($names)
	{
		// The name ends where its tag's content goes on, so <svg-icon> is not an <svg>
		$named = '<(?:' . $names . ')(?=[\s\/>])';
		$other = '(?=<[a-zA-Z\/!])(?!' . $named . ')' . self::TAG_PATTERN . '(*SKIP)(*FAIL)';

		return '(?|' . $other . '|(?=' . $named . ')' . self::TAG_PATTERN . ')';
	}

	/**
	 * A pattern for the elements of the given name, their start tag found as startTagPattern() finds it: the start
	 * tag's content is captured first, then what is between it and the first end tag. Used with the "s" modifier
	 *
	 * @param string $name
	 *
	 * @return string The pattern, without delimiters
	 */
	public static function elementPattern($name)
	{
		return self::startTagPattern($name) . '(.*?)<\/' . $name . '>';
	}

	/**
	 * Replaces each outermost element of the given name with what the callback returns for it. An end tag closes the
	 * nearest start tag still open, so an element of the same name nested inside stays part of the outer one, and a
	 * start tag never closed, or one that closes itself, is left as it was. Tags are found as startTagPattern() finds them
	 *
	 * @param string $html
	 * @param string $name
	 * @param callable $callback Given the element as elementPattern() captures it, with its end tag added: [element,
	 *                           start tag content, what is between the tags, end tag]
	 *
	 * @return string
	 */
	public static function replaceElements($html, $name, callable $callback)
	{
		preg_match_all('/' . self::startTagPattern($name . '|\/' . $name) . '/i', $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

		$open = [];
		$elements = [];
		foreach ($tags as $tag) {
			if ($tag[1][0][0] !== '/') {
				// One that closes itself leaves nothing open
				if (substr($tag[1][0], -1) !== '/') {
					$open[] = $tag;
				}
			} elseif ($open) {
				$start = array_pop($open);
				$elements[$start[0][1]] = [$start, $tag];
			}
		}

		ksort($elements);

		$replaced = '';
		$offset = 0;
		foreach ($elements as $from => $element) {
			// One inside an element already replaced is part of it
			if ($from < $offset) {
				continue;
			}

			list($start, $end) = $element;
			$innerFrom = $from + strlen($start[0][0]);
			$to = $end[0][1] + strlen($end[0][0]);

			$replaced .= substr($html, $offset, $from - $offset) . call_user_func($callback, [
				substr($html, $from, $to - $from),
				$start[1][0],
				substr($html, $innerFrom, $end[0][1] - $innerFrom),
				$end[0][0],
			]);
			$offset = $to;
		}

		return $replaced . substr($html, $offset);
	}

	/**
	 * Reads the content of a start tag into its name and its attributes, each value as it was written between its
	 * quotes. Names are uppercased, as HTML ignores their case, and a later attribute of the same name replaces an
	 * earlier one
	 *
	 * @param string $content What is between the tag's angle brackets: 'a title="x = 1" href=b'
	 *
	 * @return array The tag name, and the attribute values by name: ['A', ['TITLE' => 'x = 1', 'HREF' => 'b']]
	 */
	public static function attributes($content)
	{
		preg_match_all(self::ATTRIBUTE_PATTERN, $content, $matches, PREG_SET_ORDER);
		$tag = array_shift($matches);

		$attributes = [];
		foreach ($matches as $match) {
			// The value is the last group: PCRE drops the ones after the form that took part
			$attributes[strtoupper($match[1])] = count($match) > 2 ? end($match) : '';
		}

		return [$tag ? strtoupper($tag[1]) : '', $attributes];
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
