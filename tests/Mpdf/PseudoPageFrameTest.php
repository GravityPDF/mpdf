<?php

namespace Mpdf;

/**
 * The :first, :left and :right pages of an @page rule, named or not, can give the page its own side margins, and text
 * flowing onto that page is set in the frame they leave. A margin set on a :left or :right page is where it says: it
 * is not mirrored as the margins of a plain @page rule are. Every line is measured against the frame of the page it
 * is drawn on, the one that turns the page included.
 */
class PseudoPageFrameTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A story on a named page starts beside a photograph and carries on over the full width
	 */
	public function testEachPageOfANamedPageTakesTheFrameOfItsPseudoPage()
	{
		$frames = $this->frames(
			'@page story :first { margin-left: 100mm; }
			@page story :left { margin-left: 20mm; margin-right: 20mm; }
			@page story :right { margin-left: 20mm; margin-right: 20mm; }',
			'<div style="page: story">' . $this->story(30) . '</div>'
		);

		$this->assertSame([1 => [100.0, 190.0], 2 => [20.0, 190.0], 3 => [20.0, 190.0]], array_slice($frames, 0, 3, true));
	}

	/**
	 * The side margins of :left and :right are not swapped on a left page, and the page narrower than the one before
	 * takes the line that turned the page within its frame
	 */
	public function testLeftAndRightPagesTakeTheirSideMarginsAsWritten()
	{
		$frames = $this->frames(
			'@page { margin-left: 30mm; margin-right: 10mm; }
			@page :right { margin-left: 20mm; margin-right: 50mm; }
			@page :left { margin-left: 60mm; margin-right: 40mm; }',
			$this->story(30)
		);

		$this->assertSame([1 => [20.0, 160.0], 2 => [60.0, 170.0], 3 => [20.0, 160.0]], array_slice($frames, 0, 3, true));
	}

	/**
	 * A :left page that sets only its left margin keeps the right one mirrored from the plain rule: the inner margin
	 */
	public function testASideLeftUnsetOnALeftPageIsStillMirrored()
	{
		$frames = $this->frames(
			'@page { margin-left: 30mm; margin-right: 10mm; }
			@page :left { margin-left: 25mm; }',
			$this->story(30)
		);

		$this->assertSame([1 => [30.0, 200.0], 2 => [25.0, 180.0], 3 => [30.0, 200.0]], array_slice($frames, 0, 3, true));
	}

	/**
	 * Without side margins on a pseudo page the margins of the plain rule are mirrored as before
	 */
	public function testMirroredMarginsAreUnchangedWithoutSideMarginsOnAPseudoPage()
	{
		$frames = $this->frames(
			'@page { margin-left: 30mm; margin-right: 10mm; }
			@page :left { margin-top: 30mm; }',
			$this->story(30)
		);

		$this->assertSame([1 => [30.0, 200.0], 2 => [10.0, 180.0], 3 => [30.0, 200.0]], array_slice($frames, 0, 3, true));
	}

	/**
	 * The :first page of the unnamed @page rule sets the side margins of the first page only
	 */
	public function testTheFirstPageOfTheDocumentTakesItsOwnSideMargins()
	{
		$frames = $this->frames('@page :first { margin-left: 100mm; }', $this->story(20));

		$this->assertSame([1 => [100.0, 195.0], 2 => [15.0, 195.0]], array_slice($frames, 0, 2, true));
	}

	/**
	 * A block with a set width keeps it as the frame moves under it
	 */
	public function testABlockWithASetWidthKeepsItAcrossFrames()
	{
		$frames = $this->frames(
			'@page { margin-top: 20mm; }
			@page :right { margin-left: 20mm; margin-right: 50mm; }
			@page :left { margin-left: 60mm; margin-right: 40mm; }',
			'<div style="width: 60mm">' . $this->story(12) . '</div>'
		);

		$this->assertSame([1 => [20.0, 80.0], 2 => [60.0, 120.0], 3 => [20.0, 80.0]], array_slice($frames, 0, 3, true));
	}

	/**
	 * Columns carried over the page are laid out across the frame of the new page
	 */
	public function testColumnsAreLaidOutAcrossTheFrameOfEachPage()
	{
		$mpdf = $this->write(
			'@page story :first { margin-left: 100mm; }
			@page story :left { margin-left: 20mm; margin-right: 20mm; }
			@page story :right { margin-left: 20mm; margin-right: 20mm; }',
			'<div style="page: story"><columns column-count="2" column-gap="10" />' . $this->story(40) . '</div>'
		);

		// The frame is 90mm wide on the first page and 170mm on the others, less a 10mm gap
		$this->assertColumns($mpdf, 1, [[100.0, 140.0], [150.0, 190.0]]);
		$this->assertColumns($mpdf, 2, [[20.0, 100.0], [110.0, 190.0]]);
		$this->assertColumns($mpdf, 3, [[20.0, 100.0], [110.0, 190.0]]);
	}

	/**
	 * The narrowest left edge and widest right edge of the lines drawn on each page, in millimetres
	 *
	 * @param string $css
	 * @param string $body
	 *
	 * @return array[] By page number
	 */
	private function frames($css, $body)
	{
		$frames = [];
		foreach ($this->write($css, $body)->drawnBoxes as $box) {
			$frames[$box[0]] = $this->widen($frames, $box[0], $box);
		}

		return $this->rounded($frames);
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
				}
			}
			$this->assertNotNull($in, sprintf('A line on page %d runs from %.1f to %.1f', $page, $box[1], $box[2]));

			$filled[$in] = $this->widen($filled, $in, $box);
		}

		ksort($filled);
		$this->assertSame($columns, $this->rounded($filled));
	}

	/**
	 * A span widened to take in a drawn line
	 *
	 * @param array[] $spans The left and right edges found so far
	 * @param int $key The span to widen
	 * @param array $box The page, left and right edge of the line
	 *
	 * @return float[]
	 */
	private function widen($spans, $key, $box)
	{
		return isset($spans[$key]) ? [min($spans[$key][0], $box[1]), max($spans[$key][1], $box[2])] : [$box[1], $box[2]];
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
