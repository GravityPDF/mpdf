<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Descendant rules the legacy parser reads, matched through ancestors the legacy engine does not look at: inline
 * elements, blocks inside a table cell and a tbody a table implies. The legacy engine only looks for the ancestors
 * a descendant rule names among blocks and table parts, so the matcher applies a rule where only such an ancestor
 * lets it match, and leaves the rest to the legacy engine.
 */
class InlineAncestorSelectorTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';

	/**
	 * Each piece of text named is drawn in the colour the rules leave it in
	 *
	 * @dataProvider selectors
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected Pieces of text and the colour each should be drawn in
	 */
	public function testMatchesThroughAncestorsTheLegacyEngineDoesNotLookAt($css, $html, array $expected)
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
			'an inline ancestor' => [
				'span em { color: #f00; }',
				'<p><span>in <em>span em</em></span> and <em>bare em</em></p>',
				['span em' => self::RED, 'bare em' => self::BLACK, 'in' => self::BLACK],
			],
			'a class on an inline ancestor' => [
				'.x b { color: #f00; }',
				'<p><span class="x">x <b>in span.x</b></span> <b>outside</b></p>',
				['in span.x' => self::RED, 'outside' => self::BLACK],
			],
			'a class on a block ancestor, as before' => [
				'.x b { color: #f00; }',
				'<p class="x"><b>in p.x</b></p><p><b>in p</b></p>',
				['in p.x' => self::RED, 'in p' => self::BLACK],
			],
			'a link as the ancestor' => [
				'a span { color: #f00; }',
				'<p><a href="https://example.com/"><span>linked span</span></a> <span>plain span</span></p>',
				['linked span' => self::RED, 'plain span' => self::BLACK],
			],
			'block and inline ancestors mixed' => [
				'div span b { color: #f00; }',
				'<div><p><span><b>all three</b></span></p></div><p><span><b>no div</b></span></p><div><p><b>no span</b></p></div>',
				['all three' => self::RED, 'no div' => self::BLACK, 'no span' => self::BLACK],
			],
			'two inline ancestors' => [
				'span.a em.b strong { color: #f00; }',
				'<p><span class="a"><em class="b"><strong>deep</strong></em></span> <em class="b"><strong>no span</strong></em></p>',
				['deep' => self::RED, 'no span' => self::BLACK],
			],
			'an id on an inline ancestor' => [
				'#note i { color: #f00; }',
				'<p><span id="note"><i>in #note</i></span> <i>outside</i></p>',
				['in #note' => self::RED, 'outside' => self::BLACK],
			],
			'an inline ancestor in a table cell' => [
				'span b { color: #f00; }',
				'<table><tr><td><span><b>in cell</b></span> <b>bare</b></td></tr></table>',
				['in cell' => self::RED, 'bare' => self::BLACK],
			],
			'a block inside a table cell as the ancestor' => [
				'div b { color: #f00; }',
				'<table><tr><td><div><b>in div in cell</b></div><b>in cell</b></td></tr></table>',
				['in div in cell' => self::RED, 'in cell' => self::BLACK],
			],
			'the tbody a table implies' => [
				'table tbody td { color: #f00; }',
				'<table><tr><td>implied tbody</td></tr></table>',
				['implied tbody' => self::RED],
			],
			'a positioned block inside an inline element' => [
				'span.w p { color: #f00; }',
				'<span class="w"><div style="position: absolute; top: 100mm; left: 20mm; width: 80mm"><p>positioned</p></div></span>',
				['positioned' => self::RED],
			],
		];
	}

	/**
	 * A descendant rule the legacy engine applies through blocks is not applied again by the matcher, after the
	 * rules the legacy engine applied after it
	 *
	 * @dataProvider legacyMatches
	 *
	 * @param string $css
	 * @param string $html
	 * @param string $expected The colour the text "text" should be drawn in
	 */
	public function testLeavesToTheLegacyEngineWhatItMatches($css, $html, $expected)
	{
		$colours = $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => 'legacy']);

		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * Descendant rules the legacy engine applies in the order of the ancestors they were found at, a document, and
	 * the colour its text should be drawn in
	 *
	 * @return array[]
	 */
	public function legacyMatches()
	{
		return [
			'block ancestors' => [
				'.a b { color: #f00; } .a .c b { color: #00f; }',
				'<div class="a"><div class="c"><b>text</b></div></div>',
				self::BLUE,
			],
			'the document as the ancestor' => [
				'body b { color: #f00; } p b { color: #00f; }',
				'<p><b>text</b></p>',
				self::BLUE,
			],
			'a table and its cell' => [
				'td b { color: #00f; } table b { color: #f00; }',
				'<table><tr><td><b>text</b></td></tr></table>',
				self::BLUE,
			],
			'the content of a positioned block' => [
				'#box b { color: #f00; } .box p b { color: #00f; }',
				'<span><div id="box" class="box" style="position: absolute; top: 100mm; left: 20mm; width: 80mm"><p><b>text</b></p></div></span>',
				self::BLUE,
			],
			'a block and an inline ancestor, where the block matches' => [
				'.a b { color: #f00; } p b { color: #00f; }',
				'<div class="a"><p><span class="a"><b>text</b></span></p></div>',
				self::BLUE,
			],
		];
	}

	/**
	 * Rules matched through an inline ancestor come after the descendant rules the legacy engine applies, as the
	 * rules only the matcher reads do
	 */
	public function testAppliesRulesMatchedThroughAnInlineAncestorAfterTheLegacyOnes()
	{
		$colours = $this->drawnColours(
			'<style>.y b { color: #00f; } .x b { color: #f00; }</style><p class="x"><span class="y"><b>text</b></span></p>',
			['cssMode' => 'legacy']
		);

		$this->assertSame(self::BLUE, $colours['text']);
	}
}
