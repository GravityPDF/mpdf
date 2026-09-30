<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\Utils\UtfString;

/**
 * Reads selectors as they are written in a stylesheet, before anything is uppercased, into the parts a matcher works
 * from.
 *
 * A compiled selector is an array of:
 * - compounds: its compound selectors, left to right. Each holds its tag (null for none or *), its ids, its classes,
 *   its attribute selectors and its pseudo-classes. Tags, ids and classes are uppercased, as the tokenizer in
 *   Mpdf::WriteHTML() leaves an element's tag, id and classes.
 * - combinators: the combinator after each compound but the last, one of ' ', '>', '+' and '~'
 * - specificity: [ids, classes and attributes and pseudo-classes, tags]
 * - universal: whether it names the universal selector *
 *
 * Attribute selectors are held as [name, operator, value, case-insensitive]: the name uppercased, as the tokenizer
 * names attributes, and the operator empty for [name]. Values are compared case-insensitively, and held lowercased,
 * for the attributes HTML lists as such, for id and class, whose values the tokenizer uppercases, and with the i flag.
 *
 * Pseudo-classes are held as [name, arguments...]: ['nth-child', a, b], ['nth-of-type', a, b], ['nth-last-child', a, b]
 * and ['nth-last-of-type', a, b] for an+b, which :first-child, :first-of-type, :last-child and :last-of-type are
 * written as, ['only-child'], ['only-of-type'] and ['empty'], ['lang', ranges] with each language range lowercased,
 * and ['not', selectors] and ['is', selectors] with each selector compiled. :where() is held as :is(), its
 * specificity being the only difference.
 */
class SelectorCompiler
{

	/**
	 * @var array<string, true> The attributes whose values HTML matches case-insensitively in selectors, and id and
	 *                          class, whose values the tokenizer uppercases
	 */
	private static $caseInsensitiveAttributes = [
		'ACCEPT' => true, 'ACCEPT-CHARSET' => true, 'ALIGN' => true, 'ALINK' => true, 'AXIS' => true, 'BGCOLOR' => true,
		'CHARSET' => true, 'CHECKED' => true, 'CLASS' => true, 'CLEAR' => true, 'CODETYPE' => true, 'COLOR' => true,
		'COMPACT' => true, 'DECLARE' => true, 'DEFER' => true, 'DIR' => true, 'DIRECTION' => true, 'DISABLED' => true,
		'ENCTYPE' => true, 'FACE' => true, 'FRAME' => true, 'HREFLANG' => true, 'HTTP-EQUIV' => true, 'ID' => true,
		'LANG' => true, 'LANGUAGE' => true, 'LINK' => true, 'MEDIA' => true, 'METHOD' => true, 'MULTIPLE' => true,
		'NOHREF' => true, 'NORESIZE' => true, 'NOSHADE' => true, 'NOWRAP' => true, 'READONLY' => true, 'REL' => true,
		'REV' => true, 'RULES' => true, 'SCOPE' => true, 'SCROLLING' => true, 'SELECTED' => true, 'SHAPE' => true,
		'TARGET' => true, 'TEXT' => true, 'TYPE' => true, 'VALIGN' => true, 'VALUETYPE' => true, 'VLINK' => true,
	];

	/**
	 * @var array[] The pseudo-classes written without an argument, compiled. Those that count from the first or last
	 *              sibling are held as the nth formula they stand for
	 */
	private static $pseudoClassesWithoutArguments = [
		'first-child' => ['nth-child', 0, 1],
		'first-of-type' => ['nth-of-type', 0, 1],
		'last-child' => ['nth-last-child', 0, 1],
		'last-of-type' => ['nth-last-of-type', 0, 1],
		'only-child' => ['only-child'],
		'only-of-type' => ['only-of-type'],
		'empty' => ['empty'],
	];

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var array<string, true> The tags a type selector may name, from Mpdf::$allowedCSStags
	 */
	private $allowedTags = [];

	/**
	 * @var string The value of Mpdf::$allowedCSStags that $allowedTags was read from
	 */
	private $allowedTagsSource;

