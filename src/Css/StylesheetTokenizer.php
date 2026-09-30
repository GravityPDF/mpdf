<?php

namespace Mpdf\Css;

/**
 * Splits a stylesheet into its rules, and a block into its declarations, reading the text as CSS Syntax Level 3
 * tokenizes it.
 *
 * A brace, semicolon or comment marker inside a quoted string or an unquoted url(), or escaped with a backslash, is
 * part of the text around it. A string left open ends at the line break, and a comment or block left open ends with
 * the stylesheet. Parentheses and square brackets are not matched, so a brace or semicolon inside one still ends the
 * block or declaration: one left open does not take the rest of the stylesheet with it.
 */
class StylesheetTokenizer
{

	/**
	 * A quoted string, to its closing quote or to the line break or end that leaves it open. A backslash escapes the
	 * next character, a line break included.
	 */
	const STRING = '"(?:[^"\\\\\n\r\f]++|\\\\(?:\r\n|[\s\S]))*+"?|\'(?:[^\'\\\\\n\r\f]++|\\\\(?:\r\n|[\s\S]))*+\'?';

	/**
	 * An unquoted url(), to its closing parenthesis or the end. A quoted one is read as a string.
	 */
	const URL = '(?<![\w\x80-\xff\\\\-])url\(\s*+(?![\'"])(?:[^)\\\\]++|\\\\[\s\S])*+\)?';

	/**
	 * A backslash and the character it escapes
	 */
	const ESCAPE = '\\\\[\s\S]';

	/**
	 * A comment, to its end or to the end of the stylesheet
	 */
	const COMMENT = '\/\*(?:[^*]++|\*(?!\/))*+(?:\*\/)?';

	/**
	 * A string, url() or escape, or a comment or HTML comment marker, which is captured
	 */
	const COMMENTS = '/' . self::STRING . '|' . self::URL . '|' . self::ESCAPE . '|(' . self::COMMENT . '|<!--|-->)/i';

	/**
	 * A brace or semicolon outside strings, url() and escapes
	 */
	const STRUCTURE = '/(?:' . self::STRING . '|' . self::URL . '|' . self::ESCAPE . ')(*SKIP)(*FAIL)|[{};]/i';

	/**
	 * Replaces each comment in a stylesheet with a space, and each <!-- and --> an HTML comment around it leaves,
	 * leaving those inside strings and url() alone
	 *
	 * @param string $css
	 *
	 * @return string
	 */
	public function removeComments($css)
	{
		if (strpos($css, '/*') === false && strpos($css, '<!--') === false && strpos($css, '-->') === false) {
			return $css;
		}

		return preg_replace_callback(
			self::COMMENTS,
			function ($m) {
				return isset($m[1]) ? ' ' : $m[0];
			},
			$css
		);
	}

	/**
	 * The rules in a list of rules: a stylesheet, or the block of an at-rule that holds rules, such as @media
	 *
	 * A qualified rule's prelude runs to the brace that opens its block, taking in any semicolon or unmatched closing
	 * brace before it, so a stray one spoils only the selector after it. An at-rule ends at a semicolon, at the end of
	 * its block, or at the end of the list. A byte order mark at the start is left out.
	 *
	 * @param string $css With its comments removed
	 *
	 * @return array[] Each [the at-rule's name in lower case, without the @, or null for a qualified rule; the
	 *                 selector list of a qualified rule as written, or what follows an at-rule's name, trimmed; the
	 *                 text inside the block, or null for an at-rule without one]
	 */
	public function rules($css)
	{
		$rules = [];
		$depth = 0;
		$open = 0;

		// A byte order mark, as a stylesheet read from a file may start with, is not part of the first rule
		$start = preg_match('/^\s*+\xEF\xBB\xBF/', $css, $bom) ? strlen($bom[0]) : 0;

		foreach ($this->structure($css) as $token) {
			list($char, $offset) = $token;

			if ($char === '{') {
				if ($depth++ === 0) {
					$open = $offset;
				}
			} elseif ($char === '}') {
				if ($depth > 0 && --$depth === 0) {
					$rules[] = $this->rule(substr($css, $start, $open - $start), (string) substr($css, $open + 1, $offset - $open - 1));
					$start = $offset + 1;
				}
			} elseif ($depth === 0 && $this->startsAtRule($css, $start)) {
				$rules[] = $this->rule(substr($css, $start, $offset - $start), null);
				$start = $offset + 1;
			}
		}

		if ($depth > 0) {
			$rules[] = $this->rule(substr($css, $start, $open - $start), (string) substr($css, $open + 1));
		} elseif ($start < strlen($css) && $this->startsAtRule($css, $start)) {
			$rules[] = $this->rule(substr($css, $start), null);
		}

		return $rules;
	}

	/**
	 * The declarations in a block, as property name and value split at the first colon
	 *
	 * A declaration ends at a semicolon, or at the end of the block. An at-rule in the block, such as a margin box in
	 * an @page rule, and a rule nested in it, end at the close of their own block and are left out, as is anything
	 * else with a block in it, and any part without a colon.
	 *
	 * @param string $block With its comments removed
	 *
	 * @return string[][] Each [name, value], as written
	 */
	public function declarations($block)
	{
		$declarations = [];

		// With no string, url(), escape or block in it, every semicolon ends a declaration
		if (strpbrk($block, "\"'\\{}") === false && stripos($block, 'url(') === false) {
			foreach (explode(';', $block) as $text) {
				$this->addDeclaration($declarations, $text);
			}

			return $declarations;
		}

		$start = 0;
		$depth = 0;

		foreach ($this->structure($block) as $token) {
			list($char, $offset) = $token;

			if ($char === '{') {
				$depth++;
			} elseif ($char === '}') {
				if ($depth > 0 && --$depth === 0) {
					$start = $offset + 1;
				}
			} elseif ($depth === 0) {
				$this->addDeclaration($declarations, substr($block, $start, $offset - $start));
				$start = $offset + 1;
			}
		}

		if ($depth === 0) {
			$this->addDeclaration($declarations, (string) substr($block, $start));
		}

		return $declarations;
	}

	/**
	 * Each brace and semicolon outside strings, unquoted url() and escapes
	 *
	 * @param string $css
	 *
	 * @return array[] Each [the character, its offset]
	 */
	private function structure($css)
	{
		preg_match_all(self::STRUCTURE, $css, $matches, PREG_OFFSET_CAPTURE);

		return $matches[0];
	}

	/**
	 * Whether the text from $start, after any whitespace, is an at-rule
	 *
	 * @param string $css
	 * @param int $start
	 *
	 * @return bool
	 */
	private function startsAtRule($css, $start)
	{
		$start += strspn($css, " \t\n\r\f", $start);

		return isset($css[$start]) && $css[$start] === '@';
	}

	/**
	 * @param string $prelude Everything before the block or the semicolon
	 * @param string|null $block
	 *
	 * @return array A rule, as rules() describes
	 */
	private function rule($prelude, $block)
	{
		if (preg_match('/^[ \t\n\r\f]*+@([-\w]*)(.*)$/s', $prelude, $m)) {
			return [strtolower($m[1]), trim($m[2]), $block];
		}

		return [null, $prelude, $block];
	}

	/**
	 * Adds a part of a block to its declarations, if it is one
	 *
	 * @param string[][] $declarations
	 * @param string $text
	 */
	private function addDeclaration(array &$declarations, $text)
	{
		$colon = strpos($text, ':');
		if ($colon !== false && !$this->startsAtRule($text, 0)) {
			$declarations[] = [substr($text, 0, $colon), (string) substr($text, $colon + 1)];
		}
	}
}
