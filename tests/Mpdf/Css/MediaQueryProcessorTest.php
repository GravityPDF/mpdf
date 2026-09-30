<?php

namespace Mpdf\Css;

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
	 * (about 1123px)
	 *
	 * @dataProvider mediaQueryLists
	 *
	 * @param string $mediaQueryList
	 * @param bool $expected
	 */
	public function testMatchesAPortraitA4Page($mediaQueryList, $expected)
	{
		$this->assertSame($expected, $this->processor->matches($mediaQueryList));
	}

	/**
	 * Media query lists, and whether each applies to print on a portrait A4 page
	 *
	 * @return array[]
	 */
	public function mediaQueryLists()
	{
		return [
			'an empty list' => ['', true],
			'print' => ['print', true],
			'print in upper case' => ['PRINT', true],
			'all' => ['all', true],
			'only print' => ['only print', true],
			'screen' => ['screen', false],
			'an unknown type' => ['tv', false],
			'a list naming print second' => ['screen, print', true],
			'not print' => ['not print', false],
			'not all' => ['not all', false],
			'not screen' => ['not screen', true],
			'only screen with a condition that holds' => ['only screen and (min-width: 1px)', false],
			'not screen with a condition' => ['not screen and (min-width: 1px)', true],

			'portrait' => ['(orientation: portrait)', true],
			'landscape' => ['print and (orientation: landscape)', false],
			'not landscape' => ['not print and (orientation: landscape)', true],
			'an unknown orientation' => ['(orientation: sideways)', false],

			'a min-width the page is wider than' => ['(min-width: 768px)', true],
			'a min-width the page is narrower than' => ['(min-width: 1000px)', false],
			'a max-width the page is wider than' => ['(max-width: 768px)', false],
			'a max-width the page is narrower than' => ['print and (max-width: 800px)', true],
			'the page width in mm' => ['(width: 210mm)', true],
			'a width between two lengths' => ['(min-width: 20cm) and (max-width: 22cm)', true],
			'a min-height in inches' => ['(min-height: 11in)', true],
			'a min-width in em' => ['(min-width: 40em)', true],
			'a min-width in rem the page is narrower than' => ['(min-width: 100rem)', false],
			'a zero without a unit' => ['(min-width: 0)', true],
			'a number without a unit' => ['(min-width: 10)', false],
			'a length mPDF cannot read' => ['(min-width: calc(1px + 1px))', false],
			'the width in a boolean context' => ['(width)', true],

			'range: width >= a length' => ['(width >= 600px)', true],
			'range: width < a length' => ['(width < 600px)', false],
			'range: a length <= width' => ['(600px <= width)', true],
			'range: width between two lengths' => ['(700px < width < 800px)', true],
			'range: width between two lengths it is outside' => ['(800px < width <= 900px)', false],
			'range: height from the top' => ['(1200px > height > 1000px)', true],
			'range: two operators facing apart' => ['(700px < width > 800px)', false],

			'and' => ['(min-width: 1px) and (max-width: 1px)', false],
			'or' => ['(max-width: 1px) or (min-height: 1px)', true],
			'not' => ['not (max-width: 600px)', true],
			'nested parentheses' => ['print and ((max-width: 1px) or (orientation: portrait))', true],
			'and mixed with or' => ['(min-width: 1px) and (min-height: 1px) or (width)', false],
			'an unknown feature' => ['(hover: hover)', false],
			'an unknown feature negated' => ['not (hover: hover)', false],
			'an unknown feature beside one that holds, with or' => ['(hover: hover) or (orientation: portrait)', true],
			'a condition left unfinished' => ['print and', false],
			'unbalanced parentheses' => ['(min-width: 1px', false],
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
}