	/**
	 * @param Mpdf $mpdf
	 */
	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Splits a selector list at its commas, leaving those inside brackets, parentheses and strings, as in
	 * :is(h1, h2) or [title="a,b"]
	 *
	 * @param string $list
	 *
	 * @return string[] Each selector, trimmed. One left empty, as between two commas, matches nothing
	 */
	public function splitList($list)
	{
		$selectors = [];
		$start = 0;
		while (($comma = $this->findOutsideBrackets($list, $start, ',')) !== null) {
			$selectors[] = trim(substr($list, $start, $comma - $start));
			$start = $comma + 1;
		}

		$selectors[] = trim(substr($list, $start));

		return $selectors;
	}

	/**
	 * @param string $selector One selector of a list, as written in the stylesheet
	 *
	 * @return array|null The compiled selector, as the class describes, or null for one that is not valid or uses
	 *                    something mPDF cannot match, which matches nothing
	 */
	public function compile($selector)
	{
		$selector = trim($selector);
		$pos = 0;
		$compiled = $this->parseComplex($selector, $pos);

		return $pos === strlen($selector) ? $compiled : null;
	}

	/**
	 * Reads compound selectors and the combinators between them, up to the end of the text or a comma or closing
	 * parenthesis outside them
	 *
	 * @param string $text
	 * @param int $pos Where to start, moved past what was read
	 *
	 * @return array|null
	 */
	private function parseComplex($text, &$pos)
	{
		$length = strlen($text);
		$compounds = [];
		$combinators = [];
		$specificity = [0, 0, 0];
		$universal = false;

		while (true) {
			$compound = $this->parseCompound($text, $pos, $specificity, $universal);
			if ($compound === null) {
				return null;
			}
			$compounds[] = $compound;

			$space = $this->skipWhitespace($text, $pos);
			if ($pos >= $length || $text[$pos] === ',' || $text[$pos] === ')') {
				break;
			}

			if (strpos('>+~', $text[$pos]) !== false) {
				$combinators[] = $text[$pos];
				$pos++;
				$this->skipWhitespace($text, $pos);
			} elseif ($space) {
				$combinators[] = ' ';
			} else {
				return null;
			}
		}

		return [
			'compounds' => $compounds,
			'combinators' => $combinators,
			'specificity' => $specificity,
			'universal' => $universal,
		];
	}

	/**
	 * Reads one compound selector: a tag or *, then any ids, classes, attribute selectors and pseudo-classes, with
	 * nothing between them
	 *
	 * @param string $text
	 * @param int $pos
	 * @param int[] $specificity Added to for what is read
	 * @param bool $universal Set when the compound names *
	 *
	 * @return array|null
	 */
	private function parseCompound($text, &$pos, array &$specificity, &$universal)
	{
		$length = strlen($text);
		$compound = ['tag' => null, 'ids' => [], 'classes' => [], 'attributes' => [], 'pseudos' => []];
		$start = $pos;

		if ($pos < $length && $text[$pos] === '*') {
			$universal = true;
			$pos++;
		} elseif ($this->startsIdentifier($text, $pos)) {
			$compound['tag'] = strtoupper($this->readName($text, $pos));
			if (!$this->isAllowedTag($compound['tag'])) {
				return null;
			}
			$specificity[2]++;
		}

		while ($pos < $length) {
			$c = $text[$pos];
			if ($c === '#') {
				$pos++;
				$name = $this->readName($text, $pos);
				if ($name === '') {
					return null;
				}
				$compound['ids'][] = strtoupper($name);
				$specificity[0]++;
			} elseif ($c === '.') {
				$pos++;
				if (!$this->startsIdentifier($text, $pos)) {
					return null;
				}
				$compound['classes'][] = strtoupper($this->readName($text, $pos));
				$specificity[1]++;
			} elseif ($c === '[') {
				$pos++;
				$attribute = $this->parseAttribute($text, $pos);
				if ($attribute === null) {
					return null;
				}
				$compound['attributes'][] = $attribute;
				$specificity[1]++;
			} elseif ($c === ':') {
				$pos++;
				$pseudo = $this->parsePseudoClass($text, $pos, $specificity, $universal);
				if ($pseudo === null) {
					return null;
				}
				$compound['pseudos'][] = $pseudo;
			} else {
				break;
			}
		}

		return $pos > $start ? $compound : null;
	}

