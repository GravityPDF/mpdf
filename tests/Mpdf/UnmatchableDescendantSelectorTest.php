<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Stylesheet rules with a descendant selector that has a part mPDF cannot match - a combinator, an unsupported
 * pseudo-class, and so on.
 *
 * Such a rule is dropped whole, as a browser drops what it cannot match, rather than cut short at that part and
 * applied to whichever element the parts before it name. A rule mPDF can match is never cut short either.
 */
class UnmatchableDescendantSelectorTest extends TestCase
{

	use PageStreams;

	const RED = '1.000 0.000 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * Each piece of text is drawn in the colour the rule leaves it in
	 *
	 * @dataProvider rules
	 *
	 * @param string $css
	 * @param string $html
	 * @param string $expected The fill colour every piece of text should be drawn in
	 */
	public function testTheRuleColoursOnlyWhatItMatches($css, $html, $expected)
	{
		$colours = $this->textColours('<style>' . $css . '</style>' . $html);

		$this->assertNotEmpty($colours);
		foreach ($colours as $text => $colour) {
			$this->assertSame($expected, $colour, sprintf('"%s" is drawn in the wrong colour', $text));
		}
	}

	/**
	 * A rule, a document for it, and the colour its text should be drawn in
	 *
	 * @return array[]
	 */
	public function rules()
	{
		return [
			'a child combinator after two parts' => [
				'div p > span { color: #ff0000; }',
				'<div><p>paragraph</p></div>',
				self::BLACK,
			],
			'an unsupported pseudo-class after two parts' => [
				'nav ul li:hover { color: #ff0000; }',
				'<nav><ul><li>first</li><li>last</li></ul></nav>',
				self::BLACK,
			],
			'an unmatchable selector beside a valid one in the same rule' => [
				'div p > span, div p { color: #ff0000; }',
				'<div><p>paragraph</p></div>',
				self::RED,
			],
		];
	}

	/**
	 * A rule with two nth-child parts shades the cells both name, not every cell of the rows the first names
	 *
	 * @dataProvider nthChildRules
	 *
	 * @param string $cell The nth-child argument for the cell
	 * @param int $expected How many cells are shaded
	 */
	public function testASecondNthChildNamesTheCell($cell, $expected)
	{
		$pages = $this->pages($this->render(
			'<style>table tr:nth-child(2n) td:nth-child(' . $cell . ') { background-color: #ff0000; }</style>'
			. '<table><tr><td>one</td><td>two</td></tr><tr><td>three</td><td>four</td></tr></table>'
		));

		$this->assertSame($expected, substr_count($pages[0], self::RED));
	}

	/**
	 * The nth-child argument for the cell, and how many cells of a two by two table that shades
	 *
	 * @return array[]
	 */
	public function nthChildRules()
	{
		return [
			'a column the table has' => ['2', 1],
			'a column the table does not have' => ['3', 0],
		];
	}

}
