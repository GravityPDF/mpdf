<?php

namespace Mpdf\Css;

/**
 * Matches compiled selectors right to left against the open elements, as Mpdf::getStyledElementPath() gives them.
 *
 * An element is found by its parent's depth on the path and its index among that parent's children. The open
 * element at a depth is the child after the ones its parent has closed so far, and those closed children are its
 * siblings before it. The frame at depth 0 stands for the document and is matched as body, the root: it has no
 * parent and no siblings.
 */
class SelectorMatcher
{

	/** The selector matches */
	const MATCHES = 0;

	/** It fails for this element, and may match another */
	const FAILS_HERE = 1;

	/** It fails for this element and for any other whose ancestors are all among this one's */
	const FAILS_FOR_ANCESTORS = 2;

	/**
	 * @var bool Whether a pseudo-class that needs what follows an element was matched against one for which that is
	 *           not known, and so did not match. :not() then does not match either
	 */
	private $undecided = false;

	/**
	 * Whether a selector matches the last element of a path
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 * @param array[] $path The open elements from the document down to the element
	 *
	 * @return bool
	 */
	public function matches(array $selector, array $path)
	{
		$depth = count($path) - 1;

		return $this->matchesFrom($selector, count($selector['compounds']) - 1, $path, $depth - 1, $path[$depth]['nthChild'] - 1) === self::MATCHES;
	}

	/**
	 * Whether a compound matches an element, and the compounds to its left match the elements its combinators lead to.
	 * A descendant combinator tries each ancestor in turn, nearest first, and a general sibling combinator each earlier
	 * sibling, from the first, so a compound further left that fails on one is tried again on the next.
	 *
	 * A failure says how far it reaches, so that the tries stop once none left can match: when the compounds to the
	 * left of a descendant combinator have failed at every ancestor, they fail at every ancestor of those ancestors
	 * too, and at their siblings. Without this, a chain of descendant combinators is tried along every combination of
	 * ancestors on a deep path.
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 * @param int $compound Which of its compounds to match, from the left
	 * @param array[] $path The open elements from the document down to the element being styled
	 * @param int $parent The depth on $path of the element's parent, or -1 for the document itself
	 * @param int $index The element's index among its parent's children, from 0. At the number of children the parent
	 *                   has closed, it is the open element at the next depth
	 *
	 * @return int self::MATCHES, or how far the failure reaches: self::FAILS_HERE or self::FAILS_FOR_ANCESTORS
	 */
	private function matchesFrom(array $selector, $compound, array $path, $parent, $index)
	{
		if (!$this->matchesCompound($selector['compounds'][$compound], $path, $parent, $index)) {
			return self::FAILS_HERE;
		}

		if ($compound === 0) {
			return self::MATCHES;
		}

		$compound--;
		switch ($selector['combinators'][$compound]) {
			case '>':
				if ($parent < 0) {
					return self::FAILS_FOR_ANCESTORS;
				}

				return $this->matchesFrom($selector, $compound, $path, $parent - 1, $path[$parent]['nthChild'] - 1);

			case ' ':
				for ($depth = $parent; $depth >= 0; $depth--) {
					$result = $this->matchesFrom($selector, $compound, $path, $depth - 1, $path[$depth]['nthChild'] - 1);
					if ($result !== self::FAILS_HERE) {
						return $result;
					}
				}

				return self::FAILS_FOR_ANCESTORS;

			case '+':
				if ($parent < 0 || $index === 0) {
					return self::FAILS_HERE;
				}

				return $this->matchesFrom($selector, $compound, $path, $parent, $index - 1);

			default:
				// None of the siblings before it has the tag, if the parent has had no child with it
				$tag = $selector['compounds'][$compound]['tag'];
				if ($parent < 0 || ($tag !== null && !isset($path[$parent]['childTypes'][$tag]))) {
					return self::FAILS_HERE;
				}

				for ($sibling = 0; $sibling < $index; $sibling++) {
					$result = $this->matchesFrom($selector, $compound, $path, $parent, $sibling);
					if ($result !== self::FAILS_HERE) {
						return $result;
					}
				}

				return self::FAILS_HERE;
		}
	}

