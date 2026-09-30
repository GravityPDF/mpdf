<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * A rule written after a brace, semicolon or comment marker inside a string, a url() or an escape, or after a rule or
 * at-rule nested in a block, still applies, in either cssMode, wherever the element it styles is: a block, an
 * inline element, a table cell, a header or a positioned block.
 */
class StylesheetSyntaxTest extends TestCase
{

	use DrawnStyles;

	const GREEN = '0.000 0.502 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * The element the rule after the construct names is green, and its neighbour, which the rule does not name, is
	 * black
	 *
	 * @dataProvider constructsInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag, and {AFTER} for the rule after the construct if it is not
	 *                    at the end
	 */
	public function testTheRuleAfterTheConstructApplies($mode, $context, $css)
	{
		$this->assertDrawnInColours(
			['after' => self::GREEN, 'plain' => self::BLACK],
			$this->drawnColours($this->document($context, $css), ['cssMode' => $mode])
		);
	}

	/**
	 * Every construct in every context, in each cssMode
	 *
	 * @return array[]
	 */
	public function constructsInContexts()
	{
		$data = [];
		foreach ([CssMode::LEGACY, CssMode::STANDARD] as $mode) {
			foreach (['block', 'inline', 'table cell', 'header', 'positioned block'] as $context) {
				foreach ($this->constructs() as $name => $css) {
					$data[$mode . ', ' . $context . ': ' . $name] = [$mode, $context, $css];
				}
			}
		}

		return $data;
	}

	/**
	 * Stylesheets that were split in the wrong place, each with the rule after its tricky part
	 *
	 * @return string[]
	 */
	private function constructs()
	{
		return [
			'braces in a string' => 'q { quotes: "}" "{"; }',
			'an unbalanced brace in a string' => 'q { quotes: "}"; }',
			'a declaration in a string' => "{T}.after { color: #008000; font-family: 'x;color:#f00;y', courier; }",
			'an escaped quote in a string' => 'q { quotes: "\"}" "x"; }',
			'a comment opener in a string' => 'q { quotes: "/*" "x"; } {AFTER} q { quotes: "*/" "x"; }',
			'a comment opener in a url()' => 'q { background-image: url(http://example.com/*.png); } {AFTER} /* a comment */',
			'a comment with a quote and a brace in it' => '/* it\'s { */',
			'a string left open at a line break' => "q { quotes: \"a}b\n; }",
			'an escaped brace in a selector' => '.a\{b { color: #f00; }',
			'a brace in an attribute selector' => 'q[title="{"] { color: #f00; }',
			'braces and a semicolon in a url()' => 'q { background-image: url(a};{b.png); }',
			'semicolons in an @import url()' => '@import url(missing.css?family=a:wght@300;400);',
			'nested at-rules' => '@supports (display: block) { @media print { q { color: #f00; } } }',
			'an at-rule nested in a block' => '{T}.after { color: #f00; @media print { color: #00f; } color: #008000; }',
			'a rule nested in a block' => '{T}.after { color: #f00; & b { color: #00f; } color: #008000; }',
			'an @page margin box with a brace in a string' => '@page { @top-center { content: "}"; } }',
		];
	}

	/**
	 * A document with an element the rule after the construct names and one it does not, in a context
	 *
	 * @param string $context
	 * @param string $css As constructs() gives it
	 *
	 * @return string
	 */
	private function document($context, $css)
	{
		$tag = $context === 'inline' ? 'span' : ($context === 'table cell' ? 'td' : 'p');
		if (strpos($css, '{AFTER}') === false && strpos($css, '{T}.after') === false) {
			$css .= ' {AFTER}';
		}

		$style = '<style>' . str_replace(['{AFTER}', '{T}'], ['{T}.after { color: #008000; }', $tag], $css) . '</style>';
		$html = '<' . $tag . ' class="after">after</' . $tag . '> <' . $tag . '>plain</' . $tag . '>';

		switch ($context) {
			case 'inline':
				return $style . '<p>' . $html . '</p>';

			case 'table cell':
				return $style . '<table><tr>' . $html . '</tr></table>';

			case 'header':
				return $style . '<htmlpageheader name="h">' . $html . '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';

			case 'positioned block':
				return $style . '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">' . $html . '</div>';

			default:
				return $style . $html;
		}
	}
}
