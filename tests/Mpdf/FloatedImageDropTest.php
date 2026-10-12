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
	 * Where each image on a page is placed, keyed w/h/x/top in millimetres from the top left corner of the page
	 *
	 * @param string $stream
	 *
	 * @return array[]
	 */
	private function placements($stream)
	{
		preg_match_all('/([-\d.]+) 0 0 ([-\d.]+) ([-\d.]+) ([-\d.]+) cm \/I\d+ Do/', $stream, $matches, PREG_SET_ORDER);

		return array_map(function ($match) {
			$h = $match[2] / Mpdf::SCALE;

			return ['w' => $match[1] / Mpdf::SCALE, 'h' => $h, 'x' => $match[3] / Mpdf::SCALE, 'top' => 297 - $match[4] / Mpdf::SCALE - $h];
		}, $matches);
	}

	/**
	 * Each side the first image can float to, in each CSS mode
	 *
	 * @return array[]
	 */
	public function sideAndModeProvider()
	{
		return [
			'left first, standard' => ['left', CssMode::STANDARD],
			'right first, standard' => ['right', CssMode::STANDARD],
			'left first, legacy' => ['left', CssMode::LEGACY],
			'right first, legacy' => ['right', CssMode::LEGACY],
		];
	}

	/**
	 * Neither mode reads the margin of a float that was never recorded
	 *
	 * @dataProvider sideAndModeProvider
	 *
	 * @param string $first
	 * @param string $mode
	 */
	public function testFloatsTooWideToShareALineRaiseNoWarning($first, $mode)
	{
		$html = $this->html($first);
		$this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $mode]);
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
	 * The second image keeps its size and goes against its own side, its top at the bottom of the first
	 *
	 * @dataProvider sideProvider
	 *
	 * @param string $first
	 * @param float $firstX
	 * @param float $secondX
	 */
	public function testTheSecondFloatGoesBelowTheFirst($first, $firstX, $secondX)
	{
		$images = $this->placements($this->firstPage($this->html($first)));

		$this->assertCount(2, $images);
		foreach ($images as $image) {
			$this->assertEqualsWithDelta(120, $image['w'], 0.01);
			$this->assertEqualsWithDelta(30, $image['h'], 0.01);
		}
		$this->assertEqualsWithDelta($firstX, $images[0]['x'], 0.01);
		$this->assertEqualsWithDelta($secondX, $images[1]['x'], 0.01);
		$this->assertEqualsWithDelta($images[0]['top'] + 30, $images[1]['top'], 0.01);
	}

	/**
	 * Text runs beside the first image and then beside the second, with no line crossing into the second's top
	 *
	 * @dataProvider sideProvider
	 *
	 * @param string $first
	 * @param float $firstX
	 * @param float $secondX
	 */
	public function testTextStaysClearOfTheSecondFloat($first, $firstX, $secondX)
	{
		$page = $this->firstPage($this->html($first));
		$images = $this->placements($page);
		$top = $images[1]['top'];

		$above = 0;
		$below = 0;
		foreach ($this->drawnTextPositions($page) as $text) {
			if ($text['y'] < $top) {
				$above++;
				// The baseline stays far enough above the image for the descenders and the line's leading
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
	 * Legacy mode writes the second image inline, in the room left beside the first, as mPDF always did
	 */
	public function testLegacyModeWritesTheSecondFloatInline()
	{
		$images = $this->placements($this->firstPage($this->html('left'), ['cssMode' => CssMode::LEGACY]));

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
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML($this->html('left', '<div style="height: 215mm"></div>'));
		});

		foreach ($this->pages($pdf) as $page) {
			foreach ($this->placements($page) as $image) {
				$this->assertLessThanOrEqual(297 - 16 + 0.01, $image['top'] + $image['h']);
			}
		}
	}

}
