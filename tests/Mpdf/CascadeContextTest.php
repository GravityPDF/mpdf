<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The standard cascade orders competing rules by specificity and then position wherever the element is: a block, an
 * inline element or a table cell, in a header or footer, in a positioned block, in a block laid out twice because
 * it is kept together, and after a forced page break inside a block.
 */
class CascadeContextTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';

	/**
	 * Each subject is drawn in the colour the rules that win leave it in: the element the heavier or later rule
	 * matches, and a near miss that only the other rule matches
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag and {A} for their ancestors'
	 * @param array[] $groups As cases() gives them
	 */
	public function testTheWinningRuleStylesTheElement($context, $css, array $groups)
	{
		$expected = [];
		foreach ($groups as $group) {
			foreach ($group[1] as $text => $subject) {
				$expected[$text] = $subject[1];
			}
		}

		$this->assertDrawnInColours($expected, $this->drawnColours($this->document($context, $css, $groups), ['cssMode' => 'standard']));
	}

	/**
	 * Every case in every context
	 *
	 * @return array[]
	 */
	public function casesInContexts()
	{
		$data = [];
		foreach (['block', 'inline', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'] as $context) {
			foreach ($this->cases() as $name => $case) {
				$data[$context . ': ' . $name] = [$context, $case[0], $case[1]];
			}
		}

		return $data;
	}

	/**
	 * Rules that compete, with the elements they are matched against
	 *
	 * @return array[] Each [css, groups]: a group is [the attributes of the subjects' ancestors, outermost first, and
	 *                 for each subject keyed by its text, its attributes and the colour it should be drawn in]
	 */
	private function cases()
	{
		return [
			'an id beats a tag with a class' => [
				'#i { color: #008000; } {T}.c { color: #f00; }',
				[[[], ['with the id' => ['id="i" class="c"', self::GREEN], 'without it' => ['class="c"', self::RED]]]],
			],
			'an id beats a descendant rule' => [
				'#i { color: #008000; } {A} {T} { color: #f00; }',
				[[[''], ['with the id' => ['id="i"', self::GREEN], 'without it' => ['', self::RED]]]],
			],
			'a class beats a rule naming body' => [
				'body {T} { color: #f00; } .c { color: #008000; }',
				[[[], ['with the class' => ['class="c"', self::GREEN], 'without it' => ['', self::RED]]]],
			],
			'two classes beat a tag with a class' => [
				'{T}.c { color: #f00; } .c.d { color: #008000; }',
				[[[], ['with both' => ['class="c d"', self::GREEN], 'with one' => ['class="c"', self::RED]]]],
			],
			'a class ancestor beats two nearer tag ancestors' => [
				'.c {T} { color: #008000; } {A} {A} {T} { color: #f00; }',
				[
					[['class="c"', ''], ['under the class' => ['', self::GREEN]]],
					[['', ''], ['under plain ancestors' => ['', self::RED]]],
				],
			],
			'an id ancestor beats two nearer tag ancestors' => [
				'#o {T} { color: #008000; } {A} {A} {T} { color: #f00; }',
				[
					[['id="o"', ''], ['under the id' => ['', self::GREEN]]],
					[['', ''], ['under plain ancestors' => ['', self::RED]]],
				],
			],
			'classes apply in the order they are written' => [
				'.b { color: #008000; } .a { color: #f00; }',
				[[[], ['a then b' => ['class="a b"', self::RED], 'b then a' => ['class="b a"', self::RED], 'b alone' => ['class="b"', self::GREEN]]]],
			],
			'a selector written twice takes each of its places' => [
				'.a { color: #f00; } .b { color: #008000; } .a { color: #00f; }',
				[[[], ['a and b' => ['class="a b"', self::BLUE], 'b alone' => ['class="b"', self::GREEN]]]],
			],
			'a structural pseudo-class counts as a class' => [
				'{T}:first-child { color: #008000; } .c { color: #f00; }',
				[[[''], ['first child' => ['class="c"', self::GREEN], 'second child' => ['class="c"', self::RED]]]],
			],
			'an id beats an attribute selector' => [
				'[data-x] { color: #f00; } #i { color: #008000; }',
				[[[], ['with the id' => ['id="i" data-x="1"', self::GREEN], 'attribute alone' => ['data-x="1"', self::RED]]]],
			],
			':where() counts nothing' => [
				':where(#o) {T} { color: #f00; } {A}.k {T} { color: #008000; }',
				[
					[['id="o" class="k"'], ['under both' => ['', self::GREEN]]],
					[['id="o"'], ['under the id alone' => ['', self::RED]]],
				],
			],
			'the inline style beats an id' => [
				'#i { color: #f00; }',
				[[[], ['styled inline' => ['id="i" style="color: #008000"', self::GREEN], 'id alone' => ['id="i"', self::RED]]]],
			],
		];
	}

	/**
	 * @param string $context
	 *
	 * @return string[] The tag of the subjects in a context, and of their ancestors
	 */
	private function tags($context)
	{
		if ($context === 'inline') {
			return ['em', 'span'];
		}

		return [$context === 'table cell' ? 'td' : 'p', 'div'];
	}

	/**
	 * A document with each group of subjects, in their ancestors, in a context, under a stylesheet naming the tags the
	 * context gives them
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag and {A} for their ancestors'
	 * @param array[] $groups As cases() gives them
	 *
	 * @return string
	 */
	private function document($context, $css, array $groups)
	{
		list($subject, $ancestor) = $this->tags($context);

		$style = '<style>' . str_replace(['{T}', '{A}'], [$subject, $ancestor], $css) . '</style>';
		$html = '';
		foreach ($groups as $group) {
			$inner = '';
			foreach ($group[1] as $text => $attributes) {
				$inner .= '<' . $subject . ' ' . $attributes[0] . '>' . $text . '</' . $subject . '> ';
			}

			if ($context === 'table cell') {
				$inner = '<table><tr>' . $inner . '</tr></table>';
			}

			foreach (array_reverse($group[0]) as $attributes) {
				$inner = '<' . $ancestor . ' ' . $attributes . '>' . $inner . '</' . $ancestor . '>';
			}

			$html .= $context === 'inline' ? '<p>' . $inner . '</p>' : $inner;
		}

		switch ($context) {
			case 'header':
				return $style . '<htmlpageheader name="h">' . $html . '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';

			case 'footer':
				return $style . '<htmlpagefooter name="f">' . $html . '</htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>';

			case 'positioned block':
				return $style . '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">' . $html . '</div>';

			case 'kept block':
				// Too little of the first page is left for the block, which is laid out again on the second
				return $style . str_repeat('<p>filler</p>', 44) . '<div style="page-break-inside: avoid"><p>kept</p>' . $html . '</div>';

			case 'forced page break':
				return $style . '<div class="w"><p>before the break</p><pagebreak />' . $html . '</div>';

			default:
				return $style . $html;
		}
	}

	/**
	 * The kept-block context does lay its block out twice: it starts on the first page, runs over, and is laid out
	 * again from the top of the second
	 */
	public function testTheKeptBlockMovesToTheNextPage()
	{
		$mpdf = $this->drawDocument($this->document('kept block', '.c { color: #f00; }', [[[''], ['kept subject' => ['class="c"'], 'second' => [''], 'third' => ['']]]]), ['cssMode' => 'standard']);
		$pages = $this->keyedByText($mpdf, array_map(function ($box) {
			return $box[0];
		}, $mpdf->drawnBoxes));

		$this->assertSame(2, $pages['kept']);
		$this->assertSame(2, $pages['kept subject']);
	}

	/**
	 * A rule naming the block a forced page break closes and opens again reaches what follows the break inside it,
	 * over a lighter rule, and not what is outside it
	 */
	public function testARuleThroughABlockOpenedAgainAfterAForcedPageBreak()
	{
		$colours = $this->drawnColours('<style>.w p { color: #008000; } div p { color: #f00; }</style>'
			. '<div class="w"><p>before</p><pagebreak /><div><p>after the break</p></div></div><div><p>outside</p></div>', ['cssMode' => 'standard']);

		$this->assertSame(self::GREEN, $colours['before']);
		$this->assertSame(self::GREEN, $colours['after the break']);
		$this->assertSame(self::RED, $colours['outside']);
	}
}
