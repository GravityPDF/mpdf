<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * @media blocks and media attributes, matched against the print medium and the page's size and orientation rather
 * than by looking for the word print in them. In cssMode legacy they are matched by looking for the word, as mPDF v7
 * did.
 */
class MediaQueryTest extends TestCase
{

	use PageStreams;

	const GREEN = '0.000 1.000 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * The paragraph is green when the media query matches print on a portrait A4 page, and black when it does not
	 *
	 * @dataProvider mediaQueries
	 *
	 * @param string $mode
	 * @param string $mediaQueryList
	 * @param string $expected
	 */
	public function testAnAtMediaBlockAppliesWhenItsQueryMatches($mode, $mediaQueryList, $expected)
	{
		$colours = $this->textColours(
			'<style>@media ' . $mediaQueryList . ' { p { color: #00ff00; } }</style><p>text</p>',
			['cssMode' => $mode]
		);

		$this->assertSame(['text' => $expected], $colours);
	}

	/**
	 * Media query lists, and the colour the paragraph is drawn in each mode
	 *
	 * @return array[]
	 */
	public function mediaQueries()
	{
		return [
			'not print' => [CssMode::STANDARD, 'not print', self::BLACK],
			'landscape' => [CssMode::STANDARD, 'print and (orientation: landscape)', self::BLACK],
			'a min-width narrower than the page' => [CssMode::STANDARD, '(min-width: 768px)', self::GREEN],
			'legacy: not print' => [CssMode::LEGACY, 'not print', self::GREEN],
			'legacy: landscape' => [CssMode::LEGACY, 'print and (orientation: landscape)', self::GREEN],
			'legacy: a min-width narrower than the page' => [CssMode::LEGACY, '(min-width: 768px)', self::BLACK],
		];
	}

	/**
	 * A block for landscape pages applies on a landscape page, in either mode
	 *
	 * @dataProvider eachMode
	 *
	 * @param string $mode
	 */
	public function testALandscapeBlockAppliesOnALandscapePage($mode)
	{
		$pages = $this->pages($this->render(
			'<style>@media print and (orientation: landscape) { p { color: #00ff00; } }</style><p>text</p>',
			['format' => 'A4-L', 'cssMode' => $mode]
		));

		$this->assertStringContainsString(self::GREEN, $pages[0]);
	}

	/**
	 * A <style> block for not print is left out, and in legacy mode it applies, as it contains the word print
	 *
	 * @dataProvider eachMode
	 *
	 * @param string $mode
	 */
	public function testAStyleBlockForNotPrint($mode)
	{
		$colours = $this->textColours('<style media="not print">p { color: #00ff00; }</style><p>text</p>', ['cssMode' => $mode]);

		$this->assertSame(['text' => $mode === CssMode::LEGACY ? self::GREEN : self::BLACK], $colours);
	}

	/**
	 * @return array[] Each value of cssMode
	 */
	public function eachMode()
	{
		return [
			'standard' => [CssMode::STANDARD],
			'legacy' => [CssMode::LEGACY],
		];
	}
}
