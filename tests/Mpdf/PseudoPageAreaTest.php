<?php

namespace Mpdf;

/**
 * The :first, :left and :right pages of an @page rule, named or not, can set their own side margins, and text flowing
 * onto the page is set between them. Unlike the margins of a plain @page rule, a side margin on a pseudo page is not
 * mirrored. Every line, including the one that turns the page, is measured against the page area it is drawn in.
 */
class PseudoPageAreaTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A story on a named page starts beside a photograph and carries on over the full width
	 */
	public function testEachPageOfANamedPageTakesThePageAreaOfItsPseudoPage()
	{
		$areas = $this->areas(
			'@page story :first { margin-left: 100mm; }
			@page story :left { margin-left: 20mm; margin-right: 20mm; }
			@page story :right { margin-left: 20mm; margin-right: 20mm; }',
			'<div style="page: story">' . $this->story(30) . '</div>',
			3
		);

		$this->assertSame([1 => [100.0, 190.0], 2 => [20.0, 190.0], 3 => [20.0, 190.0]], $areas);
	}

	/**
	 * The side margins of :left and :right are not swapped on a left page, and the line that turns onto a narrower page
	 * stays inside it
	 */
	public function testLeftAndRightPagesTakeTheirSideMarginsAsWritten()
	{
		$areas = $this->areas(
			'@page { margin-left: 30mm; margin-right: 10mm; }
			@page :right { margin-left: 20mm; margin-right: 50mm; }
			@page :left { margin-left: 60mm; margin-right: 40mm; }',
			$this->story(30),
			3
		);

		$this->assertSame([1 => [20.0, 160.0], 2 => [60.0, 170.0], 3 => [20.0, 160.0]], $areas);
	}

	/**
	 * A :left page that sets only its left margin keeps the right one mirrored from the plain rule: the inner margin
	 */
	public function testASideLeftUnsetOnALeftPageIsStillMirrored()
	{
		$areas = $this->areas(
			'@page { margin-left: 30mm; margin-right: 10mm; }
			@page :left { margin-left: 25mm; }',
			$this->story(30),
			3
		);

		$this->assertSame([1 => [30.0, 200.0], 2 => [25.0, 180.0], 3 => [30.0, 200.0]], $areas);
	}

	/**
	 * Without side margins on a pseudo page the margins of the plain rule are mirrored as before
	 */
	public function testMirroredMarginsAreUnchangedWithoutSideMarginsOnAPseudoPage()
	{
		$areas = $this->areas(
			'@page { margin-left: 30mm; margin-right: 10mm; }
			@page :left { margin-top: 30mm; }',
			$this->story(30),
			3
		);

		$this->assertSame([1 => [30.0, 200.0], 2 => [10.0, 180.0], 3 => [30.0, 200.0]], $areas);
	}

	/**
	 * The :first page of the unnamed @page rule sets the side margins of the first page only
	 */
	public function testTheFirstPageOfTheDocumentTakesItsOwnSideMargins()
	{
		$areas = $this->areas('@page :first { margin-left: 100mm; }', $this->story(20), 2);

		$this->assertSame([1 => [100.0, 195.0], 2 => [15.0, 195.0]], $areas);
	}

	/**
	 * A block with a set width keeps it as the page area moves under it
	 */
	public function testABlockWithASetWidthKeepsItAcrossPageAreas()
	{
		$areas = $this->areas(
			'@page { margin-top: 20mm; }
			@page :right { margin-left: 20mm; margin-right: 50mm; }
			@page :left { margin-left: 60mm; margin-right: 40mm; }',
			'<div style="width: 60mm">' . $this->story(12) . '</div>',
			3
		);

		$this->assertSame([1 => [20.0, 80.0], 2 => [60.0, 120.0], 3 => [20.0, 80.0]], $areas);
	}

	/**
	 * Columns carried over the page are laid out across the page area of the new page
	 */
	public function testColumnsAreLaidOutAcrossThePageAreaOfEachPage()
	{
		$mpdf = $this->write(
			'@page story :first { margin-left: 100mm; }
			@page story :left { margin-left: 20mm; margin-right: 20mm; }
			@page story :right { margin-left: 20mm; margin-right: 20mm; }',
			'<div style="page: story"><columns column-count="2" column-gap="10" />' . $this->story(40) . '</div>'
		);

		// The page area is 90mm wide on the first page and 170mm on the others, less a 10mm gap
		$this->assertColumns($mpdf, 1, [[100.0, 140.0], [150.0, 190.0]]);
		$this->assertColumns($mpdf, 2, [[20.0, 100.0], [110.0, 190.0]]);
		$this->assertColumns($mpdf, 3, [[20.0, 100.0], [110.0, 190.0]]);
	}

	/**
	 * The narrowest left edge and widest right edge of the lines drawn on each of the first pages, in millimetres
	 *
	 * @param string $css
	 * @param string $body
	 * @param int $pages How many pages to return
	 *
	 * @return array[] By page number
	 */
	private function areas($css, $body, $pages)
	{
		$areas = [];
		foreach ($this->write($css, $body)->drawnBoxes as $box) {
			$areas[$box[0]] = $this->widen(isset($areas[$box[0]]) ? $areas[$box[0]] : null, $box);
		}

		return array_slice($this->rounded($areas), 0, $pages, true);
	}

	/**
	 * A document with the style sheet and body, whose lines have been recorded as they were drawn
	 *
	 * @param string $css
	 * @param string $body
	 *
	 * @return \Mpdf\TextRecordingMpdf
	 */
	private function write($css, $body)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<style>p { text-align: justify; } ' . $css . '</style>' . $body);
		$mpdf->Output('', 'S');

		return $mpdf;
	}

	/**
	 * Every line drawn on the page lies in one of the columns, and the lines of each column fill it
	 *
	 * @param \Mpdf\TextRecordingMpdf $mpdf
	 * @param int $page
	 * @param array[] $columns The left and right edge of each column
	 */
	private function assertColumns(TextRecordingMpdf $mpdf, $page, $columns)
	{
		$filled = [];
		foreach ($mpdf->drawnBoxes as $box) {
			if ($box[0] !== $page) {
				continue;
			}

			$in = null;
			foreach ($columns as $i => $column) {
				if ($box[1] >= $column[0] - 0.01 && $box[2] <= $column[1] + 0.01) {
					$in = $i;
					break;
				}
			}
			$this->assertNotNull($in, sprintf('A line on page %d runs from %.1f to %.1f', $page, $box[1], $box[2]));

			$filled[$in] = $this->widen(isset($filled[$in]) ? $filled[$in] : null, $box);
		}

		ksort($filled);
		$this->assertSame($columns, $this->rounded($filled));
	}

	/**
	 * A span widened to take in a drawn line
	 *
	 * @param float[]|null $span The left and right edges found so far, or null for none
	 * @param array $box The page, left and right edge of the line
	 *
	 * @return float[]
	 */
	private function widen($span, $box)
	{
		return $span ? [min($span[0], $box[1]), max($span[1], $box[2])] : [$box[1], $box[2]];
	}

	/**
	 * Spans rounded to a tenth of a millimetre
	 *
	 * @param array[] $spans
	 *
	 * @return array[]
	 */
	private function rounded($spans)
	{
		return array_map(static function ($span) {
			return [round($span[0], 1), round($span[1], 1)];
		}, $spans);
	}

	/**
	 * Justified paragraphs of text
	 *
	 * @param int $paragraphs
	 *
	 * @return string
	 */
	private function story($paragraphs)
	{
		return str_repeat('<p>' . str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 8) . '</p>', $paragraphs);
	}

}