	/**
	 * Whether one compound selector matches an element: its tag, each of its ids and classes, each of its attribute
	 * selectors, and each of its pseudo-classes
	 *
	 * @param array $compound One of a compiled selector's compounds, as SelectorCompiler describes them
	 * @param array[] $path As matchesFrom() takes it
	 * @param int $parent The depth of the element's parent on $path, as matchesFrom() takes it
	 * @param int $index The element's index among its parent's children, as matchesFrom() takes it
	 *
	 * @return bool
	 */
	private function matchesCompound(array $compound, array $path, $parent, $index)
	{
		$element = $this->element($path, $parent, $index);

		if ($compound['tag'] !== null && $compound['tag'] !== $element['tag']) {
			return false;
		}

		foreach ($compound['ids'] as $id) {
			if ($id !== $element['id']) {
				return false;
			}
		}

		foreach ($compound['classes'] as $class) {
			if (!in_array($class, $element['classes'], true)) {
				return false;
			}
		}

		foreach ($compound['attributes'] as $attribute) {
			if (!$this->matchesAttribute($attribute, $element['attr'])) {
				return false;
			}
		}

		foreach ($compound['pseudos'] as $pseudo) {
			if (!$this->matchesPseudoClass($pseudo, $element, $path, $parent, $index)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a pseudo-class matches an element. :nth-child() counts the element among all its element siblings, and
	 * :nth-of-type() among those with its tag. :nth-last-child(), :nth-last-of-type(), :only-child and :only-of-type
	 * count from the parent's last child, and :empty reads whether the element holds anything, as
	 * TracksOpenElements::readAhead() found; where it could not, they do not match. The document has no siblings, so
	 * it matches none of these. :lang() matches the language the element has or inherits. :is() matches when any
	 * selector in its list matches from the element, combinators and all, and :not() when none does and none was left
	 * undecided
	 *
	 * @param array $pseudo A compiled pseudo-class, as SelectorCompiler describes them
	 * @param array $element The element's frame or record, as element() gives it
	 * @param array[] $path As matchesFrom() takes it
	 * @param int $parent The depth of the element's parent on $path, as matchesFrom() takes it
	 * @param int $index The element's index among its parent's children, as matchesFrom() takes it
	 *
	 * @return bool
	 */
	private function matchesPseudoClass(array $pseudo, array $element, array $path, $parent, $index)
	{
		if ($pseudo[0] === 'is' || $pseudo[0] === 'not') {
			$outer = $this->undecided;
			$this->undecided = false;
			$matched = false;
			foreach ($pseudo[1] as $selector) {
				if ($this->matchesFrom($selector, count($selector['compounds']) - 1, $path, $parent, $index) === self::MATCHES) {
					$matched = true;
					break;
				}
			}

			// :not() of what could not be decided cannot be either
			$undecided = !$matched && $this->undecided;
			$this->undecided = $outer || $undecided;

			return $pseudo[0] === 'is' ? $matched : !$matched && !$undecided;
		}

		if ($pseudo[0] === 'lang') {
			// A closed sibling's record keeps no lang: it has its own, or its parent's
			if (isset($element['lang'])) {
				$lang = $element['lang'];
			} else {
				$lang = isset($element['attr']['LANG']) ? $element['attr']['LANG'] : $path[$parent]['lang'];
			}

			return $this->matchesLanguage($pseudo[1], strtolower($lang));
		}

		if ($parent < 0) {
			return false;
		}

		if ($pseudo[0] === 'nth-child') {
			return $this->isNth($pseudo[1], $pseudo[2], $index + 1);
		}

		if ($pseudo[0] === 'nth-of-type') {
			return $this->isNth($pseudo[1], $pseudo[2], $element['nthOfType']);
		}

		// The rest need what follows the element, which is not always known
		$siblings = $path[$parent]['childTotal'];
		$ofType = isset($path[$parent]['childTypeTotals'][$element['tag']]) ? $path[$parent]['childTypeTotals'][$element['tag']] : null;
		if (($pseudo[0] === 'empty' ? $element['empty'] : $ofType) === null) {
			$this->undecided = true;

			return false;
		}

		switch ($pseudo[0]) {
			case 'nth-last-child':
				return $this->isNth($pseudo[1], $pseudo[2], $siblings - $index);
			case 'nth-last-of-type':
				return $this->isNth($pseudo[1], $pseudo[2], $ofType - $element['nthOfType'] + 1);
			case 'only-child':
				return $siblings === 1;
			case 'only-of-type':
				return $ofType === 1;
			default:
				return $element['empty'];
		}
	}

	/**
	 * Whether an attribute selector matches an element's attributes. A link's or image's path is compared as written,
	 * before the base path is put in front of it, and a value's entities are decoded before it is compared
	 *
	 * @param array $attribute A compiled attribute selector: [name, operator, value, case-insensitive]
	 * @param string[] $attr The element's attributes, as the tag handlers are given them
	 *
	 * @return bool
	 */
	private function matchesAttribute(array $attribute, array $attr)
	{
		list($name, $operator, $expected, $caseInsensitive) = $attribute;
		if (!isset($attr[$name])) {
			return false;
		}

		if ($operator === '') {
			return true;
		}

		// The tokenizer resolves a link or image path against the base path, and keeps the path as written apart
		$value = ($name === 'HREF' || $name === 'SRC') && isset($attr['ORIG_SRC']) ? $attr['ORIG_SRC'] : $attr[$name];
		if (strpos($value, '&') !== false) {
			$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
		}

		if ($caseInsensitive) {
			$value = strtolower($value);
		}

		switch ($operator) {
			case '=':
				return $value === $expected;
			case '~=':
				return strpos($value, $expected) !== false
					&& in_array($expected, preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY), true);
			case '|=':
				return $value === $expected || strpos($value, $expected . '-') === 0;
			case '^=':
				return $expected !== '' && strpos($value, $expected) === 0;
			case '$=':
				return $expected !== '' && substr($value, -strlen($expected)) === $expected;
			default:
				return $expected !== '' && strpos($value, $expected) !== false;
		}
	}

	/**
	 * Whether a language is one of the ranges :lang() names, or a subtag of one, so that fr matches fr-CA
	 *
	 * @param string[] $ranges The language ranges, lowercased
	 * @param string $lang The element's language, lowercased
	 *
	 * @return bool
	 */
	private function matchesLanguage(array $ranges, $lang)
	{
		foreach ($ranges as $range) {
			if ($lang === $range || strpos($lang, $range . '-') === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The an+b test behind :nth-child() and :nth-of-type(): whether a position is a*n+b for some n from 0 up
	 *
	 * @param int $a The step, which may be 0 or negative
	 * @param int $b The offset
	 * @param int $position The element's position among the siblings counted, from 1
	 *
	 * @return bool
	 */
	private function isNth($a, $b, $position)
	{
		if ($a === 0) {
			return $position === $b;
		}

		return ($position - $b) % $a === 0 && ($position - $b) / $a >= 0;
	}

	/**
	 * An element: an open element's frame, a closed sibling's record, or body for the document
	 *
	 * @param array[] $path As matchesFrom() takes it
	 * @param int $parent The depth of the element's parent on $path, as matchesFrom() takes it
	 * @param int $index The element's index among its parent's children, as matchesFrom() takes it
	 *
	 * @return array Its tag, id, classes, attr and nthOfType, for an element its empty, and for an open element or
	 *               the document its lang
	 */
	private function element(array $path, $parent, $index)
	{
		if ($parent < 0) {
			return ['tag' => 'BODY', 'id' => '', 'classes' => [], 'attr' => [], 'lang' => $path[0]['lang']];
		}

		if ($index === count($path[$parent]['children'])) {
			return $path[$parent + 1];
		}

		return $path[$parent]['children'][$index];
	}
}
