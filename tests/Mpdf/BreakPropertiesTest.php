<?php

namespace Mpdf;

/**
 * break-before, break-after and break-inside act as the page-break-* properties they replace, and a break value that
 * forces no page leaves the block or table where it is.
 */
class BreakPropertiesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A forced break puts the marked text on the page the value names
	 *
	 * @dataProvider providerForcedBreak
	 */
	public function testForcedBreak($html, $pages)
	{
		$this->assertSame($pages, $this->pagesOf($html, ['mirrorMargins' => true]));
	}

	/**
	 * Forced breaks before and after blocks and tables, from inline styles and style sheets
	 *
	 * @return array
	 */
	public function providerForcedBreak()
	{
		return [
			'break-before: page' => ['<p>MA</p><p style="break-before: page">MB</p>', ['MA' => 1, 'MB' => 2]],
			'break-after: page' => ['<p style="break-after: page">MA</p><p>MB</p>', ['MA' => 1, 'MB' => 2]],
			'break-before: right' => ['<p>MA</p><p style="break-before: right">MB</p>', ['MA' => 1, 'MB' => 3]],
			'break-before: left' => ['<p>MA</p><p style="break-before: left">MB</p>', ['MA' => 1, 'MB' => 2]],
			'break-before: recto' => ['<p>MA</p><p style="break-before: recto">MB</p>', ['MA' => 1, 'MB' => 3]],
			'break-before: verso' => ['<p>MA</p><p style="break-before: verso">MB</p>', ['MA' => 1, 'MB' => 2]],
			'break-after: page on a table' => ['<table style="break-after: page"><tr><td>MA</td></tr></table><p>MB</p>', ['MA' => 1, 'MB' => 2]],
			'style sheet' => ['<style>h2 { break-before: page; }</style><p>MA</p><h2>MB</h2>', ['MA' => 1, 'MB' => 2]],
			'a later auto cancels it' => ['<style>h2 { break-before: page; } h2.x { break-before: auto; }</style><p>MA</p><h2 class="x">MB</h2>', ['MA' => 1, 'MB' => 1]],
			'a column break is not a page break' => ['<p>MA</p><p style="break-before: column">MB</p>', ['MA' => 1, 'MB' => 1]],
			'break-after: column on a table' => ['<table style="break-after: column"><tr><td>MA</td></tr></table><p>MB</p>', ['MA' => 1, 'MB' => 1]],
			'break-after: avoid on a table' => ['<table style="break-after: avoid"><tr><td>MA</td></tr></table><p>MB</p>', ['MA' => 1, 'MB' => 1]],
			'page-break-after: auto on a table' => ['<table style="page-break-after: auto"><tr><td>MA</td></tr></table><p>MB</p>', ['MA' => 1, 'MB' => 1]],
		];
	}

	/**
	 * A block that would be split over two pages moves whole to the second when breaks inside it are avoided
	 *
	 * @dataProvider providerBreakInside
	 */
	public function testBreakInside($style, $pages)
	{
		$html = str_repeat('<p>Filler</p>', 26) . '<div style="' . $style . '"><p>MA</p><p>MB</p><p>MC</p></div>';

		$this->assertSame($pages, $this->pagesOf($html));
	}

	/**
	 * The values of break-inside, against a block with none
	 *
	 * @return array
	 */
	public function providerBreakInside()
	{
		return [
			'no break-inside' => ['', ['MA' => 1, 'MB' => 2, 'MC' => 2]],
			'avoid' => ['break-inside: avoid', ['MA' => 2, 'MB' => 2, 'MC' => 2]],
			'avoid-page' => ['break-inside: avoid-page', ['MA' => 2, 'MB' => 2, 'MC' => 2]],
			'avoid-column' => ['break-inside: avoid-column', ['MA' => 1, 'MB' => 2, 'MC' => 2]],
		];
	}

	/**
	 * A break value that forces no page, on a block inside a bordered block, draws the page as if it were not there
	 * rather than closing and reopening the block around it
	 *
	 * @dataProvider providerUnforcedBreak
	 */
	public function testUnforcedBreakBeforeLeavesTheBlockAlone($style)
	{
		$page = function ($style) {
			$mpdf = new Mpdf(['compress' => false]);
			$mpdf->WriteHTML('<div style="border: 1mm solid red; padding: 5mm"><p>One</p><p style="' . $style . '">Two</p></div>');
			$mpdf->Close();

			return $mpdf->pages[1];
		};

		$this->assertSame($page(''), $page($style));
	}

	/**
	 * Values of break-before and page-break-before that do not force a page
	 *
	 * @return array
	 */
	public function providerUnforcedBreak()
	{
		return [
			['break-before: auto'],
			['break-before: avoid'],
			['break-before: avoid-page'],
			['page-break-before: auto'],
			['page-break-before: avoid'],
		];
	}

	/**
	 * Render a document and find the page each piece of text starting with "M" is drawn on
	 *
	 * @param string $html
	 * @param array $config
	 * @return int[] The page of each marker, keyed by the marker
	 */
	private function pagesOf($html, array $config = [])
	{
		$mpdf = new TextRecordingMpdf($config);
		$mpdf->WriteHTML($html);

		$pages = [];
		foreach ($mpdf->drawnText as $i => $text) {
			if (preg_match('/^M[A-Z]$/', trim($text))) {
				$pages[trim($text)] = $mpdf->drawnBoxes[$i][0];
			}
		}

		return $pages;
	}

}
