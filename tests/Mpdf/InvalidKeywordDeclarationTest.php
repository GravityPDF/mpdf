<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Declarations with a keyword the property does not take, such as width: banana or text-align: banana.
 *
 * A browser drops such a declaration, so an earlier declaration of the property in the same block still applies.
 * mPDF drops it the same way, in stylesheets and style attributes, in either CSS mode.
 */
class InvalidKeywordDeclarationTest extends TestCase
{

	/**
	 * The page's left margin, where a paragraph with no margin of its own starts
	 */
	const LEFT = 15.0;

	/**
	 * The left and right edges of the text are where the earlier declaration puts them
	 *
	 * @dataProvider edges
	 *
	 * @param string $mode
	 * @param string $html
	 * @param int $edge 1 for the left edge of the text, 2 for the right
	 * @param float $expected In millimetres from the left of the page
	 */
	public function testTheEarlierDeclarationPlacesTheText($mode, $html, $edge, $expected)
	{
		$mpdf = $this->draw($mode, $html);

		$this->assertEqualsWithDelta($expected, $mpdf->drawnBoxes[0][$edge], 0.01);
	}

	/**
	 * Each document in both CSS modes, with the edge of its text to measure and where it should be
	 *
	 * @return array[]
	 */
	public function edges()
	{
		$cases = [
			'margin-left in a style attribute' => ['<p style="margin-left: 40mm; margin-left: banana">text</p>', 1, self::LEFT + 40],
			'margin-left in a stylesheet' => ['<style>p { margin-left: 40mm; margin-left: banana }</style><p>text</p>', 1, self::LEFT + 40],
			'a margin with a word that is not a length' => ['<p style="margin: 0 0 0 40mm; margin: 0 banana">text</p>', 1, self::LEFT + 40],
			'width in a style attribute' => ['<div style="width: 50mm; width: banana; text-align: right">text</div>', 2, self::LEFT + 50],
			'width in a stylesheet' => ['<style>.w { width: 50mm; width: banana }</style><div class="w" style="text-align: right">text</div>', 2, self::LEFT + 50],
		];

		return $this->inBothModes($cases);
	}

	/**
	 * text-align: banana leaves the text aligned right
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testAnAlignmentThatIsNotOneLeavesTheEarlierOne($mode)
	{
		$mpdf = $this->draw($mode, '<p style="text-align: right; text-align: banana">text</p>');

		$this->assertGreaterThan(150, $mpdf->drawnBoxes[0][1]);
	}

	/**
	 * font-size: banana leaves the text at 30pt, and font: banana leaves the earlier font shorthand
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testASizeThatIsNotOneLeavesTheEarlierOne($mode)
	{
		$mpdf = $this->draw($mode, '<p style="font-size: 30pt; font-size: banana">a</p><p style="font: 20pt serif; font: banana">b</p>');

		$this->assertEquals([30, 20], $mpdf->drawnFontSize);
	}

	/**
	 * A border with a word that is neither a width, a style nor a colour leaves the earlier border
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testABorderWithAWordItDoesNotTakeLeavesTheEarlierOne($mode)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => $mode]);
		$mpdf->SetCompression(false);
		$mpdf->WriteHTML('<div style="border: 1mm solid #ff0000; border: 2mm banana #0000ff">text</div>');

		$pdf = $mpdf->Output('', 'S');

		$this->assertStringContainsString('1.000 0.000 0.000 RG', $pdf);
		$this->assertStringNotContainsString('0.000 0.000 1.000 RG', $pdf);
	}

	/**
	 * A keyword the property takes still replaces the earlier declaration
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testAKeywordThePropertyTakesStillApplies($mode)
	{
		$mpdf = $this->draw($mode, '<p style="text-align: banana; text-align: right; text-align: left">text</p><p style="font-size: 30pt; font-size: medium">a</p>');

		$this->assertEqualsWithDelta(self::LEFT, $mpdf->drawnBoxes[0][1], 0.01);
		$this->assertEquals(11, $mpdf->drawnFontSize[1]);
	}

	/**
	 * Each CSS mode
	 *
	 * @return array[]
	 */
	public function modes()
	{
		return [CssMode::STANDARD => [CssMode::STANDARD], CssMode::LEGACY => [CssMode::LEGACY]];
	}

	/**
	 * Draws a document in a CSS mode, recording its text
	 *
	 * @param string $mode
	 * @param string $html
	 *
	 * @return TextRecordingMpdf
	 */
	private function draw($mode, $html)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => $mode]);
		$mpdf->WriteHTML($html);

		return $mpdf;
	}

	/**
	 * Each case once in each CSS mode, with the mode first
	 *
	 * @param array[] $cases
	 *
	 * @return array[]
	 */
	private function inBothModes(array $cases)
	{
		$rows = [];
		foreach ($cases as $name => $case) {
			foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
				$rows[$name . ' (' . $mode . ')'] = array_merge([$mode], $case);
			}
		}

		return $rows;
	}

}
