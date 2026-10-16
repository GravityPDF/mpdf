<?php

namespace Mpdf\Html;

use Mpdf\Css\CommentParser;

/**
 * Finds the tags in HTML and reads their attributes, a value quoted after "=" whole, so a "<" or ">" inside it does not
 * end the tag. Any "<" starts a tag, and comments and raw text are not told apart unless removeRawText() drops them
 * first, so this is not yet a browser's tokenizer
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
	 * What follows the name in an end tag. A browser reads "</p >", "</p\n>" and "</p class=x>" all as "</p>",
	 * ignoring whatever comes before the ">", so the name only has to end there
	 */
	const END_TAG_REST_PATTERN = '(?:[\s\/]' . self::CONTENT_PATTERN . ')?>';

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
		$named = self::namedTagStart($names);
		$other = '(?=<[a-zA-Z\/!])(?!' . $named . ')' . self::TAG_PATTERN . '(*SKIP)(*FAIL)';

		return '(?|' . $other . '|(?=' . $named . ')' . self::TAG_PATTERN . ')';
	}

	/**
	 * A pattern for the end tags of the given names, written as a browser accepts them: "</pre>", "</pre >" or
	 * "</pre class=x>", but not "</prefix>"
	 *
	 * @param string $names The tag names, separated by "|"
	 *
	 * @return string The pattern, without delimiters
	 */
	public static function endTagPattern($names)
	{
		return '<\/(?:' . $names . ')' . self::END_TAG_REST_PATTERN;
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
		return self::startTagPattern($name) . '(.*?)' . self::endTagPattern($name);
	}

	/**
	 * Replaces each outermost element of the given name with what the callback returns for it. Elements are found as
	 * elementAt() finds them, so an element of the same name nested inside stays part of the outer one, and a start
	 * tag never closed, or one that closes itself, is left as it was
	 *
	 * @param string $html
	 * @param string $name
	 * @param callable $callback Given the element as elementAt() returns it: [element, start tag content, what is
	 *                           between the tags, end tag]
	 *
	 * @return string
	 */
	public static function replaceElements($html, $name, callable $callback)
	{
		$outermost = [];
		foreach (self::pairs($html, preg_quote($name, '/'), 0, []) as $pair) {
			// Those already paired after its start tag are inside it
			while ($outermost && $outermost[count($outermost) - 1][0][0][1] > $pair[0][0][1]) {
				array_pop($outermost);
			}
			$outermost[] = $pair;
		}

		$replaced = '';
		$offset = 0;
		foreach ($outermost as $pair) {
			$element = self::element($html, $pair);
			$from = $pair[0][0][1];
			$replaced .= substr($html, $offset, $from - $offset) . call_user_func($callback, $element);
			$offset = $from + strlen($element[0]);
		}

		return $replaced . substr($html, $offset);
	}

	/**
	 * The element whose start tag begins at the offset. Unlike elementPattern(), an end tag closes the nearest start
	 * tag of its name still open, so an element of the same name nested inside stays part of it. Tags are found as
	 * startTagPattern() finds them, and names compared without regard to case
	 *
	 * @param string $html
	 * @param string $name
	 * @param int $offset
	 *
	 * @return string[]|null [element, start tag content, what is between the tags, end tag], the last two empty when
	 *                       the start tag closes itself; or null when no start tag of the name begins at the offset,
	 *                       or it is never closed
	 */
	public static function elementAt($html, $name, $offset)
	{
		$name = preg_quote($name, '/');
		if (!preg_match('/\G' . self::startTagPattern($name) . '/i', $html, $start, PREG_OFFSET_CAPTURE, $offset)) {
			return null;
		}

		if (self::closesItself($start[1][0])) {
			return [$start[0][0], $start[1][0], '', ''];
		}

		// Its own start tag is opened first, so the pair that starts there is the last of its element, and the HTML after
		// its end tag is not read
		foreach (self::pairs($html, $name, $offset + strlen($start[0][0]), [$start]) as $pair) {
			if ($pair[0][0][1] === $offset) {
				return self::element($html, $pair);
			}
		}

		return null;
	}

	/**
	 * Pairs the start and end tags of a name from the offset on, as each end tag closes the nearest start tag still
	 * open. A stray end tag, or a start tag that closes itself, is passed over, and a start tag never closed pairs with
	 * nothing
	 *
	 * @param string $html
	 * @param string $name The tag name, quoted for a pattern
	 * @param int $offset
	 * @param array[] $open The start tags already open before the offset
	 *
	 * @return \Generator Each [start tag, end tag] as it is closed, as startTagPattern() matches them with
	 *                    PREG_OFFSET_CAPTURE
	 */
	private static function pairs($html, $name, $offset, array $open)
	{
		$tag = '/' . self::startTagPattern($name . '|\/' . $name) . '/i';
		while (preg_match($tag, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$offset = $m[0][1] + strlen($m[0][0]);
			if ($m[1][0][0] !== '/') {
				if (!self::closesItself($m[1][0])) {
					$open[] = $m;
				}
			} elseif ($open) {
				yield [array_pop($open), $m];
			}
		}
	}

	/**
	 * The parts of the element a start and end tag enclose
	 *
	 * @param string $html
	 * @param array $pair A start and end tag from pairs()
	 *
	 * @return string[] [element, start tag content, what is between the tags, end tag]
	 */
	private static function element($html, array $pair)
	{
		list($start, $end) = $pair;
		$from = $start[0][1];
		$inner = $from + strlen($start[0][0]);

		return [
			substr($html, $from, $end[0][1] + strlen($end[0][0]) - $from),
			$start[1][0],
			substr($html, $inner, $end[0][1] - $inner),
			$end[0][0],
		];
	}

	/**
	 * Whether a start tag closes itself, as <svg/> does, and so opens nothing
	 *
	 * @param string $content The start tag's content
	 *
	 * @return bool
	 */
	private static function closesItself($content)
	{
		return substr($content, -1) === '/';
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
	 * The name in the content of a start or an end tag, uppercased, without what follows it: "P" for '/p class="x"'
	 *
	 * @param string $content What is between the tag's angle brackets
	 *
	 * @return string
	 */
	public static function name($content)
	{
		return strtoupper((string) strtok($content, " \t\n\r\f/"));
	}

	/**
	 * Drops the comments, and the script, style and textarea elements whose content a browser reads as text, so a tag
	 * written in them, as in var t = "<title>x</title>", is not found
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public static function removeRawText($html)
	{
		$comments = new CommentParser();
		$html = $comments->removeHtmlComments($comments->removeCommentsFromStyleBlocks($html));

		foreach (['script', 'style', 'textarea'] as $name) {
			if (stripos($html, '</' . $name) !== false) {
				$html = preg_replace('/' . self::elementPattern($name) . '/si', '', $html);
			}
		}

		return $html;
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
	 * Replaces what the given pattern matches outside tags: any tag the pattern does not match from its "<" is skipped
	 * whole, so nothing inside an attribute value, as in title="<br>", is replaced. Comments are not skipped
	 *
	 * @param string $html
	 * @param string $pattern Without delimiters or backtracking verbs such as (*SKIP). Its groups keep their numbers
	 * @param string $replacement As preg_replace() takes it
	 * @param string $modifiers
	 *
	 * @return string|null Null when the pattern fails, as preg_replace() returns it
	 */
	public static function replaceOutsideTags($html, $pattern, $replacement, $modifiers = '')
	{
		// A plain search is cheaper than one stepping over every tag, and most documents hold no match at all
		if (!preg_match('/' . $pattern . '/' . $modifiers, $html)) {
			return $html;
		}

		$otherTag = '(?=<[a-zA-Z\/])' . self::TAG_PATTERN . '(*SKIP)(*FAIL)';

		return preg_replace('/(?:' . $pattern . ')|' . $otherTag . '/' . $modifiers, $replacement, $html);
	}

	/**
	 * Writes each tag of the given names as one that closes itself, with " />": "<br>", "<br/>" and
	 * "<input value="a > b">" become "<br />", "<br />" and "<input value="a > b" />". One inside another tag's
	 * attribute value is left alone
	 *
	 * @param string $html
	 * @param string $names The tag names, separated by "|"
	 *
	 * @return string
	 */
	public static function closeSelfClosing($html, $names)
	{
		$content = '(?:[^>=\/]++|' . self::QUOTED_VALUE_PATTERN . ')*+';

		return self::replaceOutsideTags($html, '(' . self::namedTagStart($names) . ')(?:\/>|(' . $content . ')>)', '\\1\\2 />', 'i');
	}

	/**
	 * The "<" and name of a start tag of the given names. The name must end where the tag's content starts, so
	 * <svg-icon> is not an <svg> and <brand> is not a <br>
	 *
	 * @param string $names The tag names, separated by "|"
	 *
	 * @return string The pattern, without delimiters
	 */
	private static function namedTagStart($names)
	{
		return '<(?:' . $names . ')(?=[\s\/>])';
	}

}
