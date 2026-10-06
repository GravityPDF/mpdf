<?php

namespace Mpdf;

/**
 * A cell, row or row group whose end tag is left out ends where the next one starts, and the descendant rules that
 * matched it end with it: they do not reach the rows and cells after it.
 */
class OmittedTableEndTagTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const RED = '1.000 0.000 0.000 rg';

	const BLACK = '0.000 g';

	/**
	 * The configuration the documents are written with: the legacy cascade. A subclass runs the tests under the
	 * standard one
	 *
	 * @return array
	 */
	protected function config()
	{
		return ['cssMode' => CssMode::LEGACY];
	}

	/**
	 * Tables of three rows, B the second, written with and without the optional end tags
	 *
	 * @return array[]
	 */
	public static function rows()
	{
		return [
			'every end tag' => ['<table><tr><td>A1</td><td>A2</td></tr><tr><td>B1</td><td>B2</td></tr><tr><td>C1</td><td>C2</td></tr></table>'],
			'no row end tags' => ['<table><tr><td>A1</td><td>A2</td><tr><td>B1</td><td>B2</td><tr><td>C1</td><td>C2</td></table>'],
			'no cell end tags' => ['<table><tr><td>A1<td>A2</tr><tr><td>B1<td>B2</tr><tr><td>C1<td>C2</tr></table>'],
			'no cell or row end tags' => ['<table><tr><td>A1<td>A2<tr><td>B1<td>B2<tr><td>C1<td>C2</table>'],
			'header cells' => ['<table><tr><th>A1<th>A2<tr><th>B1<th>B2<tr><th>C1<th>C2</table>'],
			'in a tbody' => ['<table><tbody><tr><td>A1<td>A2<tr><td>B1<td>B2<tr><td>C1<td>C2</tbody></table>'],
			'in a div' => ['<div><table><tr><td>A1<td>A2<tr><td>B1<td>B2<tr><td>C1<td>C2</table></div>'],
			'a block in the cells' => ['<table><tr><td>A1<td><p>A2</p><tr><td>B1<td><p>B2</p><tr><td>C1<td><p>C2</p></table>'],
			'a nested table with no tbody end tag' => [
				'<table><tr><td>A1<td>A2<tr><td>B1<td>B2<table><tbody><tr><td>inner</td></tr></table><tr><td>C1<td>C2</table>',
			],
		];
	}

	/**
	 * @dataProvider rows
	 *
	 * @param string $table
	 */
	public function testARuleOnTheSecondRowColoursOnlyItsCells($table)
	{
		$colours = $this->textColours('<style>tr:nth-child(2) td, tr:nth-child(2) th { color: #ff0000; }</style>' . $table, $this->config());
		unset($colours['inner']);

		$this->assertSame(
			['A1' => self::BLACK, 'A2' => self::BLACK, 'B1' => self::RED, 'B2' => self::RED, 'C1' => self::BLACK, 'C2' => self::BLACK],
			$colours
		);
	}

	/**
	 * Tables of a row group of class a, holding A, followed by a tbody, holding B, written with and without the
	 * optional end tags
	 *
	 * @return array[]
	 */
	public static function rowGroups()
	{
		return [
			'every end tag' => ['<table><tbody class="a"><tr><td>A</td></tr></tbody><tbody><tr><td>B</td></tr></tbody></table>'],
			'no tbody end tag' => ['<table><tbody class="a"><tr><td>A</td></tr><tbody><tr><td>B</td></tr></table>'],
			'no end tags' => ['<table><tbody class="a"><tr><td>A<tbody><tr><td>B</table>'],
			'no thead end tag' => ['<table><thead class="a"><tr><td>A</td></tr><tbody><tr><td>B</td></tr></table>'],
			'no thead, row or cell end tags' => ['<table><thead class="a"><tr><td>A<tbody><tr><td>B</table>'],
			'no tfoot end tag' => ['<table><tfoot class="a"><tr><td>A</td></tr><tbody><tr><td>B</td></tr></table>'],
		];
	}

	/**
	 * @dataProvider rowGroups
	 *
	 * @param string $table
	 */
	public function testARuleOnARowGroupColoursOnlyItsCells($table)
	{
		$colours = $this->textColours('<style>.a td { color: #ff0000; }</style>' . $table, $this->config());

		$this->assertSame(self::RED, $colours['A']);
		$this->assertSame(self::BLACK, $colours['B']);
	}

	/**
	 * A row of a thead that starts where the row before it, with no end tags, ends is in the thead too, and repeats
	 * at the top of each page with it
	 */
	public function testEveryRowOfATheadRepeats()
	{
		$html = '<div><table><thead><tr><th>Hone<tr><th>Htwo</thead><tbody>' . str_repeat('<tr><td>body</td></tr>', 120) . '</tbody></table></div>';
		$pages = $this->pages($this->render($html, $this->config()));

		$this->assertGreaterThan(1, count($pages));
		foreach ($pages as $i => $page) {
			$this->assertTextCount(1, 'Htwo', $page, 'Page ' . ($i + 1));
		}
	}

}
