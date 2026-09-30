<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * @media blocks and media attributes, matched against the print medium and the page's size and orientation rather
 * than by looking for the word print in them
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
	 * @param string $mediaQueryList
	 * @param string $expected
	 */
	public function testAnAtMediaBlockAppliesWhenItsQueryMatches($mediaQueryList, $expected)
	{
		$colours = $this->textColours('<style>@media ' . $mediaQueryList . ' { p { color: #00ff00; } }</style><p>text</p>');

		$this->assertSame(['text' => $expected], $colours);
	}

	/**
	 * Media query lists, and the colour the paragraph is drawn in
	 *
	 * @return array[]
	 */
	public function mediaQueries()
	{
		return [
			'not print' => ['not print', self::BLACK],
			'landscape' => ['print and (orientation: landscape)', self::BLACK],
			'a min-width narrower than the page' => ['(min-width: 768px)', self::GREEN],
		];
	}

	/**
	 * A block for landscape pages applies on a landscape page
	 */
	public function testALandscapeBlockAppliesOnALandscapePage()
	{
		$pages = $this->pages($this->render(
			'<style>@media print and (orientation: landscape) { p { color: #00ff00; } }</style><p>text</p>',
			['format' => 'A4-L']
		));

		$this->assertStringContainsString(self::GREEN, $pages[0]);
	}

	/**
	 * A <style> block for not print is left out
	 */
	public function testAStyleBlockForNotPrintIsLeftOut()
	{
		$colours = $this->textColours('<style media="not print">p { color: #00ff00; }</style><p>text</p>');

		$this->assertSame(['text' => self::BLACK], $colours);
	}
}
