<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Selectors that write an id and classes as one part, such as `p#i.c`, `p.c#i`, `#i.c` and `p.a.b#i`, on their own
 * and as parts of descendant rules.
 *
 * They are applied after `tag#id`, so each beats `p#i` and `.c`.
 */
class CompoundIdClassSelectorTest extends TestCase
{

	use PageStreams;

	const GREEN = '0.000 0.502 0.000 rg';
	const RED = '1.000 0.000 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * The text is drawn in the colour the rules leave it in
	 *
	 * @dataProvider rules
	 *
	 * @param string $css
	 * @param string $html
	 * @param string $expected The fill colour the text should be drawn in
	 */
	public function testTheRuleColoursTheText($css, $html, $expected)
	{
		$colours = $this->textColours('<style>' . $css . '</style>' . $html);

		$this->assertArrayHasKey('text', $colours);
		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * A stylesheet, a document for it, and the colour its text should be drawn in
	 *
	 * @return array[]
	 */
	public function rules()
	{
		$cases = [];
		foreach (['p#i.c', 'p.c#i', '#i.c', 'p.a.c#i'] as $selector) {
			$cases[$selector] = [
				'.c { color: red; } p#i { color: red; } ' . $selector . ' { color: green; } p#i { color: red; } .c { color: red; }',
				'<p id="i" class="a c">text</p>',
				self::GREEN,
			];
			$cases[$selector . ' after an ancestor'] = [
				'div .c { color: red; } div p#i { color: red; } div ' . $selector . ' { color: green; } div p#i { color: red; }',
				'<div><p id="i" class="a c">text</p></div>',
				self::GREEN,
			];
			$cases[$selector . ' in a table cell'] = [
				'table .c { color: red; } table td#i { color: red; } table ' . str_replace('p', 'td', $selector) . ' { color: green; }',
				'<table><tr><td id="i" class="a c">text</td></tr></table>',
				self::GREEN,
			];
		}

		return $cases + [
			'an ancestor' => [
				'div#o.x p { color: green; }',
				'<div id="o" class="x"><p>text</p></div>',
				self::GREEN,
			],
			'an ancestor with its classes in another order' => [
				'div.y#o.x p { color: green; }',
				'<div id="o" class="x y"><p>text</p></div>',
				self::GREEN,
			],
			'a table cell as the ancestor' => [
				'td#o.x span { color: green; }',
				'<table><tr><td id="o" class="x"><span>text</span></td></tr></table>',
				self::GREEN,
			],
			'another id' => [
				'p#j.c { color: red; }',
				'<p id="i" class="c">text</p>',
				self::BLACK,
			],
			'a class the element does not have' => [
				'p#i.c.d { color: red; }',
				'<p id="i" class="c">text</p>',
				self::BLACK,
			],
			'another tag' => [
				'div#i.c { color: red; }',
				'<p id="i" class="c">text</p>',
				self::BLACK,
			],
			'an ancestor with another class' => [
				'div#o.y p { color: red; }',
				'<div id="o" class="x"><p>text</p></div>',
				self::BLACK,
			],
			'more classes after a tag' => [
				'#i.c.d { color: green; } p#i.c { color: red; }',
				'<p id="i" class="c d">text</p>',
				self::GREEN,
			],
			'a tag after the same classes' => [
				'p#i.c { color: green; } #i.c { color: red; }',
				'<p id="i" class="c">text</p>',
				self::GREEN,
			],
		];
	}

}
