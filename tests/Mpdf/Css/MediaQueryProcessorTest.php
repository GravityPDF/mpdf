<?php

namespace Mpdf\Css;

use Mpdf\CssMode;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class MediaQueryProcessorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	private $mpdf;
	private $processor;

	public function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();

		$sizeConverter = new SizeConverter($this->mpdf->dpi, $this->mpdf->default_font_size, $this->mpdf, new NullLogger());
		$this->processor = new MediaQueryProcessor($this->mpdf, $sizeConverter);
	}

	public function tear_down()
	{
		unset($this->mpdf, $this->processor);
		parent::tear_down();
	}

	public function testFilterByMediaQueryMatches()
	{
		$this->mpdf->CSSselectMedia = 'print';
		$html = '<style media="print">.print { color: black; }</style>';
		$pattern = '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is';

		$processed = $this->processor->filterByMediaQuery($html, $pattern);
		$this->assertEquals($html, $processed);
	}

	public function testFilterByMediaQueryNoMatch()
	{
		$this->mpdf->CSSselectMedia = 'screen';
		$html = '<style media="print">.print { color: black; }</style>';
		$pattern = '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is';

		$processed = $this->processor->filterByMediaQuery($html, $pattern);
		$this->assertEmpty($processed); // Should be removed
	}

	public function testFilterByMediaQueryAll()
	{
		$this->mpdf->CSSselectMedia = 'screen';
		$html = '<style media="all">.all { color: blue; }</style>';
		$pattern = '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is';

		$processed = $this->processor->filterByMediaQuery($html, $pattern);
		$this->assertEquals($html, $processed);
	}

	/**
	 * A media query list applies when it names the medium CSSselectMedia names, or all media
	 */
	public function testMatchesTheMediumCssSelectMediaNames()
	{
		$this->mpdf->CSSselectMedia = 'print';

		$this->assertTrue($this->processor->matches('print'));
		$this->assertTrue($this->processor->matches('all'));
		$this->assertFalse($this->processor->matches('screen'));
	}

	/**
	 * Every media query list applies when CSSselectMedia names no medium
	 */
	public function testEveryListMatchesWithoutCssSelectMedia()
	{
		$this->mpdf->CSSselectMedia = '';

		$this->assertTrue($this->processor->matches('screen'));
	}

	/**
	 * A media query list is matched against the print medium and a portrait A4 page, 210mm (about 794px) by 297mm
	 * (about 1123px). In cssMode legacy it applies when it contains print or all anywhere, whatever the page
	 *
	 * @dataProvider mediaQueryLists
	 *
	 * @param string $mediaQueryList
	 * @param bool $expected
	 * @param bool $legacy Whether it applies in legacy mode
	 */
	public function testMatchesAPortraitA4Page($mediaQueryList, $expected, $legacy)
	{
		$this->assertSame($expected, $this->processor->matches($mediaQueryList), 'standard');

		$this->mpdf->cssMode = CssMode::LEGACY;
		$this->assertSame($legacy, $this->processor->matches($mediaQueryList), 'legacy');
	}

	/**
	 * Media query lists, whether each applies to print on a portrait A4 page, and whether it does in legacy mode
	 *
	 * @return array[]
	 */
	public function mediaQueryLists()
	{
		return [
			'an empty list' => ['', true, false],
			'print' => ['print', true, true],
			'print in upper case' => ['PRINT', true, true],
			'all' => ['all', true, true],
			'only print' => ['only print', true, true],
			'screen' => ['screen', false, false],
			'an unknown type' => ['tv', false, false],
			'a list naming print second' => ['screen, print', true, true],
			'not print' => ['not print', false, true],
			'not all' => ['not all', false, true],
			'not screen' => ['not screen', true, false],
			'only screen with a condition that holds' => ['only screen and (min-width: 1px)', false, false],
			'not screen with a condition' => ['not screen and (min-width: 1px)', true, false],

			'portrait' => ['(orientation: portrait)', true, false],
			'landscape' => ['print and (orientation: landscape)', false, true],
			'not landscape' => ['not print and (orientation: landscape)', true, true],
			'an unknown orientation' => ['(orientation: sideways)', false, false],

			'a min-width the page is wider than' => ['(min-width: 768px)', true, false],
			'a min-width the page is narrower than' => ['(min-width: 1000px)', false, false],
			'a max-width the page is wider than' => ['(max-width: 768px)', false, false],
			'a max-width the page is narrower than' => ['print and (max-width: 800px)', true, true],
			'the page width in mm' => ['(width: 210mm)', true, false],
			'a width between two lengths' => ['(min-width: 20cm) and (max-width: 22cm)', true, false],
			'a min-height in inches' => ['(min-height: 11in)', true, false],
			'a min-width in em' => ['(min-width: 40em)', true, false],
			'a min-width in rem the page is narrower than' => ['(min-width: 100rem)', false, false],
			'a zero without a unit' => ['(min-width: 0)', true, false],
			'a number without a unit' => ['(min-width: 10)', false, false],
			'a length mPDF cannot read' => ['(min-width: calc(1px + 1px))', false, false],
			'the width in a boolean context' => ['(width)', true, false],

			'range: width >= a length' => ['(width >= 600px)', true, false],
			'range: width < a length' => ['(width < 600px)', false, false],
			'range: a length <= width' => ['(600px <= width)', true, false],
			'range: width between two lengths' => ['(700px < width < 800px)', true, false],
			'range: width between two lengths it is outside' => ['(800px < width <= 900px)', false, false],
			'range: height from the top' => ['(1200px > height > 1000px)', true, false],
			'range: two operators facing apart' => ['(700px < width > 800px)', false, false],

			'and' => ['(min-width: 1px) and (max-width: 1px)', false, false],
			'or' => ['(max-width: 1px) or (min-height: 1px)', true, false],
			'not' => ['not (max-width: 600px)', true, false],
			'nested parentheses' => ['print and ((max-width: 1px) or (orientation: portrait))', true, true],
			'and mixed with or' => ['(min-width: 1px) and (min-height: 1px) or (width)', false, false],
			'an unknown feature' => ['(hover: hover)', false, false],
			'an unknown feature negated' => ['not (hover: hover)', false, false],
			'an unknown feature beside one that holds, with or' => ['(hover: hover) or (orientation: portrait)', true, false],
			'a condition left unfinished' => ['print and', false, true],
			'unbalanced parentheses' => ['(min-width: 1px', false, false],
		];
	}

	/**
	 * Orientation and width follow the current page: a landscape A4 page is about 1123px wide
	 */
	public function testMatchesTheCurrentPage()
	{
		$this->mpdf->AddPage('L');

		$this->assertTrue($this->processor->matches('(orientation: landscape)'));
		$this->assertTrue($this->processor->matches('(min-width: 1000px)'));
		$this->assertFalse($this->processor->matches('(orientation: portrait)'));
	}

	/**
	 * With CSSselectMedia set to screen, screen matches and print does not
	 */
	public function testMatchesAnotherMedium()
	{
		$this->mpdf->CSSselectMedia = 'screen';

		$this->assertTrue($this->processor->matches('screen and (min-width: 768px)'));
		$this->assertFalse($this->processor->matches('print'));
	}

	/**
	 * A <style> block for not print is left out when rendering for print
	 */
	public function testFilterByMediaQueryLeavesOutANegatedMatch()
	{
		$pattern = '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is';

		$this->assertSame('', $this->processor->filterByMediaQuery('<style media="not print">p { color: red; }</style>', $pattern));
	}

	/**
	 * In cssMode legacy the page is not read: on a landscape page, a query naming print applies whatever its
	 * orientation, and one naming no medium does not
	 */
	public function testLegacyModeDoesNotReadThePage()
	{
		$this->mpdf->cssMode = CssMode::LEGACY;
		$this->mpdf->AddPage('L');

		$this->assertTrue($this->processor->matches('print and (orientation: portrait)'));
		$this->assertFalse($this->processor->matches('(orientation: landscape)'));
		$this->assertFalse($this->processor->matches('(min-width: 1000px)'));
	}

	/**
	 * In cssMode legacy, with CSSselectMedia set to screen, a list containing screen applies, negated or not, and
	 * print does not
	 */
	public function testLegacyModeMatchesAnotherMediumByName()
	{
		$this->mpdf->cssMode = CssMode::LEGACY;
		$this->mpdf->CSSselectMedia = 'screen';

		$this->assertTrue($this->processor->matches('not screen'));
		$this->assertTrue($this->processor->matches('screen and (max-width: 1px)'));
		$this->assertFalse($this->processor->matches('print'));
	}

	/**
	 * In cssMode legacy a <style> block for not print is kept, as its media attribute contains print
	 */
	public function testFilterByMediaQueryKeepsANegatedMatchInLegacyMode()
	{
		$this->mpdf->cssMode = CssMode::LEGACY;
		$pattern = '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is';
		$html = '<style media="not print">p { color: red; }</style>';

		$this->assertSame($html, $this->processor->filterByMediaQuery($html, $pattern));
	}
}
