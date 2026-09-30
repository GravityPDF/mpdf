<?php

namespace Mpdf;

/**
 * A descendant rule naming a table, row or cell reaches the content of the cell, inline and block alike.
 */
class TableCellDescendantSelectorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const RED = '1.000 0.000 0.000 rg';

	/**
	 * The configuration the documents are written with: the legacy cascade. A subclass runs the tests under the
	 * standard one
	 *
	 * @return array
	 */
	protected function config()
	{
		return ['cssCascade' => 'legacy'];
	}

	/**
	 * A table of one cell holding the given content, in a div, under the given stylesheet
	 *
	 * @param string $css
	 * @param string $content
	 *
	 * @return string
	 */
	private function table($css, $content)
	{
		return '<style>' . $css . '</style><div class="d"><table class="t"><tr class="r"><td class="c">' . $content . '</td></tr></table></div>';
	}

	/**
	 * A picture 292px wide, 77.3mm at 96dpi
	 *
	 * @return string
	 */
	private static function image()
	{
		return '<img class="x" src="' . __DIR__ . '/../data/img/bayeux2.jpg">';
	}

	/**
	 * Rules that should reach a picture in the cell, and the cell content holding it
	 *
	 * @return array[]
	 */
	public static function matchingRules()
	{
		return [
			'cell and tag' => ['td img', self::image()],
			'the whole chain' => ['table tr td img', self::image()],
			'classes' => ['.t .r .c .x', self::image()],
			'cell class and tag' => ['td.c img', self::image()],
			'a block outside the table' => ['div.d img', self::image()],
			'a table nested in the cell' => ['td.c img', '<table><tr><td>' . self::image() . '</td></tr></table>'],
		];
	}

	/**
	 * @dataProvider matchingRules
	 *
	 * @param string $selector
	 * @param string $content
	 */
	public function testARuleReachesAPictureInTheCell($selector, $content)
	{
		$this->assertEqualsWithDelta(20, $this->drawnWidth($this->table($selector . ' { max-width: 20mm; }', $content), $this->config()), 0.01);
	}

	/**
	 * A rule for a cell of another class leaves the picture alone
	 */
	public function testARuleForAnotherCellDoesNotMatch()
	{
		$width = $this->drawnWidth($this->table('td.other img { max-width: 20mm; }', self::image()), $this->config());

		$this->assertEqualsWithDelta(292 * 25.4 / 96, $width, 0.01);
	}

	/**
	 * Rules that should colour the word "red" in the cell
	 *
	 * @return array[]
	 */
	public static function textRules()
	{
		return [
			'inline' => ['td span', 'plain <span>red</span>'],
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
		$colours = $this->textColours($this->table($selector . ' { color: #ff0000; }', $content), $this->config());

		$this->assertSame(self::RED, $colours['red']);
	}

}
