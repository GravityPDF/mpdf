<?php

namespace Mpdf\Css;

/**
 * Compiled stylesheet rules, filed under the rightmost compound of their selector as browsers file them: by its id,
 * else its first class, else its tag, else with the rules any element may match. An element then only has to be
 * matched against the rules filed under its own id, classes and tag, and those.
 *
 * The descendant rules the legacy engine applies too are filed apart. They are only looked at for an element with an
 * ancestor that opened no level of the legacy descendant rules, such as an inline element, since the legacy engine
 * matches the rest. A rule naming :lang() is looked at for every element, as the legacy engine does not match an
 * inherited language.
 */
class RuleSet
{

	/**
	 * @var array[] Each rule, by its position in the stylesheets read: [compiled selector, declarations, the ancestors
	 *              it requires as requiredAncestors() gives them, whether the legacy engine applies it too]
	 */
	private $rules = [];

	/**
	 * @var array The positions of the rules filed under each id, class and tag, and those of the rules whose rightmost
	 *            compound names none: ['id' => [id => positions], 'class' => [...], 'tag' => [...], 'any' => positions]
	 */
	private $index = ['id' => [], 'class' => [], 'tag' => [], 'any' => []];

	/**
	 * @var array The descendant rules the legacy engine applies too, filed in the same way
	 */
	private $legacyIndex = ['id' => [], 'class' => [], 'tag' => [], 'any' => []];

	/**
	 * @var SelectorMatcher Matches each rule filed under an element against the open elements around it
	 */
	private $matcher;

	/**
	 * An empty rule set
	 */
	public function __construct()
	{
		$this->matcher = new SelectorMatcher();
	}

	/**
	 * Adds a rule after those already read
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 * @param array $declarations Its normalised declarations
	 * @param bool $legacy For a rule the legacy engine applies too: match it only where the legacy engine cannot,
	 *                     such as through an inline element or another ancestor that opens no level of its
	 *                     descendant rules
	 */
	public function add(array $selector, array $declarations, $legacy = false)
	{
		$position = count($this->rules);
		$this->rules[] = [$selector, $declarations, self::requiredAncestors($selector), $legacy];

		$subject = $selector['compounds'][count($selector['compounds']) - 1];
		if ($legacy && !self::namesLanguage($selector)) {
			self::file($this->legacyIndex, $subject, $position);
		} else {
			self::file($this->index, $subject, $position);
		}
	}

	/**
	 * The rules an element could match, going by the rightmost compound of each. The rest of each selector is still
	 * to be matched
	 *
	 * @param string $tag Uppercased
	 * @param string $id Uppercased, or empty for none
	 * @param string[] $classes Uppercased
	 *
	 * @return int[] Their positions, in no particular order
	 */
	public function candidates($tag, $id, array $classes)
	{
		return self::collect($this->index, $tag, $id, $classes);
	}

	/**
	 * The declarations of the rules an element matches, in the order they apply: by specificity, then by position
	 *
	 * @param string $tag Uppercased
	 * @param string $id Uppercased, or empty for none
	 * @param string[] $classes Uppercased
	 * @param callable $path Gives the open elements from the document down to the element, or null for none. Only
	 *                       called when a rule is filed under the element
	 *
	 * @return array[]
	 */
	public function matchingDeclarations($tag, $id, array $classes, callable $path)
	{
		$candidates = $this->candidates($tag, $id, $classes);
		$legacyCandidates = self::collect($this->legacyIndex, $tag, $id, $classes);
		if (!$candidates && !$legacyCandidates) {
			return [];
		}

		$path = call_user_func($path);
		if ($path === null || $path[count($path) - 1]['tag'] !== $tag) {
			return [];
		}

		if ($legacyCandidates && self::hasAncestorOpeningNoLevel($path)) {
			$candidates = array_merge($candidates, $legacyCandidates);
		}

		$ancestors = self::ancestorsOf($path);
		$matched = [];
		foreach ($candidates as $position) {
			list($selector, , $required, $legacy) = $this->rules[$position];
			foreach ($required as $key) {
				if (!isset($ancestors[$key])) {
					continue 2;
				}
			}

			if ($this->matcher->matches($selector, $path) && !($legacy && $this->matcher->matches($selector, $path, true))) {
				$matched[] = $position;
			}
		}

		$rules = $this->rules;
		usort($matched, function ($a, $b) use ($rules) {
			// Arrays of the same keys compare element by element: ids, then classes, then tags
			if ($rules[$a][0]['specificity'] != $rules[$b][0]['specificity']) {
				return $rules[$a][0]['specificity'] < $rules[$b][0]['specificity'] ? -1 : 1;
			}

			return $a - $b;
		});

		$declarations = [];
		foreach ($matched as $position) {
			$declarations[] = $this->rules[$position][1];
		}

		return $declarations;
	}

