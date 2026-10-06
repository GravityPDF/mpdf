<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Stylesheet rules that name sup, sub or center apply to them, in both CSS modes, and a rule on sup or sub takes over
 * from the size and raise mPDF gives them by default rather than adding to them. mPDF v7 left the three tags out of
 * allowedCSStags, so a rule naming them was ignored.
 */
class SupSubRulesTest extends TestCase
{

	use DrawnStyles;

	/**
	 * The default 55% of a 20pt paragraph
	 */
	const DEFAULT_SIZE = 20 * 0.55;

	/**
	 * How far sup is raised by default in a 20pt paragraph, in mm: half the paragraph's size, Mpdf::$baselineSup
	 */
	const RAISE = 20 * 0.5 * 25.4 / 72;

	/**
	 * How far sub is dropped by default in a 20pt paragraph, in mm: a fifth of the paragraph's size, Mpdf::$baselineSub
	 */
	const DROP = 20 * -0.2 * 25.4 / 72;

	/**
	 * A rule naming sup or sub colours its text, through a descendant selector too
	 *
	 * @dataProvider colourRules
	 *
	 * @param string $css
	 * @param string $mode
	 */
	public function testAColourRuleApplies($css, $mode)
	{
		$colours = $this->drawnColours($this->document($css), ['cssMode' => $mode]);

		$this->assertDrawnInColours(['up' => '1.000 0.000 0.000 rg', 'down' => '1.000 0.000 0.000 rg', 'x' => '0.000 g'], $colours);
	}

	/**
	 * The child selector is left out under legacy, which reads no child selectors at all
	 *
	 * @return array[]
	 */
	public function colourRules()
	{
		return [
			'type selector, standard' => ['sup, sub { color: #f00 }', CssMode::STANDARD],
			'type selector, legacy' => ['sup, sub { color: #f00 }', CssMode::LEGACY],
			'descendant selector, standard' => ['p sup, p sub { color: #f00 }', CssMode::STANDARD],
			'descendant selector, legacy' => ['p sup, p sub { color: #f00 }', CssMode::LEGACY],
			'child selector, standard' => ['p > sup, p > sub { color: #f00 }', CssMode::STANDARD],
		];
	}

