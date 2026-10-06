<?php

namespace Mpdf;

/**
 * The :first, :left and :right pages of an @page rule, named or not, can set their own side margins, and text flowing
 * onto the page is set between them. Unlike the margins of a plain @page rule, a side margin on a pseudo page is not
 * mirrored. Every line, including the one that turns the page, is measured against the page area it is drawn in.
 */
class PseudoPageAreaTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/** A page area 150mm wide on a right page and 170mm on a left page, for a float to run over. */
	const WIDER_LEFT_PAGES = '@page { margin-left: 30mm; margin-right: 30mm; } @page :left { margin-left: 20mm; margin-right: 20mm; }';

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
	 * A :first, :left or :right rule applies to its own pages without a plain @page rule beside it, and only to them:
	 * a :right rule is not the default of the left pages
	 *
	 * @dataProvider pseudoPageProvider
	 *
	 * @param string $html Written ahead of the text, such as the root element of a right-to-left document
	 * @param string $css
	 * @param float[] $tops The top of the first line on each of the first three pages
	 */
	public function testAPseudoPageRuleAppliesToItsOwnPages($html, $css, $tops)
	{
		$mpdf = $this->write($css, $html . $this->story(30));

		$this->assertSame($tops, array_slice($this->firstLineTops($mpdf->drawnBoxes), 0, 3, true));
	}

	/**
	 * Rules setting an 80mm top margin, with the plain rule's own 16mm elsewhere. The first page of a right-to-left
	 * document is a left page.
	 *
	 * @return array[]
	 */
	public function pseudoPageProvider()
	{
		$leftPages = [1 => 16.0, 2 => 80.0, 3 => 16.0];
		$rightPages = [1 => 80.0, 2 => 16.0, 3 => 80.0];

		return [
			':first' => ['', '@page :first { margin-top: 80mm; }', [1 => 80.0, 2 => 16.0, 3 => 16.0]],
			':left' => ['', '@page :left { margin-top: 80mm; }', $leftPages],
			':right' => ['', '@page :right { margin-top: 80mm; }', $rightPages],
			':left beside an empty @page rule' => ['', '@page { } @page :left { margin-top: 80mm; }', $leftPages],
			':left beside a plain rule' => ['', '@page { margin-bottom: 16mm; } @page :left { margin-top: 80mm; }', $leftPages],
			':right beside a plain rule' => ['', '@page { margin-bottom: 16mm; } @page :right { margin-top: 80mm; }', $rightPages],
			':right in a right-to-left document' => ['<html dir="rtl">', '@page :right { margin-top: 80mm; }', $leftPages],
		];
	}

	/**
	 * A document that turns right to left after its first page is made keeps the page area of that page, and sets the
	 * pages after it as a document right to left from the start would
	 *
	 * @dataProvider sideMarginSourcesProvider
	 *
	 * @param string $css
	 * @param array $config
	 */
	public function testAPageKeepsItsPageAreaWhenTheDocumentTurnsRightToLeft($css, $config)
	{
		$leftToRight = $this->areas($css, '<p>x</p>' . $this->story(30), 1, $config);
		$rightToLeft = $this->areas($css, '<html dir="rtl"><p>x</p>' . $this->story(30), 3, $config);
		$turned = $this->areas($css, ['<p>x</p>', '<html dir="rtl">' . $this->story(30)], 3, $config);

		$this->assertSame($leftToRight + array_slice($rightToLeft, 1, 2, true), $turned);
	}

	/**
	 * Side margins from a plain @page rule, from the configuration, and from the configuration mirrored
	 *
	 * @return array[]
	 */
	public function sideMarginSourcesProvider()
	{
		return [
			'@page rule' => ['@page { margin-left: 20mm; margin-right: 50mm; }', []],
			'configuration' => ['', ['margin_left' => 20, 'margin_right' => 50]],
			'mirrored configuration' => ['', ['margin_left' => 30, 'margin_right' => 10, 'mirrorMargins' => true]],
		];
	}

	/**
	 * :left and :right rules without a plain @page rule set the side margins of their pages
	 */
	public function testLeftAndRightPagesTakeTheirSideMarginsWithoutAPlainRule()
	{
		$areas = $this->areas(
			'@page :right { margin-left: 20mm; margin-right: 50mm; }
			@page :left { margin-left: 60mm; margin-right: 40mm; }',
			$this->story(30),
			3
		);

		$this->assertSame([1 => [20.0, 160.0], 2 => [60.0, 170.0], 3 => [20.0, 160.0]], $areas);
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
	 * A block with a set width keeps it as the page area moves under it, and keeps to the side of the page area it was
	 * set against: the left, the middle, or the right where its left margin is auto or it runs right to left
	 *
	 * @dataProvider setWidthProvider
	 *
	 * @param string $html Written ahead of the block, such as the root element of a right-to-left document
	 * @param string $style
	 * @param array[] $spans The left and right edges of the block on each of the first three pages
	 */
	public function testABlockWithASetWidthKeepsItsPlaceInEachPageArea($html, $style, $spans)
	{
		$mpdf = $this->write(
			'@page { margin-top: 20mm; }
			@page :right { margin-left: 20mm; margin-right: 50mm; }
			@page :left { margin-left: 60mm; margin-right: 40mm; }',
			$html . '<div style="width: 60mm; background: #0f0; ' . $style . '">' . $this->story(12) . '</div>'
		);

		$this->assertSame($spans, array_slice($this->spans($mpdf->drawnBoxes), 0, 3, true));
		$this->assertSame($spans, array_slice($this->painted($mpdf), 0, 3, true));
	}

	/**
	 * Blocks 60mm wide in page areas from 20mm to 160mm on a right page and from 60mm to 170mm on a left page. The
	 * first page of a right-to-left document is a left page.
	 *
	 * @return array[]
	 */
	public function setWidthProvider()
	{
		$againstRight = [1 => [100.0, 160.0], 2 => [110.0, 170.0], 3 => [100.0, 160.0]];

		return [
			'left' => ['', '', [1 => [20.0, 80.0], 2 => [60.0, 120.0], 3 => [20.0, 80.0]]],
			'centred' => ['', 'margin: 0 auto', [1 => [60.0, 120.0], 2 => [85.0, 145.0], 3 => [60.0, 120.0]]],
			'left margin auto' => ['', 'margin-left: auto', $againstRight],
			'right to left' => ['', 'direction: rtl', $againstRight],
			'in a right-to-left document' => ['<html dir="rtl">', '', [1 => [110.0, 170.0], 2 => [100.0, 160.0], 3 => [110.0, 170.0]]],
		];
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
	 * Text beside a float that runs over pages of different widths is set in the page area of each page, including the
	 * pages the float made before the text reached them
	 *
	 * @dataProvider floatProvider
	 *
	 * @param string $body
	 * @param array[] $oddPage The float and the text beside it on pages 1 and 3, left to right
	 * @param array[] $evenPage The same on page 2
	 */
	public function testTextBesideAFloatTakesThePageAreaOfEachPage($body, $oddPage, $evenPage)
	{
		$mpdf = $this->write(self::WIDER_LEFT_PAGES, $body);

		$this->assertColumns($mpdf, 1, $oddPage);
		$this->assertColumns($mpdf, 2, $evenPage);
		$this->assertColumns($mpdf, 3, $oddPage);
	}

	/**
	 * Left and right floats, each followed by a block of text or inside the same block as the text, in a document left
	 * to right and one right to left. A float is 40% of the page area of the first page. That is a right page 150mm
	 * wide left to right, so the float is 60mm wide, and a left page 170mm wide right to left, so it is 68mm wide.
	 *
	 * @return array[]
	 */
	public function floatProvider()
	{
		$directions = [
			'' => [
				'left' => [[[30.0, 90.0], [90.0, 180.0]], [[20.0, 80.0], [80.0, 190.0]]],
				'right' => [[[30.0, 120.0], [120.0, 180.0]], [[20.0, 130.0], [130.0, 190.0]]],
			],
			'rtl' => [
				'left' => [[[20.0, 88.0], [88.0, 190.0]], [[30.0, 98.0], [98.0, 180.0]]],
				'right' => [[[20.0, 122.0], [122.0, 190.0]], [[30.0, 112.0], [112.0, 180.0]]],
			],
		];

		$cases = [];
		foreach ($directions as $direction => $floats) {
			$html = $direction ? '<html dir="' . $direction . '">' : '';
			$suffix = $direction ? ', right to left' : '';
			foreach ($floats as $float => $areas) {
				$floated = '<div style="float: ' . $float . '; width: 40%">' . $this->story(14) . '</div>';
				$cases[$float . $suffix] = array_merge([$html . $floated . '<div>' . $this->story(20) . '</div>'], $areas);
				$cases[$float . ' in a block' . $suffix] = array_merge([$html . '<div>' . $floated . $this->story(20) . '</div>'], $areas);
			}
		}

		return $cases;
	}

	/**
	 * A float and the block around it are painted in the page area of each page, as text goes back to the page the
	 * float started on and on to the page it ends on
	 *
	 * @dataProvider paintedFloatProvider
	 *
	 * @param string|string[] $body
	 * @param array[] $painted The left and right edges of what is painted on each page
	 */
	public function testAFloatAndTheBlockAroundItArePaintedInThePageAreaOfEachPage($body, $painted)
	{
		$mpdf = $this->write(self::WIDER_LEFT_PAGES, $body);

		$this->assertSame($painted, $this->painted($mpdf));
	}

	/**
	 * A right float, with the background on the block around it (which runs on past the float, or ends beside it), on
	 * the float, left to right and right to left, or on a block that follows it (cleared of it, or written by a later
	 * WriteHTML() call). Right to left, the first page is a left page, and the float 40% of its 170mm page area.
	 *
	 * @return array[]
	 */
	public function paintedFloatProvider()
	{
		$float = '<div style="float: right; width: 40%">' . $this->story(12) . '</div>';
		$paintedFloat = '<div style="float: right; width: 40%; background: #0f0">' . $this->story(12) . '</div>';
		$areas = [1 => [30.0, 180.0], 2 => [20.0, 190.0], 3 => [30.0, 180.0], 4 => [20.0, 190.0]];
		$afterFloat = [4 => [20.0, 190.0], 5 => [30.0, 180.0]];

		return [
			'block running past the float' => ['<div style="background: #0f0">' . $float . $this->story(20) . '</div>', $areas],
			'block ending beside the float' => ['<div style="background: #0f0">' . $float . $this->story(2) . '</div>' . $this->story(10), $areas],
			'float' => [
				'<div>' . $paintedFloat . $this->story(20) . '</div>',
				[1 => [120.0, 180.0], 2 => [130.0, 190.0], 3 => [120.0, 180.0], 4 => [130.0, 190.0]],
			],
			'float, right to left' => [
				'<html dir="rtl"><div>' . $paintedFloat . $this->story(20) . '</div>',
				[1 => [122.0, 190.0], 2 => [112.0, 180.0], 3 => [122.0, 190.0]],
			],
			'block cleared of the float' => [
				$float . $this->story(2) . '<div style="clear: both; background: #0f0">' . $this->story(10) . '</div>',
				$afterFloat,
			],
			'block written after the float' => [
				[$float . $this->story(2), '<div style="background: #0f0">' . $this->story(10) . '</div>'],
				$afterFloat,
			],
		];
	}

	/**
	 * The narrowest left edge and widest right edge of the lines drawn on each of the first pages, in millimetres
	 *
	 * @param string $css
	 * @param string|string[] $body Or its parts, as write() takes them
	 * @param int $pages How many pages to return
	 * @param array $config
	 *
	 * @return array[] By page number
	 */
	private function areas($css, $body, $pages, $config = [])
	{
		return array_slice($this->spans($this->write($css, $body, $config)->drawnBoxes), 0, $pages, true);
	}

	/**
	 * A document with the style sheet and body, whose lines have been recorded as they were drawn
	 *
	 * @param string $css
	 * @param string|string[] $body Or its parts, each written by a call of its own; the style sheet goes with the first
	 * @param array $config
	 *
	 * @return \Mpdf\TextRecordingMpdf
	 */
	private function write($css, $body, $config = [])
	{
		$parts = (array) $body;
		$parts[0] = '<style>p { text-align: justify; } ' . $css . '</style>' . $parts[0];

		$mpdf = new TextRecordingMpdf($config + ['mode' => 'c']);
		foreach ($parts as $part) {
			$mpdf->WriteHTML($part);
		}
		$mpdf->Output('', 'S');

		return $mpdf;
	}

	/**
	 * Every line drawn on the page lies in one of the columns, and the lines of each column fill it: together they span
	 * it, and most of them end at its right edge, as every line of justified text does but the last of a paragraph
	 *
	 * @param \Mpdf\TextRecordingMpdf $mpdf
	 * @param int $page
	 * @param array[] $columns The left and right edge of each column
	 */
	private function assertColumns(TextRecordingMpdf $mpdf, $page, $columns)
	{
		$filled = [];
		$rightEdges = [];
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

			$filled[] = [$in, $box[1], $box[2]];
			$rightEdges[$in][] = sprintf('%.1f', $box[2]);
		}

		$filled = $this->spans($filled);
		ksort($filled);
		$this->assertSame($columns, $filled);

		foreach ($rightEdges as $i => $edges) {
			$atEdge = count(array_keys($edges, sprintf('%.1f', $columns[$i][1])));
			$this->assertGreaterThan(count($edges) / 2, $atEdge, sprintf('Most lines of column %d on page %d end short of it', $i, $page));
		}
	}

	/**
	 * The narrowest left edge and widest right edge of the rectangles painted on each page, in millimetres
	 *
	 * @param \Mpdf\TextRecordingMpdf $mpdf
	 *
	 * @return array[] By page number
	 */
	private function painted(TextRecordingMpdf $mpdf)
	{
		$boxes = [];
		foreach ($mpdf->pages as $page => $stream) {
			preg_match_all('/(-?[\d.]+) -?[\d.]+ (-?[\d.]+) -?[\d.]+ re\b/', $stream, $rects, PREG_SET_ORDER);
			foreach ($rects as $rect) {
				$x = $rect[1] / Mpdf::SCALE;
				$boxes[] = [$page, $x, $x + $rect[2] / Mpdf::SCALE];
			}
		}

		return $this->spans($boxes);
	}

}
