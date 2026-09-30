<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;

class SelectorParser
{
	/**
	 * A class or id name, without the . or #. Stylesheets are uppercased before they are parsed, so the lowercase
	 * letters are only for callers that do not.
	 */
	const NAME = '[A-Za-z0-9_\-\x80-\xFF]+';

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Parse @PAGE selector.
	 *
	 * @param array $tags Selector tags array
	 * @return string Matches tag
	 */
	public function parsePageSelector($tags)
	{
		$level = count($tags);
		$t = '';
		$t2 = '';
		$t3 = '';

		if (isset($tags[0])) {
			$t = trim($tags[0]);
		}

		if (isset($tags[1])) {
			$t2 = trim($tags[1]);
		}

		if (isset($tags[2])) {
			$t3 = trim($tags[2]);
		}

		$tag = '';
		if ($level === 1) {
			$tag = $t;
		} elseif ($level === 2 && preg_match('/^[:](.*)$/', $t2, $m)) {
			$tag = $t . '>>PSEUDO>>' . $m[1];
			if ($m[1] === 'LEFT' || $m[1] === 'RIGHT') {
				$this->mpdf->mirrorMargins = true;
			}
		} elseif ($level === 2) {
			$tag = $t . '>>NAMED>>' . $t2;
		} elseif ($level === 3 && preg_match('/^[:](.*)$/', $t3, $m)) {
			$tag = $t . '>>NAMED>>' . $t2 . '>>PSEUDO>>' . $m[1];
			if ($m[1] === 'LEFT' || $m[1] === 'RIGHT') {
				$this->mpdf->mirrorMargins = true;
			}
		}

		return $tag;
	}

	/**
	 * Parse simple selector (depth 1).
	 *
	 * @param array $tags Selector tags array
	 * @return string|null Parsed tag key or null if invalid
	 */
	public function parseSimpleSelector($tags)
	{
		$t = isset($tags[0]) ? trim($tags[0]) : '';
		if (empty($t)) {
			return null;
		}

		$tag = '';
		if (preg_match('/^(' . $this->mpdf->allowedCSStags . ')?((?:[.#]' . self::NAME . ')+)$/', $t, $m)) {
			$tag = $this->compoundKey($m[1], $m[2]);
		} elseif (preg_match('/^\[LANG=[\'\"]{0,1}([A-Z\-]{2,11})[\'\"]{0,1}\]$/', $t, $m)) {
			$tag = 'LANG>>' . strtolower($m[1]);
		} elseif (preg_match('/^:LANG\([\'\"]{0,1}([A-Z\-]{2,11})[\'\"]{0,1}\)$/', $t, $m)) { // mPDF 6  Special case for lang as attribute selector
			$tag = 'LANG>>' . strtolower($m[1]);
		} elseif (preg_match('/^(' . $this->mpdf->allowedCSStags . ')\s*:NTH-CHILD\(([\-+]?\d*N(?:[\-+]\d+)?|[\-+]?\d+|ODD|EVEN)\)$/', $t, $m)) { // the whole argument is a formula, with nothing after it
			$tag = $m[1] . '>>SELECTORNTHCHILD>>' . $m[2];
		} elseif (preg_match('/^(' . $this->mpdf->allowedCSStags . ')\[LANG=[\'\"]{0,1}([A-Z\-]{2,11})[\'\"]{0,1}\]$/', $t, $m)) {
			$tag = $m[1] . '>>LANG>>' . strtolower($m[2]);
		} elseif (preg_match('/^(' . $this->mpdf->allowedCSStags . '):LANG\([\'\"]{0,1}([A-Z\-]{2,11})[\'\"]{0,1}\)$/', $t, $m)) {  // mPDF 6  Special case for lang as attribute selector
			$tag = $m[1] . '>>LANG>>' . strtolower($m[2]);
		} elseif (preg_match('/^(' . $this->mpdf->allowedCSStags . ')$/', $t)) { // mPDF 6  Special case for lang as attribute selector
			$tag = $t;
		}

		return $tag ?: null;
	}