	/**
	 * Reads a pseudo-class after its colon. :not() and :is() count as their most specific argument, :where() as
	 * nothing, and the others as a class
	 *
	 * @param string $text
	 * @param int $pos
	 * @param int[] $specificity Added to for the pseudo-class
	 * @param bool $universal Set when an argument names *
	 *
	 * @return array|null As the class describes, or null for a pseudo-element or a pseudo-class mPDF cannot match
	 */
	private function parsePseudoClass($text, &$pos, array &$specificity, &$universal)
	{
		if (!$this->startsIdentifier($text, $pos)) {
			return null;
		}

		$name = strtolower($this->readName($text, $pos));
		if ($name !== 'not' && $name !== 'is' && $name !== 'where') {
			$specificity[1]++;
		}

		if (isset(self::$pseudoClassesWithoutArguments[$name])) {
			return self::$pseudoClassesWithoutArguments[$name];
		}

		if (!$this->isAt($text, $pos, '(')) {
			return null;
		}

		$pos++;
		$argument = $this->readArgument($text, $pos);
		if ($argument === null) {
			return null;
		}

		if ($name === 'not' || $name === 'is' || $name === 'where') {
			return $this->parseSelectorArgument($name, $argument, $specificity, $universal);
		}

		if ($name === 'lang') {
			$ranges = $this->parseLanguageRanges($argument);

			return $ranges === null ? null : ['lang', $ranges];
		}

		if (!in_array($name, ['nth-child', 'nth-of-type', 'nth-last-child', 'nth-last-of-type'], true)) {
			return null;
		}

		$formula = $this->parseNth($argument);

		return $formula === null ? null : [$name, $formula[0], $formula[1]];
	}

	/**
	 * Reads the selector list :not(), :is() and :where() take. :is() and :where() leave out a selector they cannot
	 * read, as browsers do, and :not() cannot be read with one
	 *
	 * @param string $name not, is or where
	 * @param string $argument
	 * @param int[] $specificity Added to for the most specific selector, except for :where()
	 * @param bool $universal Set when a selector names *
	 *
	 * @return array|null
	 */
	private function parseSelectorArgument($name, $argument, array &$specificity, &$universal)
	{
		$selectors = [];
		$heaviest = [0, 0, 0];
		foreach ($this->splitList($argument) as $member) {
			$compiled = $this->compile($member);
			if ($compiled === null) {
				if ($name === 'not') {
					return null;
				}
				continue;
			}

			$selectors[] = $compiled;
			$universal = $universal || $compiled['universal'];
			if ($compiled['specificity'] > $heaviest) {
				$heaviest = $compiled['specificity'];
			}
		}

		if (!$selectors) {
			return null;
		}

		if ($name !== 'where') {
			foreach ($heaviest as $i => $count) {
				$specificity[$i] += $count;
			}
		}

		return [$name === 'not' ? 'not' : 'is', $selectors];
	}

	/**
	 * Reads the language ranges :lang() takes: identifiers or strings, separated by commas
	 *
	 * @param string $argument
	 *
	 * @return string[]|null The ranges, lowercased, or null for a list that is not valid
	 */
	private function parseLanguageRanges($argument)
	{
		$ranges = [];
		$pos = 0;
		$length = strlen($argument);
		while (true) {
			$this->skipWhitespace($argument, $pos);
			$range = $this->readValue($argument, $pos);
			if ($range === null || $range === '') {
				return null;
			}
			$ranges[] = strtolower($range);

			$this->skipWhitespace($argument, $pos);
			if ($pos >= $length) {
				return $ranges;
			}
			if ($argument[$pos] !== ',') {
				return null;
			}
			$pos++;
		}
	}

