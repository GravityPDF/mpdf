<?php

namespace Mpdf;

class RepeatedTableHeaderBackgroundTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A header or footer drawn again on a later page read the gradient of its background image without checking for
	 * one, and an image from a URL has none. Its image is still tiled on every page.
	 *
	 * @dataProvider backgrounds
	 *
	 * @param string $cssMode
	 * @param string $thead
	 * @param string $tfoot
	 */
	public function testARepeatedHeaderOrFooterWithABackgroundImageDrawsItOnEveryPageWithoutWarnings($cssMode, $thead, $tfoot)
	{
		$html = '<table repeat_header="1"><thead>' . $thead . '</thead><tfoot>' . $tfoot . '</tfoot><tbody>'
			. str_repeat('<tr><td>Row</td><td>Row</td></tr>', 80)
			. '</tbody></table>';

		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $cssMode]);

		$pages = $this->pageContents($pdf);
		$this->assertGreaterThan(1, count($pages));
		foreach ($pages as $page) {
			$this->assertStringContainsString('/Pattern cs', $page);
		}
	}

	/**
	 * A background image on a header cell, a header row and a footer row, in each CSS mode
	 *
	 * @return array[]
	 */
	public function backgrounds()
	{
		$image = 'background-image: url(' . __DIR__ . '/../data/img/bg.jpg)';
		$plainRow = '<tr><td>Plain</td><td>Plain</td></tr>';
		$cases = [
			'a header cell' => ['<tr><th style="' . $image . '">Cell</th><th>Plain</th></tr>', $plainRow],
			'a header row' => ['<tr style="' . $image . '"><th>Row</th><th>Row</th></tr>', $plainRow],
			'a footer cell' => [$plainRow, '<tr><td style="' . $image . '">Cell</td><td>Plain</td></tr>'],
			'a footer row' => [$plainRow, '<tr style="' . $image . '"><td>Row</td><td>Row</td></tr>'],
		];

		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
			foreach ($cases as $name => $case) {
				$data[$cssMode . ', ' . $name] = array_merge([$cssMode], $case);
			}
		}

		return $data;
	}

}
