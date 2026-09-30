<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Filing compiled rules under the rightmost compound of their selector, and finding the ones an element could match
 */
class RuleSetTest extends TestCase
{

	/**
	 * @var RuleSet
	 */
	private $rules;

	/**
	 * @var SelectorCompiler
	 */
	private $compiler;

	/**
	 * A rule set holding rules filed each way a rule is filed, with a declaration naming each one's position
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->compiler = new SelectorCompiler(new Mpdf());
		$this->rules = new RuleSet();

		$selectors = [
			'div > p', // under P
			'p.a#main', // under #MAIN
			'div + .b.c', // under .B
			':first-child', // for any element
			'p ~ span', // under SPAN
			'.a > p', // under P: only the rightmost compound counts
		];
		foreach ($selectors as $position => $selector) {
			$this->rules->add($this->compiler->compile($selector), ['COLOR' => 'rule ' . $position]);
		}
	}

	/**
	 * Lets go of the rule set and compiler
	 */
	protected function tear_down()
	{
		unset($this->rules, $this->compiler);

		parent::tear_down();
	}

	/**
	 * An element's candidates are the rules filed under its tag, its id, each of its classes, and those for any
	 * element
	 *
	 * @dataProvider elements
	 *
	 * @param string $tag
	 * @param string $id
	 * @param string[] $classes
	 * @param int[] $expected The positions of the candidate rules
	 */
	public function testFindsTheRulesFiledUnderAnElement($tag, $id, array $classes, array $expected)
	{
		$candidates = $this->rules->candidates($tag, $id, $classes);
		sort($candidates);

		$this->assertSame($expected, $candidates);
	}

	/**
	 * An element's tag, id and classes, and the rules it could match
	 *
	 * @return array[]
	 */
	public function elements()
	{
		return [
			'a paragraph' => ['P', '', [], [0, 3, 5]],
			'a paragraph with the id' => ['P', 'MAIN', [], [0, 1, 3, 5]],
			'a class the rule is filed under' => ['DIV', '', ['B'], [2, 3]],
			'only the other class of the rule' => ['DIV', '', ['C'], [3]],
			'a class written twice' => ['DIV', '', ['B', 'B'], [2, 3]],
			'a span' => ['SPAN', '', [], [3, 4]],
			'a tag no rule names' => ['LI', '', [], [3]],
		];
	}

	/**
	 * Rules keep the position they were added at, whatever they are filed under
	 */
	public function testKeepsEachRuleAtItsPosition()
	{
		$this->assertSame($this->compiler->compile('p ~ span'), $this->rules->rule(4)[0]);
		$this->assertSame(['COLOR' => 'rule 4'], $this->rules->rule(4)[1]);
	}

	/**
	 * An empty rule set has no candidates for any element
	 */
	public function testHasNoCandidatesUntilARuleIsAdded()
	{
		$rules = new RuleSet();
		$this->assertSame([], $rules->candidates('P', 'MAIN', ['A']));

		$rules->add($this->compiler->compile('div > p'), ['COLOR' => 'red']);
		$this->assertSame([0], $rules->candidates('P', 'MAIN', ['A']));
	}

	/**
	 * The declarations of the rules an element matches come in the order they apply: by specificity, and rules of
	 * the same specificity by position
	 */
	public function testGivesTheMatchingDeclarationsBySpecificityThenPosition()
	{
		$rules = new RuleSet();
		foreach (['.a > p', 'div > p', 'p:first-child', 'body > div > p', 'div > p', '#main > p', 'ul > p'] as $position => $selector) {
			$rules->add($this->compiler->compile($selector), ['COLOR' => 'rule ' . $position]);
		}

		$path = function () {
			return $this->path();
		};

		$this->assertSame(
			[['COLOR' => 'rule 1'], ['COLOR' => 'rule 4'], ['COLOR' => 'rule 3'], ['COLOR' => 'rule 0'], ['COLOR' => 'rule 2'], ['COLOR' => 'rule 5']],
			$rules->matchingDeclarations('P', '', [], $path)[0]
		);
	}

	/**
	 * The !important declarations of the rules an element matches come apart from the others, in the same order, and
	 * only from the rules that have any
	 */
	public function testGivesTheImportantDeclarationsApartInTheSameOrder()
	{
		$rules = new RuleSet();
		$rules->add($this->compiler->compile('#main > p'), ['COLOR' => 'id'], ['FONT-SIZE' => 'id']);
		$rules->add($this->compiler->compile('div > p'), ['COLOR' => 'first tag'], ['FONT-SIZE' => 'first tag']);
		$rules->add($this->compiler->compile('p:first-child'), ['COLOR' => 'pseudo-class']);
		$rules->add($this->compiler->compile('div > p'), [], ['FONT-SIZE' => 'second tag']);
		$rules->add($this->compiler->compile('ul > p'), ['COLOR' => 'no match'], ['FONT-SIZE' => 'no match']);

		$path = function () {
			return $this->path();
		};

		$this->assertSame(
			[
				[['COLOR' => 'first tag'], [], ['COLOR' => 'pseudo-class'], ['COLOR' => 'id']],
				[['FONT-SIZE' => 'first tag'], ['FONT-SIZE' => 'second tag'], ['FONT-SIZE' => 'id']],
			],
			$rules->matchingDeclarations('P', '', [], $path)
		);
	}

	/**
	 * The open elements are only asked for when a rule is filed under the element
	 */
	public function testOnlyAsksForThePathWhenARuleIsFiledUnderTheElement()
	{
		$asked = false;
		$path = function () use (&$asked) {
			$asked = true;

			return null;
		};

		$this->assertSame([[], []], $this->rules->matchingDeclarations('LI', '', [], $path));
		$this->assertTrue($asked, 'The rule for any element should ask');

		$rules = new RuleSet();
		$rules->add($this->compiler->compile('div > p'), ['COLOR' => 'red']);
		$asked = false;
		$this->assertSame([[], []], $rules->matchingDeclarations('LI', '', [], $path));
		$this->assertFalse($asked);
	}

	/**
	 * A path that ends in another element than the one being styled matches nothing
	 */
	public function testMatchesNothingForThePathOfAnotherElement()
	{
		$rules = new RuleSet();
		$rules->add($this->compiler->compile('div > span'), ['COLOR' => 'red']);

		$path = function () {
			return $this->path();
		};

		$this->assertSame([[], []], $rules->matchingDeclarations('SPAN', '', [], $path));
	}

	/**
	 * The open elements a rule is matched against: a `<p>` as the first child of `<div id="main" class="a">`, the
	 * document's first child
	 *
	 * @return array[] The frames from the document down to the paragraph
	 */
	private function path()
	{
		$frame = function ($tag, array $attr) {
			return [
				'tag' => $tag,
				'id' => isset($attr['ID']) ? $attr['ID'] : '',
				'classes' => isset($attr['CLASS']) ? [$attr['CLASS']] : [],
				'lang' => '',
				'attr' => $attr,
				'nthChild' => 1,
				'nthOfType' => 1,
				'children' => [],
				'childTypes' => [],
				'computed' => null,
			];
		};

		return [$frame('', []), $frame('DIV', ['ID' => 'MAIN', 'CLASS' => 'A']), $frame('P', [])];
	}
}
