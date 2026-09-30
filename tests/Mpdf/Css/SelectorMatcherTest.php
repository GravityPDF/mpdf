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
			'not the first child' => ['li:not(:first-child)', true],
			'not a class it has' => ['li:not(.x)', false],
			'not any of a list, one of which it is' => ['li:not(.y, :nth-child(2))', false],
			'not a complex selector it matches' => ['li:not(ul > li)', false],
			'not a complex selector it does not match' => ['li:not(ol > li)', true],
			'is one of a list' => [':is(ol, ul) > li', true],
			'is none of a list' => [':is(ol, dl) > li', false],
			'is a complex selector, matched from the element' => ['li:is(li + li)', true],
			'is on an ancestor' => ['div:is(#main, .other) li', true],
			'not on an ancestor' => ['div:not(.card) li', false],
			'is on a closed sibling' => [':is(h2, h3) ~ ul > li', true],
			'where' => [':where(#main) li.x', true],
			'nested' => ['li:not(:is(.y, :first-child))', true],
			'body in is' => [':is(body) > div li', true],
		];
	}

	/**
	 * Whether each attribute selector and :lang() matches the last element of the path below:
	 *
	 *   <body> (the document says lang="fr-CA")
	 *     <p lang="de" data-x="a">                               (closed)
	 *     <div class="Card Wide" id="Main">                      (open)
	 *       <a href="https://example.com/docs/guide.PDF"
	 *          data-role="Card link" type="Text" title="a &amp; b"
	 *          rel="nofollow external" data-empty="">            (the element, href as written before the base path
	 *                                                            was put in front of it)
	 *
	 * @dataProvider attributeSelectors
	 *
	 * @param string $selector
	 * @param bool $expected
	 */
	public function testMatchesAttributesAndLanguages($selector, $expected)
	{
		$document = $this->frame('', 1, 1, [], [['P']]);
		$document['lang'] = 'fr-CA';
		$document['children'][0]['attr'] = ['LANG' => 'de', 'DATA-X' => 'a'];
		$div = $this->frame('DIV', 2, 1, ['ID' => 'MAIN', 'CLASS' => 'CARD WIDE'], []);
		$div['classes'] = ['CARD', 'WIDE'];
		$div['lang'] = 'fr-CA';
		$link = $this->frame('A', 1, 1, [
			'HREF' => 'file:///base/https://example.com/docs/guide.PDF',
			'ORIG_SRC' => 'https://example.com/docs/guide.PDF',
			'DATA-ROLE' => 'Card link',
			'TYPE' => 'Text',
			'TITLE' => 'a &amp; b',
			'REL' => 'nofollow external',
			'DATA-EMPTY' => '',
		], []);
		$link['lang'] = 'fr-CA';

		$this->assertSame($expected, $this->matcher->matches($this->compiler->compile($selector), [$document, $div, $link]));
	}

	/**
	 * An attribute selector or :lang(), and whether it matches the link
	 *
	 * @return array[]
	 */
	public function attributeSelectors()
	{
		return [
			'an attribute it has' => ['[data-role]', true],
			'an attribute it has with an empty value' => ['[data-empty]', true],
			'an attribute it does not have' => ['[data-missing]', false],
			'equals' => ['[data-role="Card link"]', true],
			'equals in another case' => ['[data-role="card link"]', false],
			'equals in another case with the i flag' => ['[data-role="card link" i]', true],
			'an attribute HTML compares case-insensitively' => ['[type=text]', true],
			'the same attribute with the s flag' => ['[type=text s]', false],
			'one word of a list' => ['[rel~=external]', true],
			'part of a word of a list' => ['[rel~=extern]', false],
			'a list word with a space in it' => ['[rel~="nofollow external"]', false],
			'the whole value or its start before a hyphen' => ['[data-role|="Card link"]', true],
			'a start not followed by a hyphen' => ['[data-role|=Card]', false],
			'starts with' => ['[href^="https://"]', true],
			'starts with the base path put in front of it' => ['[href^="file:"]', false],
			'starts with nothing' => ['[href^=""]', false],
			'ends with' => ['[href$=".PDF"]', true],
			'ends with in another case' => ['[href$=".pdf"]', false],
			'ends with in another case with the i flag' => ['[href$=".pdf" i]', true],
			'contains' => ['[href*="/docs/"]', true],
			'contains nothing' => ['[href*=""]', false],
			'a value with an entity' => ['[title="a & b"]', true],
			'an id in another case' => ['div[id=main] > a', true],
			'a class word in another case' => ['[class~=wide] a', true],
			'an attribute of a sibling of an ancestor' => ['p[data-x=a] + div > a', true],
			'the wrong attribute value on a sibling of an ancestor' => ['p[data-x=b] + div > a', false],
			'the language it inherits' => [':lang(fr)', true],
			'the language with its region' => ['a:lang(fr-ca)', true],
			'another region' => ['a:lang(fr-fr)', false],
			'another language' => [':lang(de)', false],
			'one of several languages' => [':lang(de, fr)', true],
			'the start of a subtag that is not a whole one' => [':lang(f)', false],
			'the language of the document' => ['body:lang(fr) a', true],
			'the language of a sibling of an ancestor' => ['p:lang(de) + div a', true],
			'a language a sibling of an ancestor does not have' => ['p:lang(fr) + div a', false],
		];
	}

	/**
	 * A failure left of a combinator stops the tries only where no other ancestor or sibling could match: a child
	 * combinator that fails at the nearest ancestor is tried at the next one, and an adjacent or general sibling
	 * combinator that fails at the first sibling at the next one
	 *
	 * @dataProvider retriedSelectors
	 *
	 * @param string $selector
	 * @param bool $expected Whether it matches the paragraph in `<section><article><div><article>` that follows an
	 *                       `<h2>`, an `<h1>`, an `<h2>` and a `<ul>`
	 */
	public function testTriesTheNextAncestorOrSiblingAfterAFailure($selector, $expected)
	{
		$path = [
			$this->frame('', 1, 1, [], []),
			$this->frame('SECTION', 1, 1, [], []),
			$this->frame('ARTICLE', 1, 1, [], []),
			$this->frame('DIV', 1, 1, [], []),
			$this->frame('ARTICLE', 1, 1, [], [['H2'], ['H1'], ['H2'], ['UL']]),
			$this->frame('P', 5, 1, [], []),
		];

		$this->assertSame($expected, $this->matcher->matches($this->compiler->compile($selector), $path));
	}

	/**
	 * A selector whose left part fails where it is first tried, and whether it matches in the end
	 *
	 * @return array[]
	 */
	public function retriedSelectors()
	{
		return [
			'a child of a further ancestor' => ['section > article p', true],
			'a child of no ancestor' => ['div > section p', false],
			'the second of two siblings is adjacent to the one it needs' => ['h1 + h2 ~ p', true],
			'no sibling is adjacent to the one it needs' => ['ul + h2 ~ p', false],
			'the second of two siblings follows the one it needs' => ['h1 ~ h2 ~ p', true],
			'no sibling follows the one it needs' => ['ul ~ h1 ~ p', false],
			'a sibling tag the parent has no child of' => ['h3 ~ p', false],
			'an ancestor chain that fails everywhere' => ['p article section div p', false],
		];
	}

	/**
	 * A chain of descendant combinators whose leftmost compound names the nearest ancestor fails without trying every
	 * combination of ancestors. On a path 50 deep, `.x div div div div div p` has nearly two million of them
	 */
	public function testGivesUpOnADescendantChainOnceNoAncestorCanMatch()
	{
		$path = [$this->frame('', 1, 1, [], [])];
		for ($depth = 1; $depth < 50; $depth++) {
			$path[] = $this->frame('DIV', 1, 1, $depth === 49 ? ['CLASS' => 'X'] : [], []);
		}
		$path[] = $this->frame('P', 1, 1, [], []);

		$start = microtime(true);
		$this->assertFalse($this->matcher->matches($this->compiler->compile('.x div div div div div p'), $path));
		$this->assertTrue($this->matcher->matches($this->compiler->compile('div div div div div.x p'), $path));
		$this->assertLessThan(1, microtime(true) - $start);
	}

	/**
	 * A general sibling combinator finds a sibling at the start of a long run of them, and skips a tag its parent has
	 * no child of, without walking back through every sibling for each element. Matching each of 5,000 paragraphs
	 * after an `<h2>` walks 12.5 million siblings that way
	 */
	public function testFindsAnEarlierSiblingWithoutWalkingThroughEachOne()
	{
		$children = [['H2']];
		for ($i = 0; $i < 5000; $i++) {
			$children[] = ['P'];
		}
		$parent = $this->frame('', 1, 1, [], $children);
		$heading = $this->compiler->compile('h2 ~ p');
		$missing = $this->compiler->compile('h3 ~ p');

		$start = microtime(true);
		for ($i = 1; $i <= 5000; $i++) {
			$path = [$parent, $this->frame('P', $i + 1, $i, [], [])];
			$this->assertTrue($this->matcher->matches($heading, $path));
			$this->assertFalse($this->matcher->matches($missing, $path));
		}
		$this->assertLessThan(1, microtime(true) - $start);
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
		];
	}
}
