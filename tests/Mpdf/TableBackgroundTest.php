<?php

namespace Mpdf;

class TableBackgroundTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const RED_FILL = '1.000 0.000 0.000 rg';

	private function render($html)
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->compress = false;
		$mpdf->WriteHTML($html);

		return $mpdf->Output('', 'S');
	}

	/**
	 * The content stream of each page, in order
	 */
	private function pages($pdf)
	{
		$matches = [];
		preg_match_all('/\d+ 0 obj\s*<<\/Length \d+>>\s*stream\n(.*?)\nendstream/s', $pdf, $matches);

		return $matches[1];
	}

	/**
	 * The rectangles filled red, without the duplicates that come of the same background being
	 * spliced in behind more than one placeholder
	 */
	private function redRectangles($pdf)
	{
		$matches = [];
		preg_match_all('/' . preg_quote(self::RED_FILL, '/') . '\n([\d.\- ]+) re f/', $pdf, $matches);

		return array_values(array_unique($matches[1]));
	}

	private function document($before, $after)
	{
		return str_repeat('<p>Before</p>', $before)
			. '<table><tr><td>Normal table cell</td></tr></table>'
			. '<div style="page-break-inside: avoid">'
			. '<table><tr><td bgcolor="red">Red table cell</td></tr></table>'
			. str_repeat('<p>After</p>', $after)
			. '</div>';
	}

	/**
	 * A page-break-inside:avoid block is laid out once to measure it and then again for real. The
	 * measuring pass used to paint the table background on the page the block starts on, which is
	 * the one page the unwind does not throw away, so it was left behind after the table had moved
	 * to the next page. See mpdf/mpdf#570.
	 */
	public function testATableBackgroundInsideAnAvoidBlockThatMovesIsDrawnInOnePlace()
	{
		$this->assertCount(1, $this->redRectangles($this->render($this->document(20, 10))));
	}

	public function testItIsDrawnOnThePageTheTableMovedToAndNotTheOneItLeft()
	{
		$pages = $this->pages($this->render($this->document(20, 10)));

		$this->assertCount(2, $pages);
		$this->assertStringNotContainsString(self::RED_FILL, $pages[0]);
		$this->assertStringContainsString(self::RED_FILL, $pages[1]);
		$this->assertStringContainsString('Red table cell', $pages[1]);
	}

	/**
	 * The block fits where it is, so there is no move to leave anything behind - the background
	 * still has to be drawn
	 */
	public function testATableBackgroundInsideAnAvoidBlockThatStaysPutIsStillDrawn()
	{
		$this->assertCount(1, $this->redRectangles($this->render($this->document(2, 2))));
	}

	public function testATableBackgroundOutsideAnyAvoidBlockIsUnaffected()
	{
		$html = str_repeat('<p>Before</p>', 20)
			. '<table><tr><td bgcolor="red">Red table cell</td></tr></table>';

		$this->assertCount(1, $this->redRectangles($this->render($html)));
	}

}
