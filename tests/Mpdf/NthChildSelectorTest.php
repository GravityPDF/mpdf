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

	/**
	 * A table of three rows and two columns, the second cell of each row with the class x
	 *
	 * @return string
	 */
	private static function table()
	{
		$rows = '';
		for ($row = 1; $row <= 3; $row++) {
			$rows .= '<tr><td>R' . $row . 'C1</td><td class="x">R' . $row . 'C2</td></tr>';
		}

		return '<table>' . $rows . '</table>';
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
		];
	}

}