	/**
	 * The key for a tag, id and classes written as one part, such as P#I.C or .B.A: the tag, then the id, then the
	 * classes in alphabetical order, so P.C#I and P#I.C give the same key, P>>ID>>I>>CLASS>>C
	 *
	 * @param string $tag The tag, or an empty string
	 * @param string $names The id and classes, each with its # or .
	 * @return string|null Null for two different ids, which no element has
	 */
	private function compoundKey($tag, $names)
	{
		preg_match_all('/([.#])(' . self::NAME . ')/', $names, $parts, PREG_SET_ORDER);

		$id = null;
		$classes = [];
		foreach ($parts as $part) {
			if ($part[1] === '.') {
				$classes[] = $part[2];
			} elseif ($id !== null && $id !== $part[2]) {
				return null;
			} else {
				$id = $part[2];
			}
		}

		$key = $tag === '' ? [] : [$tag];
		if ($id !== null) {
			$key[] = 'ID>>' . $id;
		}

		if ($classes) {
			$classes = array_unique($classes);
			sort($classes);
			$key[] = 'CLASS>>' . implode('.', $classes);
		}

		return implode('>>', $key);
	}

	/**
	 * Parse cascaded selector (depth > 1).
	 *
	 * A selector with a part that cannot be parsed matches nothing, so it gives no levels at all rather than
	 * the ones before that part, which would name a different element.
	 *
	 * @param array $tags Selector tags array
	 * @return array Array of tag levels for cascade, empty if any part cannot be parsed
	 */
	public function parseCascadedSelector($tags)
	{
		$levels = [];
		foreach ($tags as $t) {
			$tag = $this->parseSimpleSelector([$t]);
			if ($tag === null) {
				return [];
			}

			$levels[] = $tag;
		}

		return $levels;
	}

	/**
	 * Evaluate nth-child CSS selector.
	 *
	 * Determines if a given element index matches an nth-child selector formula.
	 * Supports formulas like "2n+1", "odd", "even", or specific numbers.
	 *
	 * @param array $nthComponents Formula components from preg_match (e.g. 2N+1 split into a preg_match array))
	 * @param int $index Current element index (e.g row or column number)
	 * @return bool True if element matches the nth-child selector
	 */
	public function matchesNthChild($nthComponents, $index)
	{
		++$index;
		$select = false;

		$numOfComponents = count($nthComponents);
		if ($nthComponents[0] === 'ODD') {
			$a = 2;
			$b = 1;
		} elseif ($nthComponents[0] === 'EVEN') {
			$a = 2;
			$b = 0;
		} elseif ($numOfComponents === 2) {
			$a = 0;
			$b = $nthComponents[1] + 0;
		} // e.g. (+6)
		elseif ($numOfComponents === 3) {  // e.g. (2N)
			if ($nthComponents[2] === '') {
				$a = 1;
			} elseif ($nthComponents[2] === '-') {
				$a = -1;
			} else {
				$a = $nthComponents[2] + 0;
			}
			$b = 0;
		} elseif ($numOfComponents === 4) {  // e.g. (2N+6)
			if ($nthComponents[2] === '') {
				$a = 1;
			} elseif ($nthComponents[2] === '-') {
				$a = -1;
			} else {
				$a = $nthComponents[2] + 0;
			}
			$b = $nthComponents[3] + 0;
		} else {
			return false;
		}

		if ($a > 0) {
			if (((($index % $a) - $b) % $a) === 0 && $index >= $b) {
				$select = true;
			}
		} elseif ($a === 0) {
			if ($index === $b) {
				$select = true;
			}
		} else {  // if ($a<0)
			if (((($index % $a) - $b) % $a) === 0 && $index <= $b) {
				$select = true;
			}
		}

		return $select;
	}
}
