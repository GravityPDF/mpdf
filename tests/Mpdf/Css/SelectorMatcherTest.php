<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Matching compiled selectors right to left against a path of open elements, as Mpdf::getStyledElementPath()
 * gives it
 */
class SelectorMatcherTest extends TestCase
{

	/**
	 * @var SelectorCompiler
	 */
	private $compiler;

	/**
	 * @var SelectorMatcher
	 */
	private $matcher;

	/**
	 * A compiler and a matcher
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->compiler = new SelectorCompiler(new Mpdf());
		$this->matcher = new SelectorMatcher();
	}

	/**
	 * Lets go of the compiler and matcher
	 */
	protected function tear_down()
	{
		unset($this->compiler, $this->matcher);

		parent::tear_down();
	}

	/**
	 * Whether each selector matches the last element of the path below:
	 *
	 *   <body>
	 *     <h1/> <p class="a"/> <p/>              (closed)
	 *     <div id="main" class="card">           (open, third element)
	 *       <h2/> <p/>                           (closed)
	 *       <ul>                                 (open)
	 *         <li/>                              (closed)
	 *         <li class="x">                     (the element)
	 *
	 * @dataProvider selectors
	 *
	 * @param string $selector
	 * @param bool $expected
	 */
	public function testMatchesTheLastElementOfThePath($selector, $expected)
	{
		$this->assertSame($expected, $this->matcher->matches($this->compiler->compile($selector), $this->path()));
	}

	/**
	 * A selector, and whether it matches the element
	 *
	 * @return array[]
	 */
	public function selectors()
	{
		return [
			'its tag' => ['li', true],
			'another tag' => ['p', false],
			'its class' => ['.x', true],
			'a class it does not have' => ['.y', false],
			'its parent' => ['ul > li', true],
			'not its parent' => ['div > li', false],
			'an ancestor' => ['div li', true],
			'an ancestor by id and class' => ['#main.card li', true],
			'body as the root' => ['body > div > ul > li', true],
			'body with a parent' => ['div > body li', false],
			'the sibling before it' => ['li + li', true],
			'the sibling before it must be the one right before' => ['h2 + li', false],
			'a sibling before it' => ['li ~ .x', true],
			'the first child is not it' => ['li:first-child', false],
			'the second child' => ['li:nth-child(2)', true],
			'the second child of its type' => ['li:nth-of-type(2)', true],
			'the first of its type' => ['li:first-of-type', false],
			'an ancestor at its position among its siblings' => ['div:nth-child(4) li', true],
			'an ancestor at the wrong position' => ['div:first-child li', false],
			'an ancestor that is the first of its type' => ['div:first-of-type li', true],
			'a closed sibling of an ancestor' => ['p + div li', true],
			'a closed sibling of an ancestor by class and position' => ['p.a:nth-of-type(1) ~ div li', true],
			'a closed sibling of an ancestor at its position among its type' => ['p:nth-of-type(2) + div li', true],
			'the wrong closed sibling of an ancestor' => ['h1 + div li', false],
			'body has no siblings' => ['body:first-child li', false],
			'a chain through siblings and ancestors' => ['h1 ~ .card > h2 ~ ul > li + .x', true],
		];
	}

	/**
	 * Looking only at the ancestors that opened a level of the legacy descendant rules, a descendant combinator skips
	 * the others
	 *
	 * @dataProvider levelSelectors
	 *
	 * @param string $selector
	 * @param bool $anyAncestor Whether it matches through any ancestor
	 * @param bool $legacyView Whether it matches as the legacy engine sees it
	 */
	public function testMatchesAsTheLegacyEngineWhenAsked($selector, $anyAncestor, $legacyView)
	{
		$document = $this->frame('', 1, 1, [], []);
		$document['level'] = true;
		$paragraph = $this->frame('P', 1, 1, ['CLASS' => 'A'], []);
		$paragraph['level'] = true;
		$path = [$document, $paragraph, $this->frame('SPAN', 1, 1, ['CLASS' => 'A'], []), $this->frame('B', 1, 1, [], [])];

		$compiled = $this->compiler->compile($selector);
		$this->assertSame($anyAncestor, $this->matcher->matches($compiled, $path));
		$this->assertSame($legacyView, $this->matcher->matches($compiled, $path, true));
	}

	/**
	 * A selector for <b> in <span class="a"> in <p class="a">, and whether it matches through any ancestor and
	 * through levels only
	 *
	 * @return array[]
	 */
	public function levelSelectors()
	{
		return [
			'the block' => ['p b', true, true],
			'the inline element' => ['span b', true, false],
			'a class on both' => ['.a b', true, true],
			'both' => ['p span b', true, false],
			'the document' => ['body b', true, true],
			'a child combinator is not a descendant one' => ['span > b', true, true],
		];
	}

	/**
	 * The open elements the selectors are matched against: `<li class="x">`, the second item of a `<ul>` that follows
	 * an `<h2>` and a `<p>` in `<div id="main" class="card">`, which follows an `<h1>`, a `<p class="a">` and a `<p>` in
	 * the document
	 *
	 * @return array[] The frames from the document down to the item
	 */
	private function path()
	{
		return [
			$this->frame('', 1, 1, [], [['H1'], ['P', 'A'], ['P']]),
			$this->frame('DIV', 4, 1, ['ID' => 'MAIN', 'CLASS' => 'CARD'], [['H2'], ['P']]),
			$this->frame('UL', 3, 1, [], [['LI']]),
			$this->frame('LI', 2, 2, ['CLASS' => 'X'], []),
		];
	}

	/**
	 * A frame in the shape Mpdf::getOpenElements() gives
	 *
	 * @param string $tag
	 * @param int $nthChild
	 * @param int $nthOfType
	 * @param string[] $attr
	 * @param array[] $children Each closed child's tag and, if any, its class
	 *
	 * @return array
	 */
	private function frame($tag, $nthChild, $nthOfType, array $attr, array $children)
	{
		$records = [];
		$childTypes = [];
		foreach ($children as $child) {
			$childTypes[$child[0]] = isset($childTypes[$child[0]]) ? $childTypes[$child[0]] + 1 : 1;
			$records[] = [
				'tag' => $child[0],
				'id' => '',
				'classes' => isset($child[1]) ? [$child[1]] : [],
				'attr' => isset($child[1]) ? ['CLASS' => $child[1]] : [],
				'nthOfType' => $childTypes[$child[0]],
			];
		}

		return [
			'tag' => $tag,
			'id' => isset($attr['ID']) ? $attr['ID'] : '',
			'classes' => isset($attr['CLASS']) ? [$attr['CLASS']] : [],
			'lang' => '',
			'attr' => $attr,
			'nthChild' => $nthChild,
			'nthOfType' => $nthOfType,
			'children' => $records,
			'childTypes' => $childTypes,
			'computed' => null,
			'level' => false,
		];
	}
}