	/**
	 * @param int $position
	 *
	 * @return array The rule at a position, as it is kept: [compiled selector, declarations, required ancestors,
	 *               whether the legacy engine applies it too]
	 */
	public function rule($position)
	{
		return $this->rules[$position];
	}

	/**
	 * @param array $index
	 * @param array $subject The rightmost compound of a rule's selector
	 * @param int $position The rule's position
	 */
	private static function file(array &$index, array $subject, $position)
	{
		if ($subject['ids']) {
			$index['id'][$subject['ids'][0]][] = $position;
		} elseif ($subject['classes']) {
			$index['class'][$subject['classes'][0]][] = $position;
		} elseif ($subject['tag'] !== null) {
			$index['tag'][$subject['tag']][] = $position;
		} else {
			$index['any'][] = $position;
		}
	}

	/**
	 * @param array $index
	 * @param string $tag
	 * @param string $id
	 * @param string[] $classes
	 *
	 * @return int[] The positions of the rules an index files under an element's tag, id or classes, or for any element
	 */
	private static function collect(array $index, $tag, $id, array $classes)
	{
		$positions = $index['any'];
		if (isset($index['tag'][$tag])) {
			$positions = array_merge($positions, $index['tag'][$tag]);
		}

		if ($id !== '' && isset($index['id'][$id])) {
			$positions = array_merge($positions, $index['id'][$id]);
		}

		foreach (array_unique($classes) as $class) {
			if (isset($index['class'][$class])) {
				$positions = array_merge($positions, $index['class'][$class]);
			}
		}

		return $positions;
	}

	/**
	 * A key for each compound of a selector that an ancestor of the element has to match: the compound's first id,
	 * else its first class, else its tag. A compound followed by a descendant or child combinator names an ancestor,
	 * since an ancestor of a sibling is one too. A rule is only matched for an element whose ancestors give every key
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 *
	 * @return string[] Each key prefixed with I, C or T for an id, a class or a tag
	 */
	private static function requiredAncestors(array $selector)
	{
		$keys = [];
		foreach ($selector['combinators'] as $i => $combinator) {
			if ($combinator !== ' ' && $combinator !== '>') {
				continue;
			}

			$compound = $selector['compounds'][$i];
			if ($compound['ids']) {
				$keys[] = 'I' . $compound['ids'][0];
			} elseif ($compound['classes']) {
				$keys[] = 'C' . $compound['classes'][0];
			} elseif ($compound['tag'] !== null) {
				$keys[] = 'T' . $compound['tag'];
			}
		}

		return $keys;
	}

	/**
	 * The other half of the pre-filter requiredAncestors() sets up: the keys an element's ancestors give, so that a
	 * rule naming an ancestor the element does not have is passed over without running the matcher
	 *
	 * @param array[] $path The open elements from the document down to the element, with the element last
	 *
	 * @return array<string, true> The keys requiredAncestors() gives for the ids, classes and tags of the element's
	 *                             ancestors, the document's frame being body
	 */
	private static function ancestorsOf(array $path)
	{
		$keys = ['T' . 'BODY' => true]; // the document's frame
		for ($depth = count($path) - 2; $depth > 0; $depth--) {
			$keys['T' . $path[$depth]['tag']] = true;
			if ($path[$depth]['id'] !== '') {
				$keys['I' . $path[$depth]['id']] = true;
			}
			foreach ($path[$depth]['classes'] as $class) {
				$keys['C' . $class] = true;
			}
		}

		return $keys;
	}

	/**
	 * Whether an ancestor of the element opened no level of the legacy descendant rules, such as an inline element or
	 * a block inside a table cell. Only then can a descendant rule the legacy engine applies too match where the
	 * legacy engine does not, so the rules filed in the legacy index are only looked at for such an element
	 *
	 * @param array[] $path The open elements from the document down to the element, with the element last
	 *
	 * @return bool
	 */
	private static function hasAncestorOpeningNoLevel(array $path)
	{
		for ($depth = count($path) - 2; $depth > 0; $depth--) {
			if (!$path[$depth]['level']) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Only the compounds' own pseudo-classes are looked at: the legacy parser reads no :not() or :is(), so a legacy
	 * rule has no :lang() inside one
	 *
	 * @param array $selector
	 *
	 * @return bool Whether a compiled selector names :lang()
	 */
	private static function namesLanguage(array $selector)
	{
		foreach ($selector['compounds'] as $compound) {
			foreach ($compound['pseudos'] as $pseudo) {
				if ($pseudo[0] === 'lang') {
					return true;
				}
			}
		}

		return false;
	}
}
