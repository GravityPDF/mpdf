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

}
