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
			'html as an ancestor' => ['html li', true],
			'html as the parent of body' => ['html > body > div > ul > li', true],
			'html is not the parent of what body holds' => ['html > div li', false],
			'html has no parent' => ['div > html li', false],
			'html has no siblings' => ['html + body li', false],
			'html is not the first child' => ['html:first-child li', false],
			'root as an ancestor' => [':root li', true],
			'root as the parent of body' => [':root > body li', true],
			'root is not body' => [':root > div li', false],
			'body is not the root' => ['body:root li', false],
			'the element is not the root' => ['li:root', false],
			'not the root' => ['li:not(:root)', true],
			'root in is' => [':is(:root) div li', true],
			'hover' => ['li:hover', false],
			'hover on an ancestor' => ['div:hover li', false],
			'focus, active, focus-within, focus-visible and target' => ['li:is(:focus, :active, :focus-within, :focus-visible, :target)', false],
			'not hover' => ['li:not(:hover)', true],
			'not visited' => ['li:not(:visited)', true],
			'not hover on an ancestor' => ['div:not(:hover) li', true],
			'is hover' => ['li:is(:hover)', false],
			'link on an element with no href' => ['li:link', false],
			'not link on an element with no href' => ['li:not(:link)', true],
			'the universal selector' => ['*', true],
			'the universal selector with its class' => ['*.x', true],
			'the universal selector with a class it does not have' => ['*.y', false],
			'the universal selector as its parent' => ['* > li', true],
			'the universal selector as a child of its parent' => ['ul > *', true],
			'the universal selector as a child of an ancestor that is not its parent' => ['div > *.x', false],
			'the universal selector as an ancestor' => ['#main * li', true],
			'the universal selector as html, an ancestor of body' => ['* body li', true],
			'the universal selector as a descendant of body' => ['body * li', true],
			'the universal selector as the root' => ['* > div > ul > li', true],
			'the universal selector as html, the parent of body' => ['* > * > div > ul > li', true],
			'the universal selector as a parent of html' => ['* > * > * > div > ul > li', false],
			'the universal selector as the sibling before it' => ['* + li', true],
			'the universal selector as a sibling before its first sibling' => ['li:first-child + *', true],
			'the universal selector before the first child' => ['* + h1', false],
			'the universal selector as a sibling of an ancestor' => ['* ~ div li', true],
			'the universal selector in is' => [':is(*) > li', true],
			'the universal selector in not' => ['li:not(*)', false],
			'the universal selector in not on an ancestor' => ['div:not(*) li', false],
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
			'link' => ['a:link', true],
			'any-link' => [':any-link', true],
			'visited' => ['a:visited', false],
			'link and hover' => ['a:link:hover', false],
			'not link' => ['a:not(:link)', false],
			'not visited' => ['a:not(:visited)', true],
			'link with a class' => ['a.card:link', false],
			'an element with a link attribute that is not href' => ['div[id]:link a', false],
			'the language of the root' => [':root:lang(fr) a', true],
			'another language on html' => ['html:lang(de) a', false],
		];
	}

	/**
	 * html and body, which the document's frame stands for, are matched with html as body's parent, and neither with
	 * a sibling. The document says lang="fr"
	 *
	 * @dataProvider documentSelectors
	 *
	 * @param string $selector
	 * @param bool $html Whether it matches html
	 * @param bool $body Whether it matches body
	 */
	public function testMatchesHtmlAndBody($selector, $html, $body)
	{
		$document = $this->frame('', 1, 1, [], []);
		$document['lang'] = 'fr';
		$compiled = $this->compiler->compile($selector);

		$this->assertSame($html, $this->matcher->matchesDocumentElement($compiled, [$document], true), 'html');
		$this->assertSame($body, $this->matcher->matchesDocumentElement($compiled, [$document], false), 'body');
	}

	/**
	 * A selector, and whether it matches html and body
	 *
	 * @return array[]
	 */
	public function documentSelectors()
	{
		return [
			'html' => ['html', true, false],
			'root' => [':root', true, false],
			'html as the root' => ['html:root', true, false],
			'body' => ['body', false, true],
			'body as the root' => ['body:root', false, false],
			'body as the child of html' => ['html > body', false, true],
			'body as the child of the root' => [':root > body', false, true],
			'body in html' => ['html body', false, true],
			'body in body' => ['body body', false, false],
			'html in body' => ['body html', false, false],
			'html beside body' => ['html + body', false, false],
			'body after something' => [':root ~ body', false, false],
			'html as a first child' => ['html:first-child', false, false],
			'not the root' => [':not(:root)', false, true],
			'not html' => ['body:not(html)', false, true],
			'the language of the document' => [':lang(fr)', true, true],
			'another language' => ['html:lang(de)', false, false],
			'hover' => ['html:hover', false, false],
			'a class' => ['html.a', false, false],
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
	 * Whether each selector that looks at what follows an element matches the last element of the path below, with
	 * what is read ahead of each element known:
	 *
	 *   <body>                                   (5 children)
	 *     <h1/> <p class="a"/> <p/>              (closed)
	 *     <div id="main" class="card">           (open, 3 children)
	 *       <h2/> <p/>                           (closed; the p is empty)
	 *       <ul>                                 (open, 3 children)
	 *         <li/>                              (closed)
	 *         <li class="x">                     (the element, empty)
	 *         <li/>                              (still to come)
	 *     <section/>                             (still to come)
	 *
	 * @dataProvider lookAheadSelectors
	 *
	 * @param string $selector
	 * @param bool $expected
	 */
	public function testMatchesWhatFollowsTheElement($selector, $expected)
	{
		$this->assertSame($expected, $this->matcher->matches($this->compiler->compile($selector), $this->pathReadAhead(true)));
	}

	/**
	 * A selector, and whether it matches the element
	 *
	 * @return array[]
	 */
	public function lookAheadSelectors()
	{
		return [
			'not the last child' => ['li:last-child', false],
			'second from the end' => ['li:nth-last-child(2)', true],
			'not third from the end' => ['li:nth-last-child(3)', false],
			'an odd place from the end' => ['li:nth-last-child(odd)', false],
			'among the last two' => ['li:nth-last-child(-n + 2)', true],
			'second of its type from the end' => ['li:nth-last-of-type(2)', true],
			'not the last of its type' => ['li:last-of-type', false],
			'not the only child' => ['li:only-child', false],
			'not the only one of its type' => ['li:only-of-type', false],
			'empty' => ['li:empty', true],
			'not last' => ['li:not(:last-child)', true],
			'both ends counted' => ['li:nth-child(2):nth-last-child(2)', true],
			'an ancestor that is the last child' => ['ul:last-child > li', true],
			'an ancestor that is not the last child' => ['div:last-child li', false],
			'an ancestor second from the end' => ['div:nth-last-child(2) li', true],
			'an ancestor that is the only one of its type' => ['div:only-of-type li', true],
			'an ancestor that is not empty' => ['ul:empty li', false],
			'a closed sibling of an ancestor that is empty' => ['p:empty + ul > li', true],
			'a closed sibling of an ancestor that is not empty' => ['h2:empty ~ ul li', false],
			'a closed sibling at its place from the end' => ['li:nth-last-child(3) + li', true],
			'a closed sibling of an ancestor, last of its type' => ['p:last-of-type + ul li', true],
			'the document is not a child' => ['body:last-child li', false],
			'the document is not empty' => ['body:empty li', false],
		];
	}

	/**
	 * Where what follows an element is not known, as for elements still open at the end of a WriteHTML() call that
	 * leaves them open, a pseudo-class that needs it does not match, and :not() of it does not match either
	 *
	 * @dataProvider undecidedSelectors
	 *
	 * @param string $selector
	 * @param bool $expected
	 */
	public function testDoesNotMatchWhatFollowsWhereItIsNotKnown($selector, $expected)
	{
		$this->assertSame($expected, $this->matcher->matches($this->compiler->compile($selector), $this->pathReadAhead(false)));
	}

	/**
	 * A selector, and whether it matches the element with what follows it not known
	 *
	 * @return array[]
	 */
	public function undecidedSelectors()
	{
		return [
			'last-child' => ['li:last-child', false],
			'not last-child' => ['li:not(:last-child)', false],
			'nth-last-child' => ['li:nth-last-child(2)', false],
			'only-of-type' => ['li:only-of-type', false],
			'empty' => ['li:empty', false],
			'not empty' => ['li:not(:empty)', false],
			'not of a list with something undecided in it' => ['li:not(.y, :last-child)', false],
			'not of a list with something that matches in it' => ['li:not(.x, :last-child)', false],
			'is of a list with something that matches in it' => ['li:is(:last-child, .x)', true],
			'not of not' => ['li:not(:not(:last-child))', false],
			'not of something known beside it' => ['li:not(:first-child)', true],
			'a known ancestor' => ['div:nth-last-child(2) li', true],
			'not on a known ancestor' => ['div:not(:last-child) li', true],
		];
	}

	/**
	 * The path testMatchesWhatFollowsTheElement() describes
	 *
	 * @param bool $known Whether what follows the list and the item is known. Only the rest is if not
	 *
	 * @return array[]
	 */
	private function pathReadAhead($known)
	{
		$document = $this->readAhead($this->frame('', 1, 1, [], [['H1', null, false], ['P', 'A', false], ['P', null, false]]), 5, ['H1' => 1, 'P' => 2, 'DIV' => 1, 'SECTION' => 1], false);
		$div = $this->readAhead($this->frame('DIV', 4, 1, ['ID' => 'MAIN', 'CLASS' => 'CARD'], [['H2', null, false], ['P', null, true]]), 3, ['H2' => 1, 'P' => 1, 'UL' => 1], false);
		$list = $this->frame('UL', 3, 1, [], [['LI', null, false]]);
		$item = $this->frame('LI', 2, 2, ['CLASS' => 'X'], []);
		if ($known) {
			$list = $this->readAhead($list, 3, ['LI' => 3], false);
			$item = $this->readAhead($item, 0, [], true);
		}

		return [$document, $div, $list, $item];
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
	 * @param array[] $children Each closed child's tag and, if any, its class (or null) and whether it is empty
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
				'empty' => isset($child[2]) ? $child[2] : null,
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
			'key' => '',
			'childTotal' => null,
			'childTypeTotals' => null,
			'empty' => null,
			'computed' => null,
		];
	}

	/**
	 * A frame given what TracksOpenElements::readAhead() finds of its element
	 *
	 * @param array $frame
	 * @param int|null $childTotal
	 * @param int[]|null $childTypeTotals
	 * @param bool|null $empty
	 *
	 * @return array
	 */
	private function readAhead(array $frame, $childTotal, $childTypeTotals, $empty)
	{
		$frame['childTotal'] = $childTotal;
		$frame['childTypeTotals'] = $childTypeTotals;
		$frame['empty'] = $empty;

		return $frame;
	}
}
