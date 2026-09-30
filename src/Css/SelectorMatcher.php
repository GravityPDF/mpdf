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

	/**
	 * Whether a selector matches the last element of a path
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 * @param array[] $path The open elements from the document down to the element
	 * @param bool $legacyView Whether to match as the legacy engine does: a descendant combinator only looks at the
	 *                         ancestors that opened a level of the legacy descendant rules
	 *
	 * @return bool
	 */
	public function matches(array $selector, array $path, $legacyView = false)
	{
		$depth = count($path) - 1;

		return $this->matchesFrom($selector, count($selector['compounds']) - 1, $path, $depth - 1, $path[$depth]['nthChild'] - 1, $legacyView);
	}

	/**
	 * Whether a compound matches an element, and the compounds to its left match the elements its combinators lead to.
	 * A descendant or general sibling combinator tries each ancestor or earlier sibling in turn, nearest first, so a
	 * compound further left that fails on the nearest one is tried again on the next
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 * @param int $compound Which of its compounds to match, from the left
	 * @param array[] $path The open elements from the document down to the element being styled
	 * @param int $parent The depth on $path of the element's parent, or -1 for the document itself
	 * @param int $index The element's index among its parent's children, from 0. At the number of children the parent
	 *                   has closed, it is the open element at the next depth
	 * @param bool $legacyView Whether to match as the legacy engine does, as matches() takes it
	 *
	 * @return bool
	 */
	private function matchesFrom(array $selector, $compound, array $path, $parent, $index, $legacyView)
	{
		if (!$this->matchesCompound($selector['compounds'][$compound], $path, $parent, $index)) {
			return false;
		}

		if ($compound === 0) {
			return true;
		}

		$compound--;
		switch ($selector['combinators'][$compound]) {
			case '>':
				return $parent >= 0 && $this->matchesFrom($selector, $compound, $path, $parent - 1, $path[$parent]['nthChild'] - 1, $legacyView);

			case ' ':
				for ($depth = $parent; $depth >= 0; $depth--) {
					if ((!$legacyView || $path[$depth]['level'])
						&& $this->matchesFrom($selector, $compound, $path, $depth - 1, $path[$depth]['nthChild'] - 1, $legacyView)) {
						return true;
					}
				}

				return false;

			case '+':
				return $parent >= 0 && $index > 0 && $this->matchesFrom($selector, $compound, $path, $parent, $index - 1, $legacyView);

			default:
				for ($sibling = $index - 1; $parent >= 0 && $sibling >= 0; $sibling--) {
					if ($this->matchesFrom($selector, $compound, $path, $parent, $sibling, $legacyView)) {
						return true;
					}
				}

				return false;
		}
	}

	/**
	 * Whether one compound selector matches an element: its tag, each of its ids and classes, and each of its
	 * pseudo-classes
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

		foreach ($compound['pseudos'] as $pseudo) {
			if (!$this->matchesPseudoClass($pseudo, $element, $parent, $index)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a structural pseudo-class matches an element. :nth-child() counts the element among all its element
	 * siblings, and :nth-of-type() among those with its tag. The document has no siblings, so it matches neither
	 *
	 * @param array $pseudo A compiled pseudo-class: ['nth-child', a, b] or ['nth-of-type', a, b]
	 * @param array $element The element's frame or record, as element() gives it
	 * @param int $parent The depth of the element's parent on the path, as matchesFrom() takes it
	 * @param int $index The element's index among its parent's children, as matchesFrom() takes it
	 *
	 * @return bool
	 */
	private function matchesPseudoClass(array $pseudo, array $element, $parent, $index)
	{
		if ($parent < 0) {
			return false;
		}

		if ($pseudo[0] === 'nth-child') {
			return $this->isNth($pseudo[1], $pseudo[2], $index + 1);
		}

		return $this->isNth($pseudo[1], $pseudo[2], $element['nthOfType']);
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
	 * @return array Its tag, id, classes, attr and nthOfType
	 */
	private function element(array $path, $parent, $index)
	{
		if ($parent < 0) {
			return ['tag' => 'BODY', 'id' => '', 'classes' => [], 'attr' => []];
		}

		if ($index === count($path[$parent]['children'])) {
			return $path[$parent + 1];
		}

		return $path[$parent]['children'][$index];
	}
}
