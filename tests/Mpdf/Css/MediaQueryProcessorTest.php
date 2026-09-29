<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;

class MediaQueryProcessorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	private $mpdf;
	private $processor;

	public function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();

		$this->processor = new MediaQueryProcessor($this->mpdf);
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
}
