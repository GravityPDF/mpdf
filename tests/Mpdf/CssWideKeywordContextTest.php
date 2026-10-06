<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The CSS-wide keywords inherit, initial, unset, revert and revert-layer on an inherited property (color, font-size)
 * and on properties that are not inherited (background-color, border, padding), in each context an element can be
 * written in: a block, an inline element, a table cell and a nested table's cell, a list item, a header and a footer,
 * a positioned block, a block laid out again because it is kept together, and a block a forced page break splits.
 *
 * Each case compares two documents, one with the keyword and one with the value it should resolve to written in its
 * place, which must draw the same pages. The standard CSS mode resolves the keywords as a browser does. The legacy
 * mode reads them as it always has.
 */
class CssWideKeywordContextTest extends TestCase
{

	use DrawnStyles;

	/**
	 * For each property the tests cover: the value the subject's ancestor sets, the value a rule for the subject's tag
	 * sets, and its initial value, as a declaration would write them
	 */
	const VALUES = [
		'color' => ['#008000', '#ff0000', '#000000'],
		'font-size' => ['20pt', '8pt', '11pt'],
		'background-color' => ['#00ff00', '#0000ff', 'transparent'],
		'border' => ['1mm solid #00ff00', '0.5mm dashed #0000ff', 'none'],
		'padding' => ['6mm', '2mm', '0'],
	];

	/**
	 * The values the built-in defaults give the subjects, by tag and property
	 */
	const DEFAULTS = [
		'td' => ['padding' => '0.1em'],
	];

	/**
	 * The keyword draws what the value it resolves to draws, in each context. A subject beside it with no keyword
	 * keeps its tag's rule
	 *
	 * @dataProvider keywordsInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $property
	 * @param string $keyword
	 * @param string|null $resolved The value the keyword should draw as, or null for as if it were not declared
	 */
	public function testTheKeywordDrawsAsTheValueItResolvesTo($mode, $context, $property, $keyword, $resolved)
	{
		$this->assertSame(
			$this->drawnPages($this->document($context, $property, $resolved), ['cssMode' => $mode]),
			$this->drawnPages($this->document($context, $property, $keyword), ['cssMode' => $mode])
		);
	}

	/**
	 * Under the standard mode, a keyword draws something other than its tag's rule, so each case above tests the
	 * keyword and not a property the context never draws
	 *
	 * @dataProvider standardKeywordsInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $property
	 * @param string $keyword
	 */
	public function testTheKeywordOverridesItsTagsRule($mode, $context, $property, $keyword)
	{
		$this->assertNotSame(
			$this->drawnPages($this->document($context, $property, null), ['cssMode' => $mode]),
			$this->drawnPages($this->document($context, $property, $keyword), ['cssMode' => $mode])
		);
	}

	/**
	 * Each context draws its subject where the context puts it, for every property: in the header, the footer or the
	 * positioned block, on the second page after a forced page break or once the kept block has been put back and laid
	 * out again, and in the flow of the first page otherwise
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testEachContextDrawsItsSubject($mode)
	{
		foreach (array_keys($this->contexts()) as $context) {
			foreach (array_keys(self::VALUES) as $property) {
				$mpdf = $this->drawDocument($this->document($context, $property, 'inherit'), ['cssMode' => $mode]);
				$mpdf->OutputBinaryData();

				$this->assertDrawnInContext($context, $mpdf, ['subject']);
			}
		}
	}

	/**
	 * Every keyword, for each property, in every context, under each mode
	 *
	 * @return array[] Each [mode, context, property, keyword, the value it should draw as]
	 */
	public function keywordsInContexts()
	{
		$rows = [];
		foreach (array_keys($this->contexts()) as $context) {
			foreach (array_keys(self::VALUES) as $property) {
				// mPDF draws no padding on an inline element
				if ($context === 'inline' && $property === 'padding') {
					continue;
				}

				foreach (['inherit', 'initial', 'unset', 'revert', 'revert-layer'] as $keyword) {
					$rows[$context . ', ' . $property . ': ' . $keyword] = [
						$context,
						$property,
						$keyword,
						$this->standardValue($context, $property, $keyword),
						$this->legacyValue($property, $keyword),
					];
				}
			}
		}

		return $this->bothModes($rows);
	}

	/**
	 * The standard cases of keywordsInContexts()
	 *
	 * @return array[]
	 */
	public function standardKeywordsInContexts()
	{
		return array_filter($this->keywordsInContexts(), function ($case) {
			return $case[0] === CssMode::STANDARD;
		});
	}

