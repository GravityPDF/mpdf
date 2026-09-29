<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * :not(), :is() and :where(), with selector lists and complex selectors as their arguments, on the element and on
 * its ancestors and siblings
 */
class LogicalSelectorTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';
	const GREEN = '0.000 0.502 0.000 rg';

	/**
	 * Each piece of text named is drawn in the colour the rules leave it in
	 *
	 * @dataProvider selectors
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected Pieces of text and the colour each should be drawn in
	 */
	public function testMatchesTheElementsTheSelectorNames($css, $html, array $expected)
	{
		$colours = $this->drawnColours('<style>' . $css . '</style>' . $html);

		foreach ($expected as $text => $colour) {
			$this->assertArrayHasKey($text, $colours, sprintf('"%s" is not drawn', $text));
			$this->assertSame($colour, $colours[$text], sprintf('"%s" is drawn in the wrong colour', $text));
		}
	}

	/**
	 * A rule, a document for it, and the colours its text should be drawn in: elements that match, and near misses
	 * that must not
	 *
	 * @return array[]
	 */
	public function selectors()
	{
		return [
			'not a class' => [
				'p:not(.a) { color: #f00; }',
				'<p class="a">with a</p><p class="b">with b</p><p>with none</p>',
				['with a' => self::BLACK, 'with b' => self::RED, 'with none' => self::RED],
			],
			'not any of a list' => [
				'p:not(.a, .b) { color: #f00; }',
				'<p class="a">a</p><p class="b">b</p><p class="c">c</p>',
				['a' => self::BLACK, 'b' => self::BLACK, 'c' => self::RED],
			],
			'not a structural pseudo-class' => [
				'li:not(:first-child) { color: #f00; }',
				'<ul><li>one</li><li>two</li><li>three</li></ul>',
				['one' => self::BLACK, 'two' => self::RED, 'three' => self::RED],
			],
			'not an attribute' => [
				'a:not([href^="https://"]) { color: #f00; }',
				'<p><a href="https://example.com/">secure</a> <a href="http://example.com/">plain</a></p>',
				['secure' => self::BLUE, 'plain' => self::RED],
			],
			'not a complex selector' => [
				'p:not(div > p) { color: #f00; }',
				'<div><p>in div</p></div><section><p>in section</p></section>',
				['in div' => self::BLACK, 'in section' => self::RED],
			],
			'not on an ancestor' => [
				'div:not(.card) > p { color: #f00; }',
				'<div class="card"><p>in card</p></div><div class="panel"><p>in panel</p></div>',
				['in card' => self::BLACK, 'in panel' => self::RED],
			],
			'is before an adjacent sibling' => [
				':is(h1, h2) + p { color: #f00; }',
				'<h1>One</h1><p>after h1</p><h2>Two</h2><p>after h2</p><h3>Three</h3><p>after h3</p>',
				['after h1' => self::RED, 'after h2' => self::RED, 'after h3' => self::BLACK],
			],
			'is on the element' => [
				'p:is(.a, #b) { color: #f00; }',
				'<p class="a">class</p><p id="b">id</p><p class="c">other</p>',
				['class' => self::RED, 'id' => self::RED, 'other' => self::BLACK],
			],
			'is with a complex selector, matched from the element' => [
				'p:is(h2 + p) { color: #f00; }',
				'<h2>Title</h2><p>first</p><p>second</p>',
				['first' => self::RED, 'second' => self::BLACK],
			],
			'is leaves out an argument it cannot read' => [
				':is(h1, a:hover) + p { color: #f00; }',
				'<h1>One</h1><p>after h1</p>',
				['after h1' => self::RED],
			],
			'where' => [
				':where(ul, ol) > li { color: #f00; }',
				'<ul><li>bullet</li></ul><ol><li>number</li></ol>',
				['bullet' => self::RED, 'number' => self::RED],
			],
			'is through an inline ancestor' => [
				':is(span, em) b { color: #f00; }',
				'<p><span><b>in span</b></span> <em><b>in em</b></em> <b>bare</b></p>',
				['in span' => self::RED, 'in em' => self::RED, 'bare' => self::BLACK],
			],
			'nested, and chained with other pseudo-classes' => [
				':is(ul, ol) > li:not(.skip):nth-child(odd) { color: #f00; }',
				'<ol><li>one</li><li>two</li><li class="skip">three</li><li>four</li><li>five</li></ol>',
				['one' => self::RED, 'two' => self::BLACK, 'three' => self::BLACK, 'five' => self::RED],
			],
			'is with an inherited language' => [
				'p:is(:lang(fr), .x) { color: #f00; }',
				'<div lang="fr-CA"><p>french</p></div><p class="x">classed</p><p>plain</p>',
				['french' => self::RED, 'classed' => self::RED, 'plain' => self::BLACK],
			],
			'not an inherited language' => [
				'p:not(:lang(fr)) { color: #f00; }',
				'<div lang="fr"><p>french</p></div><p>plain</p>',
				['french' => self::BLACK, 'plain' => self::RED],
			],
			'in a table cell' => [
				'td:not(:first-child) { color: #f00; }',
				'<table><tr><td>c1</td><td>c2</td></tr></table>',
				['c1' => self::BLACK, 'c2' => self::RED],
			],
			'not an argument it cannot read drops the rule' => [
				'p:not(.a, a:hover) { color: #f00; }',
				'<p>plain</p>',
				['plain' => self::BLACK],
			],
			'the universal selector as an argument waits for #530' => [
				'p:not(*) { color: #f00; } :is(*) + p { color: #f00; }',
				'<p>one</p><p>two</p>',
				['one' => self::BLACK, 'two' => self::BLACK],
			],
		];
	}

	/**
	 * :not() and :is() count as their most specific argument, and :where() as nothing
	 *
	 * @dataProvider precedence
	 *
	 * @param string $css
	 * @param string $expected The colour the text "text" should be drawn in
	 */
	public function testCountsTheSpecificityOfTheirArguments($css, $expected)
	{
		$colours = $this->drawnColours(
			'<style>' . $css . '</style><div id="main" class="box"><p class="a">text</p></div>'
		);

		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * Competing rules and the colour "text" should be drawn in
	 *
	 * @return array[]
	 */
	public function precedence()
	{
		return [
			'is with an id beats two classes, written first' => [':is(#main, .x) > p { color: #f00; } .box > p.a { color: #00f; }', self::RED],
			'where with an id loses to two types, written last' => ['div > p { color: #f00; } :where(#main) > p { color: #00f; }', self::RED],
			'where with an id against where with a class, the later wins' => [':where(#main) > p { color: #f00; } :where(.box) > p { color: #00f; }', self::BLUE],
			'not with an id beats a class' => ['p:not(#other) { color: #f00; } div > .a { color: #00f; }', self::RED],
			'not with a class ties with a class, the later wins' => ['div > p:not(.b) { color: #f00; } div > p.a { color: #00f; }', self::BLUE],
		];
	}

	/**
	 * A header is matched against its own elements, not the flow's elements open when it is set
	 */
	public function testMatchesAHeaderAgainstItsOwnElements()
	{
		$mpdf = $this->drawDocument(
			'<style>p:not(.side):is(:first-child) { color: #f00; } :where(div.x) > p:not(:first-child) { color: #00f; }</style>'
			. '<htmlpageheader name="h"><p>header first</p><p class="side">header side</p></htmlpageheader>'
			. '<div class="x"><p>flow first</p><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>flow second</p></div>',
			['setAutoTopMargin' => 'stretch']
		);
		$mpdf->Output('', 'S');

		$colours = array_combine(array_map('trim', $mpdf->drawnText), $mpdf->drawnColours);
		$this->assertSame(self::RED, $colours['header first']);
		$this->assertSame(self::BLACK, $colours['header side']);
		$this->assertSame(self::RED, $colours['flow first']);
		$this->assertSame(self::BLUE, $colours['flow second']);
	}

	/**
	 * A positioned block is matched against the elements it was written in, and its content against the block
	 */
	public function testMatchesAPositionedBlockAndItsContent()
	{
		$colours = $this->drawnColours(
			'<style>:is(section, aside) > div:not(.plain) { color: #f00; } div:is(.box) > p:not(:first-child) { color: #00f; }'
			. ' :where(div.box) > p:is(:first-child) { color: #008000; }</style>'
			. '<section><div class="box" style="position: absolute; top: 100mm; left: 20mm; width: 80mm">loose<p>first</p><p>second</p></div></section>'
			. '<section><div class="plain" style="position: absolute; top: 150mm; left: 20mm; width: 80mm">plain</div></section>'
		);

		$this->assertSame(self::RED, $colours['loose']);
		$this->assertSame(self::GREEN, $colours['first']);
		$this->assertSame(self::BLUE, $colours['second']);
		$this->assertSame(self::BLACK, $colours['plain']);
	}

	/**
	 * A kept block that runs onto the next page is matched again from its start tag, and its children are counted
	 * once
	 */
	public function testMatchesAKeptBlockLaidOutAgain()
	{
		$mpdf = $this->drawDocument(
			'<style>div.kept > p:not(:first-child, .skip) { color: #f00; } div:is(.kept) > p:is(.skip + p) { color: #00f; }</style>'
			. str_repeat('<p>filler</p>', 45)
			. '<div class="kept" style="page-break-inside: avoid"><p>one</p><p class="skip">two</p><p>three</p><p>four</p></div>'
		);

		$colours = array_combine(array_map('trim', $mpdf->drawnText), $mpdf->drawnColours);
		$pages = array_combine(array_map('trim', $mpdf->drawnText), array_column($mpdf->drawnBoxes, 0));
		$this->assertSame(2, $pages['one'], 'The block should have moved to the next page');
		$this->assertSame(self::BLACK, $colours['one']);
		$this->assertSame(self::BLACK, $colours['two']);
		$this->assertSame(self::BLUE, $colours['three']);
		$this->assertSame(self::RED, $colours['four']);
	}

	/**
	 * Elements inside one hidden with display: none are matched as earlier siblings and ancestors, with their
	 * attributes and classes as written
	 *
	 * @dataProvider hiddenSelectors
	 *
	 * @param string $selector
	 * @param bool $expected Whether it matches the span still open inside the hidden block
	 */
	public function testMatchesAgainstHiddenElements($selector, $expected)
	{
		$this->assertSame($expected, $this->matchesLastOpenElement(
			'<div class="box" style="display: none"><p class="a" data-state="Draft">hidden</p><span>',
			$selector
		));
	}

	/**
	 * A selector, and whether it matches the span inside the hidden block
	 *
	 * @return array[]
	 */
	public function hiddenSelectors()
	{
		return [
			'after an element that is one of a list' => [':is(p.a, h1) + span', true],
			'after an element that is none of a list' => [':is(p.b, h1) + span', false],
			'after an element that is not something else' => ['p:not([data-state="draft"]) + span', true],
			'after an element that is not what it is' => ['p:not([data-state="Draft"]) + span', false],
			'inside an ancestor, weighing nothing' => [':where(.box) > span', true],
			'not inside another ancestor' => ['span:not(.other > span, section span)', true],
			'not inside the ancestor it is in' => ['span:not(.other > span, .box > span)', false],
		];
	}
}