	/**
	 * Reads an attribute selector after its opening bracket
	 *
	 * @param string $text
	 * @param int $pos Moved past the closing bracket
	 *
	 * @return array|null As the class describes, or null for one that is not valid
	 */
	private function parseAttribute($text, &$pos)
	{
		$this->skipWhitespace($text, $pos);
		if (!$this->startsIdentifier($text, $pos)) {
			return null;
		}
		$name = strtoupper($this->readName($text, $pos));
		$this->skipWhitespace($text, $pos);

		if ($this->isAt($text, $pos, ']')) {
			$pos++;

			return [$name, '', '', false];
		}

		if (!preg_match('/\G([~|^$*]?=)/', $text, $m, 0, $pos)) {
			return null;
		}
		$operator = $m[1];
		$pos += strlen($operator);

		$this->skipWhitespace($text, $pos);
		$value = $this->readValue($text, $pos);
		if ($value === null) {
			return null;
		}

		$this->skipWhitespace($text, $pos);
		$caseInsensitive = isset(self::$caseInsensitiveAttributes[$name]);
		if (preg_match('/\G([is])(?![\w-])/i', $text, $m, 0, $pos)) {
			// The tokenizer uppercases id and class values, so those stay case-insensitive whatever the flag
			$caseInsensitive = $name === 'ID' || $name === 'CLASS' || strtolower($m[1]) === 'i';
			$pos++;
			$this->skipWhitespace($text, $pos);
		}

		if (!$this->isAt($text, $pos, ']')) {
			return null;
		}
		$pos++;

		return [$name, $operator, $caseInsensitive ? strtolower($value) : $value, $caseInsensitive];
	}

	/**
	 * Whether the selector text has a given character at a position, which may be past its end
	 *
	 * @param string $text
	 * @param int $pos
	 * @param string $character
	 *
	 * @return bool
	 */
	private function isAt($text, $pos, $character)
	{
		return $pos < strlen($text) && $text[$pos] === $character;
	}

	/**
	 * Reads an identifier or a quoted string, as an attribute selector's value is written
	 *
	 * @param string $text
	 * @param int $pos
	 *
	 * @return string|null The value, with its escapes read, or null for neither
	 */
	private function readValue($text, &$pos)
	{
		if ($pos >= strlen($text)) {
			return null;
		}

		$quote = $text[$pos];
		if ($quote !== '"' && $quote !== "'") {
			return $this->startsIdentifier($text, $pos) ? $this->readName($text, $pos) : null;
		}

		$value = '';
		$length = strlen($text);
		for ($i = $pos + 1; $i < $length; $i++) {
			$c = $text[$i];
			if ($c === $quote) {
				$pos = $i + 1;

				return $value;
			}
			if ($c !== '\\') {
				$value .= $c;
				continue;
			}

			if (preg_match('/\G\\\\(?:([0-9A-Fa-f]{1,6})[ \t\n\r\f]?|(\r\n|[\n\r\f])|(.))/s', $text, $m, 0, $i)) {
				if (isset($m[3])) {
					$value .= $m[3];
				} elseif ($m[1] !== '') {
					$value .= $this->codePoint(hexdec($m[1]));
				}
				$i += strlen($m[0]) - 1;
			}
		}

		return null;
	}

	/**
	 * Reads an an+b argument, as :nth-child() takes
	 *
	 * @param string $argument
	 *
	 * @return int[]|null [a, b], or null for one that is not valid
	 */
	public function parseNth($argument)
	{
		$argument = strtolower(trim($argument));
		if ($argument === 'odd') {
			return [2, 1];
		}
		if ($argument === 'even') {
			return [2, 0];
		}
		if (preg_match('/^[+-]?\d+$/', $argument)) {
			return [0, (int) $argument];
		}
		if (!preg_match('/^([+-]?)(\d*)n(?:\s*([+-])\s*(\d+))?$/', $argument, $m)) {
			return null;
		}

		$a = $m[2] === '' ? 1 : (int) $m[2];
		$b = isset($m[4]) ? (int) $m[4] : 0;

		return [$m[1] === '-' ? -$a : $a, isset($m[3]) && $m[3] === '-' ? -$b : $b];
	}