	/**
	 * What a keyword resolves to under the standard mode: inherit takes the ancestor's value, which the cases set on
	 * the subject's parent, and on the table as well as the row for a cell; initial the initial value; unset the one or
	 * the other as the property is inherited or not; and revert the built-in default, or unset where there is none
	 *
	 * @param string $context
	 * @param string $property
	 * @param string $keyword
	 *
	 * @return string
	 */
	private function standardValue($context, $property, $keyword)
	{
		list($ancestor, , $initial) = self::VALUES[$property];
		$inherited = in_array($property, ['color', 'font-size'], true);
		$tag = $this->contexts()[$context][0];

		$defaults = self::DEFAULTS;
		if ($keyword === 'revert' || $keyword === 'revert-layer') {
			if (isset($defaults[$tag][$property])) {
				return $defaults[$tag][$property];
			}
			$keyword = 'unset';
		}

		if ($keyword === 'unset') {
			$keyword = $inherited ? 'inherit' : 'initial';
		}

		return $keyword === 'inherit' ? $ancestor : $initial;
	}

	/**
	 * What a keyword draws as under the legacy mode, as measured: a colour keyword mPDF reads sets no colour, so the
	 * text keeps the colour in force, as currentColor does, which the legacy mode ignores in color; one it does not
	 * read (revert) is dropped. A background colour drawn as none covers a row's, as currentColor does, where
	 * transparent lets it show through. A font size or padding keyword sets none. A border keyword draws
	 * no border, except revert-layer, which is dropped
	 *
	 * @param string $property
	 * @param string $keyword
	 *
	 * @return string|null
	 */
	private function legacyValue($property, $keyword)
	{
		$dropped = ['color' => ['revert', 'revert-layer'], 'background-color' => ['revert', 'revert-layer'], 'border' => ['revert-layer']];
		if (isset($dropped[$property]) && in_array($keyword, $dropped[$property], true)) {
			return null;
		}

		$values = ['color' => 'currentcolor', 'background-color' => 'currentcolor', 'font-size' => 'auto', 'border' => 'none', 'padding' => '0'];

		return $values[$property];
	}

	/**
	 * The contexts the subjects are written in. {S} stands for the subjects, one with the class k and one without,
	 * and the class a marks the ancestor the value is inherited from: for a cell, both its row and its table
	 *
	 * @return array[] Each [the subjects' tag, the document]
	 */
	private function contexts()
	{
		return [
			'block' => ['p', '<div class="a">{S}</div>'],
			'inline' => ['em', '<p class="a">text {S}</p>'],
			'table cell' => ['td', '<table class="a"><tr class="a">{S}</tr></table>'],
			'nested table cell' => ['td', '<table><tr><td>outer<table class="a"><tr class="a">{S}</tr></table></td></tr></table>'],
			'list item' => ['li', '<ul class="a">{S}</ul>'],
			'header' => ['p', '<htmlpageheader name="h"><div class="a">{S}</div></htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>'],
			'footer' => ['p', '<htmlpagefooter name="f"><div class="a">{S}</div></htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>'],
			'positioned block' => ['p', '<div class="a" style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">{S}</div>'],
			'kept block' => ['p', str_repeat('<p>filler</p>', 27) . '<div class="a" style="page-break-inside: avoid"><h6>kept</h6>{S}</div>'],
			'forced page break' => ['p', '<div class="a"><h6>before</h6><pagebreak />{S}</div>'],
		];
	}

	/**
	 * A document in a context whose subject with the class k takes a value, or a keyword, over the rule for its tag
	 *
	 * @param string $context
	 * @param string $property
	 * @param string|null $value Null for no declaration
	 *
	 * @return string
	 */
	private function document($context, $property, $value)
	{
		list($tag, $html) = $this->contexts()[$context];
		list($ancestor, $rule) = self::VALUES[$property];

		$css = '.a { ' . $property . ': ' . $ancestor . '; } .a ' . $tag . ' { ' . $property . ': ' . $rule . '; }';
		if ($value !== null) {
			$css .= ' .a .k { ' . $property . ': ' . $value . '; }';
		}
		$subjects = '<' . $tag . ' class="k">subject</' . $tag . '><' . $tag . '>near miss</' . $tag . '>';

		return '<style>' . $css . '</style>' . str_replace('{S}', $subjects, $html);
	}
}
