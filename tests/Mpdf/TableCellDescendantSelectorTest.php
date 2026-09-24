<?php

namespace Mpdf;

/**
 * A descendant rule naming a table, row or cell reaches the content of the cell, inline and block alike.
 */
class TableCellDescendantSelectorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The first page of a document of the given CSS and body
	 *
	 * @param string $css
	 * @param string $html
	 *
	 * @return string
	 */
	private function page($css, $html)
	{
		return $this->pages($this->render('<style>' . $css . '</style>' . $html))[0];
	}

	/**
	 * A table of one cell holding the given content, in a div
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	private function table($content)
	{
		return '<div class="d"><table class="t"><tr class="r"><td class="c">' . $content . '</td></tr></table></div>';
	}

	/**
	 * A picture 292px wide, 77.3mm at 96dpi
	 *
	 * @return string
	 */
	private function image()
	{
		return '<img class="x" src="' . __DIR__ . '/../data/img/bayeux2.jpg">';
	}

	/**
	 * The width the only picture on the page is drawn at, in millimetres
	 *
	 * @param string $page
	 *
	 * @return float
	 */
	private function imageWidth($page)
	{
		$this->assertSame(1, preg_match('/([\d.]+) 0 0 [\d.]+ [\d.]+ [\d.]+ cm \/I\d+ Do/', $page, $matches));

		return $matches[1] / Mpdf::SCALE;
	}

	/**
	 * Rules that should reach a picture in the cell
	 *
	 * @return array[]
	 */
	public static function matchingRules()
	{
		return [
			'cell and tag' => ['td img'],
			'the whole chain' => ['table tr td img'],
			'classes' => ['.t .r .c .x'],
			'cell class and tag' => ['td.c img'],
			'a block outside the table' => ['div.d img'],
		];
	}

	/**
	 * @dataProvider matchingRules
	 *
	 * @param string $selector
	 */
	public function testARuleReachesAPictureInTheCell($selector)
	{
		$page = $this->page($selector . ' { max-width: 20mm; }', $this->table($this->image()));

		$this->assertEqualsWithDelta(20, $this->imageWidth($page), 0.01);
	}

	/**
	 * A rule for a cell of another class leaves the picture alone
	 */
	public function testARuleForAnotherCellDoesNotMatch()
	{
		$page = $this->page('td.other img { max-width: 20mm; }', $this->table($this->image()));

		$this->assertEqualsWithDelta(292 * 25.4 / 96, $this->imageWidth($page), 0.01);
	}

	/**
	 * A rule reaches a picture in a table nested in the cell
	 */
	public function testARuleReachesIntoANestedTable()
	{
		$page = $this->page('td.c img { max-width: 20mm; }', $this->table('<table><tr><td>' . $this->image() . '</td></tr></table>'));

		$this->assertEqualsWithDelta(20, $this->imageWidth($page), 0.01);
	}

	/**
	 * Rules that should colour text in the cell red
	 *
	 * @return array[]
	 */
	public static function textRules()
	{
		return [
			'inline' => ['td span', '<span>red</span>'],
			'block' => ['td p', '<p>red</p>'],
		];
	}

	/**
	 * @dataProvider textRules
	 *
	 * @param string $selector
	 * @param string $content
	 */
	public function testARuleReachesTextInTheCell($selector, $content)
	{
		$page = $this->page($selector . ' { color: #ff0000; }', $this->table($content));

		$this->assertStringContainsString('1.000 0.000 0.000 rg', $page);
	}

}