	/**
	 * A font-size rule takes the place of the 55% mPDF draws sup and sub at, and leaves the raise and drop alone
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testAFontSizeRuleReplacesTheBuiltInSize($mode)
	{
		$mpdf = $this->draw('sup, sub { font-size: 75% }', $mode);

		$this->assertDrawn($mpdf, 'up', 15, self::RAISE);
		$this->assertDrawn($mpdf, 'down', 15, self::DROP);
	}

	/**
	 * vertical-align: baseline puts the text back on the baseline, at the built-in size
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testVerticalAlignBaselineRemovesTheRaise($mode)
	{
		$mpdf = $this->draw('sup, sub { vertical-align: baseline }', $mode);

		$this->assertDrawn($mpdf, 'up', self::DEFAULT_SIZE, 0);
		$this->assertDrawn($mpdf, 'down', self::DEFAULT_SIZE, 0);
	}

	/**
	 * A vertical-align rule moves the text once, by its own amount, not on top of the built-in raise
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testVerticalAlignReplacesTheBuiltInRaise($mode)
	{
		$mpdf = $this->draw('sup { vertical-align: sub } sub { vertical-align: super }', $mode);

		$this->assertDrawn($mpdf, 'up', self::DEFAULT_SIZE, self::DROP);
		$this->assertDrawn($mpdf, 'down', self::DEFAULT_SIZE, self::RAISE);
	}

	/**
	 * normalize.css's rule for sub and sup draws them at 75% of the paragraph's size, on its baseline
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testNormalizeCssDrawsThemOnTheBaseline($mode)
	{
		$css = 'sub, sup { font-size: 75%; line-height: 0; position: relative; vertical-align: baseline }';
		$mpdf = $this->draw($css, $mode);

		$this->assertDrawn($mpdf, 'up', 15, 0);
		$this->assertDrawn($mpdf, 'down', 15, 0);
	}

	/**
	 * A rule naming sup or sub applies in a table cell too
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testARuleAppliesInATableCell($mode)
	{
		$html = '<style>sup { font-size: 75%; vertical-align: baseline }</style>'
			. '<table><tr><td style="font-size: 20pt">x<sup>up</sup></td></tr></table>';
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

		$this->assertDrawn($mpdf, 'up', 15, 0);
	}

	/**
	 * A rule naming center, the other tag mPDF v7 left out of allowedCSStags, applies
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testACenterRuleApplies($mode)
	{
		$colours = $this->drawnColours('<style>center { color: #f00 }</style><center>centred</center>', ['cssMode' => $mode]);

		$this->assertDrawnInColours(['centred' => '1.000 0.000 0.000 rg'], $colours);
	}

	/**
	 * Without a rule, sup and sub are drawn as they always were, at 55%, raised by half the paragraph's size and
	 * dropped by a fifth of it
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testWithoutARuleTheyAreDrawnAsBefore($mode)
	{
		$mpdf = $this->draw('', $mode);

		$this->assertDrawn($mpdf, 'up', self::DEFAULT_SIZE, self::RAISE);
		$this->assertDrawn($mpdf, 'down', self::DEFAULT_SIZE, self::DROP);
	}

	/**
	 * Taking the tags out of allowedCSStags again ignores the rules that name them, as mPDF v7 did, while a class rule
	 * on them still applies
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testTakingTheTagsOutOfAllowedCssTagsIgnoresTheRules($mode)
	{
		$css = 'sup, sub, p sup { color: #f00; font-size: 75%; vertical-align: baseline } .c { color: #00f }';
		$html = $this->document($css) . '<p style="font-size: 20pt">x<sup class="c">classed</sup></p>';
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode, 'allowedCSStags' => 'P|SPAN']);

		$this->assertDrawn($mpdf, 'up', self::DEFAULT_SIZE, self::RAISE);
		$this->assertDrawn($mpdf, 'down', self::DEFAULT_SIZE, self::DROP);
		$this->assertDrawnInColours(['up' => '0.000 g', 'down' => '0.000 g', 'classed' => '0.000 0.000 1.000 rg'], $this->keyedByText($mpdf, $mpdf->drawnColours));
	}

	/**
	 * A 20pt paragraph with a sup and a sub in it, after a stylesheet
	 *
	 * @param string $css
	 *
	 * @return string
	 */
	private function document($css)
	{
		return '<style>' . $css . '</style><p style="font-size: 20pt">x<sup>up</sup>x<sub>down</sub></p>';
	}

	/**
	 * Draws that paragraph under the stylesheet given
	 *
	 * @param string $css
	 * @param string $mode
	 *
	 * @return TextRecordingMpdf
	 */
	private function draw($css, $mode)
	{
		return $this->drawDocument($this->document($css), ['cssMode' => $mode]);
	}

	/**
	 * A piece of text is drawn at the size given, moved off the baseline by the amount given
	 *
	 * @param TextRecordingMpdf $mpdf
	 * @param string $text
	 * @param float $size In points
	 * @param float $shift In mm, negative for lowered
	 */
	private function assertDrawn(TextRecordingMpdf $mpdf, $text, $size, $shift)
	{
		$sizes = $this->keyedByText($mpdf, $mpdf->drawnFontSize);
		$shifts = $this->keyedByText($mpdf, $mpdf->drawnBaselineShifts);
		$this->assertArrayHasKey($text, $sizes, sprintf('"%s" is not drawn', $text));
		$this->assertEqualsWithDelta($size, $sizes[$text], 0.001, sprintf('"%s" is drawn at the wrong size', $text));
		$this->assertEqualsWithDelta($shift, $shifts[$text], 0.001, sprintf('"%s" is moved off the baseline by the wrong amount', $text));
	}
}
