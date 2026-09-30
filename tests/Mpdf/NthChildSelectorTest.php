<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * nth-child rules on table rows and cells shade the cells a browser would, and a rule with an nth-child mPDF cannot
 * match in full is dropped rather than applied to every cell the formula at its start names.
 */
class NthChildSelectorTest extends TestCase
{

	use PageStreams;

	const FILL = '0.000 1.000 0.000 rg';
	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';

	/**
	 * A table of three rows and two columns in a div with the class d, the second cell of each row with the class x
	 *
	 * @return string
	 */
	private static function table()
	{
		$rows = '';
		for ($row = 1; $row <= 3; $row++) {
			$rows .= '<tr><td>R' . $row . 'C1</td><td class="x">R' . $row . 'C2</td></tr>';
		}

		return '<div class="d"><table>' . $rows . '</table></div>';
	}

	/**
	 * The rule shades as many cells as a browser does, or none where mPDF drops it
	 *
	 * @dataProvider rules
	 *
	 * @param string $selector
	 * @param int $expected How many cells are shaded
	 */
	public function testTheRuleShadesTheCellsItNames($selector, $expected)
	{
		$pages = $this->pages($this->render('<style>' . $selector . ' { background-color: #00ff00; }</style>' . self::table()));

		$this->assertSame($expected, substr_count($pages[0], self::FILL));
	}

	/**
	 * A selector, and how many cells of the table it shades
	 *
	 * @return array[]
	 */
	public function rules()
	{
		return [
			'a formula written with spaces' => ['td:nth-child(2n + 1)', 3],
			'a part mPDF cannot match after an nth-child part' => ['tr:nth-child(2) td:not(.y)', 0],
			'a pseudo-class after the nth-child argument' => ['td:nth-child(2):not(.x)', 0],
			'a second nth-child after the argument' => ['td:nth-child(2):nth-child(odd)', 0],
			'an of selector in the argument' => ['td:nth-child(2 of .x)', 0],
			'the first row' => ['tr:first-child', 2],
			'the first cell of each row' => ['td:first-child', 3],
			'the first cell of the second row' => ['tr:nth-child(2) td:first-child', 1],
			'a descendant rule from the table' => ['table td:nth-child(2)', 3],
			'a descendant rule from the table and the row' => ['table tr:nth-child(odd) td:nth-child(2)', 2],
			'a descendant rule from a block around the table' => ['div.d td:nth-child(2)', 3],
			'a descendant rule from a block around the table, for the row' => ['div.d tr:nth-child(2)', 2],
		];
	}

	/**
	 * Of two nth-child rules that both match a cell, the one later in the stylesheet wins, wherever the rules are stored
	 *
	 * @dataProvider laterRules
	 *
	 * @param string $css
	 * @param string $expected The colour the second cell is drawn in
	 */
	public function testALaterNthChildRuleWins($css, $expected)
	{
		$colours = $this->textColours('<style>' . $css . '</style><div class="d"><table><tr><td>a</td><td>X</td></tr></table></div>');

		$this->assertSame($expected, $colours['X']);
	}

	/**
	 * A stylesheet with two nth-child rules for the second cell, and the colour the later one gives it
	 *
	 * @return array[]
	 */
	public function laterRules()
	{
		// Each stylesheet first names the formulas in a rule that does not style the cell, so the order the stylesheet
		// first uses them differs from the order the node that styles the cell holds them
		$descendant = 'div.d td:nth-child(2) { font-weight: bold; } ';

		return [
			'n, then 2' => [$descendant . 'td:nth-child(n) { color: #ff0000; } td:nth-child(2) { color: #0000ff; }', self::BLUE],
			'2, then n' => [$descendant . 'td:nth-child(2) { color: #0000ff; } td:nth-child(n) { color: #ff0000; }', self::RED],
			'descendant rules, n then 2' => ['div.d td:nth-child(n) { color: #ff0000; } div.d td:nth-child(2) { color: #0000ff; } p td:nth-child(2) { font-weight: bold; }', self::BLUE],
			'descendant rules, 2 then n' => ['p td:nth-child(n) { font-weight: bold; } div.d td:nth-child(2) { color: #0000ff; } div.d td:nth-child(n) { color: #ff0000; }', self::RED],
		];
	}

	/**
	 * Only the text of the cells the rule names is red
	 *
	 * @dataProvider cellRules
	 *
	 * @param string $css
	 * @param string $table
	 * @param string[] $red The text of the cells the rule names, in the order it is drawn
	 */
	public function testTheRuleCountsTheCellsOfTheRow($css, $table, $red)
	{
		$colours = $this->textColours('<style>' . $css . ' { color: #ff0000; }</style>' . $table);

		$this->assertSame($red, array_keys($colours, self::RED, true));
	}

	/**
	 * A cell rule, a table for it, and the text of the cells it names
	 *
	 * @return array[]
	 */
	public function cellRules()
	{
		return [
			'the cell after a colspan is the second' => [
				'td:nth-child(2)',
				'<table><tr><td colspan="2">a</td><td>X</td></tr></table>',
				['X'],
			],
			'no cell after a colspan is the third' => [
				'td:nth-child(3)',
				'<table><tr><td colspan="2">a</td><td>X</td></tr></table>',
				[],
			],
			'the cell beside a rowspan from the row above is the first' => [
				'td:nth-child(1)',
				'<table><tr><td rowspan="2">a</td><td>b</td></tr><tr><td>X</td></tr></table>',
				['a', 'X'],
			],
			'the first cell beside a rowspan from the row above' => [
				'td:first-child',
				'<table><tr><td rowspan="2">a</td><td>b</td></tr><tr><td>X</td><td>c</td></tr></table>',
				['a', 'X'],
			],
			'header, body and footer rows' => [
				'th:nth-child(2), td:nth-child(2)',
				'<table><thead><tr><th colspan="2">h</th><th>X</th></tr></thead>'
				. '<tbody><tr><td>a</td><td>Y</td></tr></tbody>'
				. '<tfoot><tr><td colspan="2">f</td><td>Z</td></tr></tfoot></table>',
				['X', 'Y', 'Z'],
			],
			'the first header and footer cells' => [
				'th:first-child, td:first-child',
				'<table><thead><tr><th>X</th><th>h</th></tr></thead>'
				. '<tbody><tr><td>Y</td><td>b</td></tr></tbody>'
				. '<tfoot><tr><td>Z</td><td>f</td></tr></tfoot></table>',
				['X', 'Y', 'Z'],
			],
			'a table nested in a cell, and the cell after it' => [
				'td:nth-child(3)',
				'<table><tr><td colspan="2">a</td><td><table><tr><td>i</td><td>j</td><td>X</td></tr></table></td><td>Y</td></tr></table>',
				['X', 'Y'],
			],
		];
	}

}