	/**
	 * Reads a function's argument, up to the parenthesis that closes it
	 *
	 * @param string $text
	 * @param int $pos Just after the opening parenthesis, moved past the closing one
	 *
	 * @return string|null Null when it is not closed
	 */
	private function readArgument($text, &$pos)
	{
		$end = $this->findOutsideBrackets($text, $pos, ')');
		if ($end === null) {
			return null;
		}

		$argument = substr($text, $pos, $end - $pos);
		$pos = $end + 1;

		return $argument;
	}

	/**
	 * Finds a character that is not inside brackets, parentheses or a string, nor escaped
	 *
	 * @param string $text
	 * @param int $start Where to look from
	 * @param string $character
	 *
	 * @return int|null Where it is, or null if it is not there
	 */
	private function findOutsideBrackets($text, $start, $character)
	{
		$depth = 0;
		$quote = null;
		$length = strlen($text);

		for ($i = $start; $i < $length; $i++) {
			$c = $text[$i];
			if ($c === '\\') {
				$i++;
			} elseif ($quote !== null) {
				if ($c === $quote) {
					$quote = null;
				}
			} elseif ($c === '"' || $c === "'") {
				$quote = $c;
			} elseif ($c === $character && $depth === 0) {
				return $i;
			} elseif ($c === '(' || $c === '[') {
				$depth++;
			} elseif (($c === ')' || $c === ']') && $depth > 0) {
				$depth--;
			}
		}

		return null;
	}

	/**
	 * Whether an identifier starts here: a letter, underscore, non-ASCII character or escape, after at most one
	 * hyphen, or two hyphens
	 *
	 * @param string $text
	 * @param int $pos
	 *
	 * @return bool
	 */
	private function startsIdentifier($text, $pos)
	{
		return (bool) preg_match('/\G(?:--|-?(?:[A-Za-z_\x80-\xFF]|\\\\[^\r\n\f]))/', $text, $m, 0, $pos);
	}

	/**
	 * Reads a name made of identifier characters and escapes
	 *
	 * @param string $text
	 * @param int $pos
	 *
	 * @return string The name with its escapes read, empty when there is none here
	 */
	private function readName($text, &$pos)
	{
		$name = '';
		$length = strlen($text);

		while ($pos < $length) {
			$c = $text[$pos];
			if (ctype_alnum($c) || $c === '-' || $c === '_' || ord($c) >= 0x80) {
				$name .= $c;
				$pos++;
			} elseif ($c === '\\' && preg_match('/\G\\\\(?:([0-9A-Fa-f]{1,6})[ \t\n\r\f]?|([^\r\n\f]))/', $text, $m, 0, $pos)) {
				$name .= isset($m[2]) ? $m[2] : $this->codePoint(hexdec($m[1]));
				$pos += strlen($m[0]);
			} else {
				break;
			}
		}

		return $name;
	}

	/**
	 * @param int $code
	 *
	 * @return string The character a hex escape stands for, as UTF-8. One that is not a character gives U+FFFD
	 */
	private function codePoint($code)
	{
		if ($code === 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
			$code = 0xFFFD;
		}

		return UtfString::code2utf($code);
	}

	/**
	 * @param string $text
	 * @param int $pos Moved past any white space
	 *
	 * @return bool Whether there was any
	 */
	private function skipWhitespace($text, &$pos)
	{
		$start = $pos;
		$length = strlen($text);
		while ($pos < $length && strpos(" \t\n\r\f", $text[$pos]) !== false) {
			$pos++;
		}

		return $pos > $start;
	}

	/**
	 * Whether a type selector may name a tag. Which elements take part in the cascade is decided per tag, by
	 * Mpdf::$allowedCSStags, as it is for the selectors the legacy parser reads
	 *
	 * @param string $tag Uppercased
	 *
	 * @return bool
	 */
	private function isAllowedTag($tag)
	{
		if ($this->allowedTagsSource !== $this->mpdf->allowedCSStags) {
			$this->allowedTagsSource = $this->mpdf->allowedCSStags;
			$this->allowedTags = array_fill_keys(explode('|', strtoupper($this->allowedTagsSource)), true);
		}

		return isset($this->allowedTags[$tag]);
	}
}
