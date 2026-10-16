<?php

namespace Mpdf;

class AdjustHtmlTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf(['use_kwt' => true]);
	}

	public function testAdjustHtmlWithLongStringWithoutHeadingFollowedByTable()
	{
		$html = file_get_contents(__DIR__.'/../data/html/long-string-without-kwt-match.html');
		$adjustedHtml = $this->mpdf->AdjustHTML($html);

		$this->assertNotEmpty($adjustedHtml);
	}

	public function testAdjustHtmlWithLongStringWithHeadingFollowedByTable()
	{
		$html = file_get_contents(__DIR__.'/../data/html/long-string-with-kwt-match.html');
		$adjustedHtml = $this->mpdf->AdjustHTML($html);

		$this->assertNotEmpty($adjustedHtml);
		$this->assertStringContainsString('<h1 keep-with-table="1">', $adjustedHtml);
		$this->assertStringContainsString('<h2 class="name" keep-with-table="1">', $adjustedHtml);
		$this->assertStringContainsString('<h3 keep-with-table="1">', $adjustedHtml);
		$this->assertStringContainsString('<h4 style="color: #CCC" keep-with-table="1">', $adjustedHtml);
		$this->assertStringContainsString('<h5 align="center" style="color: red" keep-with-table="1">', $adjustedHtml);
		$this->assertStringContainsString('<h6 class="name" keep-with-table="1">', $adjustedHtml);
	}

	/**
	 * A thead or tfoot holding only empty rows is removed with them, and so is a table left empty
	 */
	public function testSectionsLeftEmptyByEmptyRowsAreRemoved()
	{
		$html = '<table><thead><tr></tr></thead><tbody><tr><td>x</td></tr></tbody><tfoot> <tr> </tr> </tfoot></table>'
			. '<table><thead><tr> </tr></thead></table>';

		$this->assertSame('<table><tbody><tr><td>x</td></tr></tbody></table>', $this->mpdf->AdjustHTML($html));
	}
}
