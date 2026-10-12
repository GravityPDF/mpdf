<?php

namespace Mpdf;

class SplitTableBackgroundTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A table's background image from a URL has no gradient, and the background drawn again on each later page of a
	 * table split across pages must not expect one: the image is tiled on every page and nothing is raised.
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testASplitTableWithABackgroundImageDrawsItOnEveryPageWithoutWarnings($cssMode)
	{
		$html = '<table style="background-image: url(' . $this->backgroundImage() . ')">'
			. str_repeat('<tr><td>Row</td><td>Row</td></tr>', 80)
			. '</table>';

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
	 * Each CSS mode
	 *
	 * @return array[]
	 */
	public function cssModes()
	{
		return [
			CssMode::STANDARD => [CssMode::STANDARD],
			CssMode::LEGACY => [CssMode::LEGACY],
		];
	}

}
