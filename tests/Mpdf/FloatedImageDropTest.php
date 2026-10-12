<?php

namespace Mpdf;

/**
 * A left and a right floated image too wide to sit side by side: the second goes below the first, against its own
 * side, as in a browser. Legacy mode writes the second inline, as mPDF always did. Neither raises a warning.
 *
 * Positions are read from the content streams: the page's left margin is at 15mm and its content 180mm wide.
 */
class FloatedImageDropTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A JPEG, embedded as it is
	 */
	const IMAGE = __DIR__ . '/../data/img/bayeux2.jpg';

	/**
	 * Both floats are 120mm wide, too wide to share the 180mm line
	 *
	 * @param string $first The side the first image floats to
	 * @param string $before HTML before the paragraph
	 *
	 * @return string
	 */
	private function html($first, $before = '')
	{
		$second = $first === 'left' ? 'right' : 'left';

		return $before . '<p>'
			. '<img src="' . self::IMAGE . '" style="float: ' . $first . '; width: 120mm; height: 30mm" />'
			. '<img src="' . self::IMAGE . '" style="float: ' . $second . '; width: 120mm; height: 30mm" />'
			. str_repeat('Text beside the floats. ', 30)
			. '</p>';
	}

	/**
	 * The content stream of each page of the document, drawn without a warning
	 *
	 * @param string $html
	 * @param string $mode The CSS mode
	 *
	 * @return string[]
	 */
	private function silentPages($html, $mode = CssMode::STANDARD)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $mode, 'useKerning' => false]); // Kerned text is drawn with TJ, which drawnTextPositions() does not read

		return $this->pages($pdf);
	}

	/**
	 * Each side the first image can float to, with where each image's left edge is
	 *
	 * @return array[]
	 */
	public function sideProvider()
	{
		return [
			'left first' => ['left', 15, 75],
			'right first' => ['right', 75, 15],
		];
	}

	/**
	 * The second image keeps its size and goes against its own side, its top at the bottom of the first. Text runs
	 * beside the first image and then beside the second, with no line crossing into the second's top.
	 *
	 * @dataProvider sideProvider
	 *
	 * @param string $first
	 * @param float $firstX
	 * @param float $secondX
	 */
	public function testTheSecondFloatGoesBelowTheFirst($first, $firstX, $secondX)
	{
		$pages = $this->silentPages($this->html($first));
		$this->assertCount(1, $pages);
		$images = $this->placementsIn($pages[0]);

		$this->assertCount(2, $images);
		foreach ($images as $image) {
			$this->assertEqualsWithDelta(120, $image['w'], 0.01);
			$this->assertEqualsWithDelta(30, $image['h'], 0.01);
		}
		$this->assertEqualsWithDelta($firstX, $images[0]['x'], 0.01);
		$this->assertEqualsWithDelta($secondX, $images[1]['x'], 0.01);
		$top = $images[1]['top'];
		$this->assertEqualsWithDelta($images[0]['top'] + 30, $top, 0.01);

		$above = 0;
		$below = 0;
		foreach ($this->drawnTextPositions($pages[0]) as $text) {
			if ($text['y'] < $top) {
				$above++;
				// Far enough above the image for the descenders and the line's leading
				$this->assertLessThan($top - 1, $text['y'], 'A line beside the first float runs into the second');
			} else {
				$below++;
				$this->assertGreaterThan($top + 2, $text['y'], 'A line beside the second float starts above it');
			}
		}
		$this->assertGreaterThan(0, $above);
		$this->assertGreaterThan(0, $below);
	}

	/**
	 * Each side the first image can float to
	 *
	 * @return array[]
	 */
	public function firstSideProvider()
	{
		return [
			'left first' => ['left'],
			'right first' => ['right'],
		];
	}

	/**
	 * Legacy mode writes the second image inline, in the room left beside the first, as mPDF always did
	 *
	 * @dataProvider firstSideProvider
	 *
	 * @param string $first
	 */
	public function testLegacyModeWritesTheSecondFloatInline($first)
	{
		$pages = $this->silentPages($this->html($first), CssMode::LEGACY);
		$this->assertCount(1, $pages);
		$images = $this->placementsIn($pages[0]);

		$this->assertCount(2, $images);
		$this->assertLessThan(120, $images[1]['w']);
		$this->assertLessThan($images[0]['top'] + 30, $images[1]['top']);
	}

	/**
	 * A second image that would run past the bottom of the page below the first is written inline, as mPDF always did,
	 * and no image is drawn below the bottom margin
	 */
	public function testASecondFloatThatWouldRunOffThePageIsWrittenInline()
	{
		foreach ($this->silentPages($this->html('left', '<div style="height: 215mm"></div>')) as $page) {
			foreach ($this->placementsIn($page) as $image) {
				$this->assertLessThanOrEqual(297 - 16 + 0.01, $image['top'] + $image['h']);
			}
		}
	}

}
