<?php

namespace Mpdf\Css;

/**
 * Compiled stylesheet rules, filed under the rightmost compound of their selector as browsers file them: by its id,
 * else its first class, else its tag, else with the rules any element may match. An element then only has to be
 * matched against the rules filed under its own id, classes and tag, and those.
 */
class RuleSet
{

	/**
	 * @var array[] Each rule, by its position in the stylesheets read: [compiled selector, declarations]
	 */
	private $rules = [];

	/**
	 * @var array The positions of the rules filed under each id, class and tag, and those of the rules whose rightmost
	 *            compound names none: ['id' => [id => positions], 'class' => [...], 'tag' => [...], 'any' => positions]
	 */
	private $index = ['id' => [], 'class' => [], 'tag' => [], 'any' => []];

	/**
	 * Adds a rule after those already read
	 *
	 * @param array $selector A selector SelectorCompiler::compile() compiled
	 * @param array $declarations Its normalised declarations
	 */
	public function add(array $selector, array $declarations)
	{
		$position = count($this->rules);
		$this->rules[] = [$selector, $declarations];

		self::file($this->index, $selector['compounds'][count($selector['compounds']) - 1], $position);
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
	 * @param int $position
	 *
	 * @return array The rule at a position, as it is kept: [compiled selector, declarations]
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
}
