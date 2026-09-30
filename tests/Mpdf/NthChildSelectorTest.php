<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * nth-child rules on table rows and cells shade the cells a browser would, and a rule with an nth-child mPDF cannot
 * match in full is dropped rather than applied to every cell the formula at its start names.
 */
class NthChildSelectorTest extends TestCase
{

	use DrawnStyles;
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
			'a part mPDF cannot match after an nth-child part' => ['tr:nth-child(2) td:hover', 0],
			'a :not() part after an nth-child part' => ['tr:nth-child(2) td:not(.y)', 2],
			'a pseudo-class after the nth-child argument' => ['td:nth-child(2):not(.x)', 0],
			'a pseudo-class after the nth-child argument that the cells match' => ['td:nth-child(2):not(.y)', 3],
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
			'header cells count among the cells' => [
				'td:nth-child(2), th:nth-child(3)',
				'<table><tr><th>h</th><td>X</td><th>Y</th><td>d</td></tr></table>',
				['X', 'Y'],
			],
			'no td is the first cell of a row that starts with a th' => [
				'td:nth-child(1)',
				'<table><tr><th>h</th><td>a</td></tr><tr><td>X</td><th>b</th></tr></table>',
				['X'],
			],
			'no th is the first cell of a row that starts with a td' => [
				'th:first-child',
				'<table><tr><td>a</td><th>b</th></tr><tr><th>X</th><td>c</td></tr></table>',
				['X'],
			],
			'cells whose end tags are left out' => [
				'td:nth-child(2)',
				'<table><tr><td>a<td>X<td>b<tr><td>c<td>Y</table>',
				['X', 'Y'],
			],
			"mPDF's own tags written between the cells" => [
				'td:nth-child(2)',
				'<table><tr><bookmark content="b" /><td>a</td><annotation content="n" /><td>X</td>'
				. '<tocentry content="t" /><indexentry content="i" /><td>b</td></tr></table>',
				['X'],
			],
			"mPDF's own tag written before the first cell" => [
				'td:first-child',
				'<table><tr><bookmark content="b" /><td>X</td><td>a</td></tr></table>',
				['X'],
			],
		];
	}

	/**
	 * Only the text of the rows the rule names is red
	 *
	 * @dataProvider rowRules
	 *
	 * @param string $row A row selector
	 * @param string $table
	 * @param string[] $red The text of the rows the rule names, in the order it is drawn
	 */
	public function testTheRuleCountsTheRowsOfTheRowGroup($row, $table, $red)
	{
		$colours = $this->textColours('<style>' . $row . ' td, ' . $row . ' th { color: #ff0000; }</style>' . $table);

		$this->assertSame($red, array_keys($colours, self::RED, true));
	}

	/**
	 * A row selector, a table for it, and the text of the rows it names
	 *
	 * @return array[]
	 */
	public function rowRules()
	{
		$head = '<thead><tr><th>H1</th></tr><tr><th>H2</th></tr></thead>';
		$body = '<tbody><tr><td>B1</td></tr><tr><td>B2</td></tr></tbody>';
		$foot = '<tfoot><tr><td>F1</td></tr><tr><td>F2</td></tr></tfoot>';

		return [
			'the first row of each tbody' => [
				'tr:first-child',
				'<table><tbody><tr><td>A1</td></tr><tr><td>A2</td></tr></tbody>' . $body . '</table>',
				['A1', 'B1'],
			],
			'the second row of each tbody' => [
				'tr:nth-child(2)',
				'<table><tbody><tr><td>A1</td></tr><tr><td>A2</td></tr><tr><td>A3</td></tr></tbody>' . $body . '</table>',
				['A2', 'B2'],
			],
			'the first rows with the footer before the body' => [
				'tr:first-child',
				'<table>' . $head . $foot . $body . '</table>',
				['H1', 'B1', 'F1'],
			],
			'the first rows with the footer after the body' => [
				'tr:first-child',
				'<table>' . $head . $body . $foot . '</table>',
				['H1', 'B1', 'F1'],
			],
			'the second rows with the footer after the body' => [
				'tr:nth-child(2)',
				'<table>' . $head . $body . $foot . '</table>',
				['H2', 'B2', 'F2'],
			],
			'rows written straight into the table after the header' => [
				'tr:first-child',
				'<table>' . $head . '<tr><td>B1</td></tr><tr><td>B2</td></tr></table>',
				['H1', 'B1'],
			],
			'rows written straight into the table between two tbody' => [
				'tr:first-child',
				'<table>' . $body . '<tr><td>C1</td></tr><tr><td>C2</td></tr><tbody><tr><td>D1</td></tr></tbody></table>',
				['B1', 'C1', 'D1'],
			],
			'a table nested in a header cell' => [
				'tr:nth-child(2)',
				'<table><thead><tr><th><table><tr><td>I1</td></tr><tr><td>I2</td></tr><tr><td>I3</td></tr></table></th></tr>'
				. '<tr><th>H2</th></tr></thead>' . $body . '</table>',
				['I2', 'H2', 'B2'],
			],
			'rows written straight into the table' => [
				'tr:nth-child(2)',
				'<table><tr><td>A1</td></tr><tr><td>A2</td></tr><tr><td>A3</td></tr></table>',
				['A2'],
			],
			'no third row in a table of two' => [
				'tr:nth-child(3)',
				'<table><tr><td>A1</td></tr><tr><td>A2</td></tr></table>',
				[],
			],
			"mPDF's own tag written between the rows" => [
				'tr:nth-child(2)',
				'<table><tr><td>A1</td></tr><bookmark content="b" /><tr><td>A2</td></tr><tr><td>A3</td></tr></table>',
				['A2'],
			],
			'each table counts its own rows' => [
				'tr:nth-child(2)',
				'<table><tr><td>A1</td></tr><tr><td>A2</td></tr></table><table><tr><td>B1</td></tr><tr><td>B2</td></tr></table>',
				['A2', 'B2'],
			],
		];
	}

	/**
	 * A header row repeated at the top of each page is drawn as it is in the header, and does not move the rows of the
	 * body the rule names
	 */
	public function testARepeatedHeaderRowKeepsItsCount()
	{
		$rows = '';
		for ($row = 1; $row <= 60; $row++) {
			$rows .= '<tr><td>Row ' . $row . '</td></tr>';
		}

		$pages = $this->pages($this->render(
			'<style>tr:nth-child(2) td, tr:nth-child(2) th { background-color: #00ff00; }</style>'
			. '<table><thead><tr><th>H1</th></tr><tr><th>H2</th></tr></thead><tbody>' . $rows . '</tbody></table>'
		));

		$this->assertGreaterThan(1, count($pages));
		$this->assertSame(2, substr_count($pages[0], self::FILL));
		for ($page = 1; $page < count($pages); $page++) {
			$this->assertSame(1, substr_count($pages[$page], self::FILL), 'Page ' . ($page + 1));
		}
	}

	/**
	 * A row opened without the end tag of the one before is the next row, and is shaded as it is when the end tags are
	 * written
	 */
	public function testARowWithNoEndTagBeforeIt()
	{
		$css = '<style>tr:nth-child(2) { background-color: #00ff00; }</style>';
		$closed = $this->pages($this->render($css . '<table><tr><td>A1</td></tr><tr><td>B1</td><td>B2</td></tr><tr><td>C1</td><td>C2</td><td>C3</td></tr></table>'));
		$open = $this->pages($this->render($css . '<table><tr><td>A1<tr><td>B1<td>B2<tr><td>C1<td>C2<td>C3</table>'));

		$this->assertSame(3, substr_count($closed[0], self::FILL), 'The second row is shaded across the three columns');
		$this->assertSame($closed, $open);
	}

	/**
	 * The rows of a table that runs onto further pages are counted as they are written, not as they fall on a page
	 */
	public function testARowOnALaterPageKeepsItsCount()
	{
		$rows = '';
		for ($row = 1; $row <= 70; $row++) {
			$rows .= '<tr><td>Row ' . $row . '</td></tr>';
		}

		$red = $this->redText($this->drawDocument('<style>tr:nth-child(20n) td { color: #ff0000; }</style><table>' . $rows . '</table>'));

		$this->assertSame(['Row 20', 'Row 40', 'Row 60'], $red);
	}

	/**
	 * A table counts its rows and cells the same wherever it is written, and the table after it counts its own
	 *
	 * @dataProvider contexts
	 *
	 * @param string $before What comes before the table
	 * @param string $after What comes after the table, closing what $before opens
	 * @param int $pages How many pages the document takes: a page-break-inside: avoid block that does not fit is put
	 *                   back and laid out again on the next page
	 */
	public function testATableCountsItsRowsWhereverItIsWritten($before, $after, $pages)
	{
		$table = '<table><tr><td>A1</td><td>A2</td></tr><tr><td>B1</td><td>B2</td></tr><tr><td>C1</td><td>C2</td></tr></table>';

		$mpdf = $this->drawDocument(
			'<style>tr:nth-child(2) td:first-child, td:nth-child(3) { color: #ff0000; }</style>'
			. $before . $table . $after
			. '<table><tr><td>Y1</td></tr><tr><td>Z1</td><td>Z2</td></tr></table>'
		);

		$red = $this->redText($mpdf);
		sort($red);
		$this->assertSame(['B1', 'Z1'], $red);
		$this->assertCount($pages, $mpdf->pages);
	}

	/**
	 * Markup around a table, before and after it
	 *
	 * @return array[]
	 */
	public function contexts()
	{
		return [
			'the flow' => ['', '', 1],
			'a page header' => ['<htmlpageheader name="h">', '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" />', 1],
			'a page footer' => ['<htmlpagefooter name="f">', '</htmlpagefooter><sethtmlpagefooter name="f" value="on" />', 1],
			'an absolutely positioned block' => ['<div style="position: absolute; top: 150mm; left: 20mm; width: 80mm">', '</div>', 1],
			'a fixed block' => ['<div style="position: fixed; top: 150mm; left: 20mm; width: 80mm">', '</div>', 1],
			'a page-break-inside: avoid block put back and laid out again' => [
				$this->filler(26) . '<div style="page-break-inside: avoid">' . str_repeat('<p>Kept</p>', 3),
				'</div>',
				2,
			],
			'a cell of another table' => ['<table><tr><td>', '</td></tr></table>', 1],
			'a list item' => ['<ul><li>', '</li></ul>', 1],
			'columns' => ['<columns column-count="2" />', '<columns column-count="1" />', 1],
		];
	}

	/**
	 * mPDF lays out a cell or row opened without the end tag of the one before as the next one, even with
	 * allow_html_optional_endtags off, and the rules count it so
	 */
	public function testCellsAndRowsWithNoEndTagWhenOptionalEndTagsAreOff()
	{
		$red = $this->redText($this->drawDocument(
			'<style>td:nth-child(2) { color: #ff0000; }</style><table><tr><td>a<td>X<td>b<tr><td>c<td>Y</table>',
			['allow_html_optional_endtags' => false]
		));

		$this->assertSame(['X', 'Y'], $red);
	}

	/**
	 * The text a document drew in red, in the order it was drawn
	 *
	 * @param TextRecordingMpdf $mpdf A document, written
	 *
	 * @return string[]
	 */
	private function redText(TextRecordingMpdf $mpdf)
	{
		return array_keys($this->keyedByText($mpdf, $mpdf->drawnColours), self::RED, true);
	}

}
