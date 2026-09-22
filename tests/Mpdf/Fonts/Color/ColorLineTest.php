<?php

namespace Mpdf\Fonts\Color;

/**
 * A COLR version 1 colour line: its stops put in order and taken onto 0 to 1, and for REPEAT and
 * REFLECT drawn again over each span a gradient needs.
 */
class ColorLineTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const RED = [1, 0, 0];

	const GREEN = [0, 1, 0];

	const BLUE = [0, 0, 1];

	/**
	 * Stops are used in the order of their offsets, and of several at one offset only the first and
	 * the last the font lists
	 */
	public function testStopsAreSortedAndOnlyTheOuterOfThoseSharingAnOffsetKept()
	{
		$line = new ColorLine(ColorLine::PAD, [
			[1.0, self::BLUE, 1.0],
			[0.5, self::RED, 1.0],
			[0.5, self::GREEN, 1.0],
			[0.5, self::BLUE, 1.0],
			[0.0, self::GREEN, 1.0],
		]);

		$this->assertSame([[0.0, self::GREEN, 1.0], [0.5, self::RED, 1.0], [0.5, self::BLUE, 1.0], [1.0, self::BLUE, 1.0]], $line->stops);
	}

	/**
	 * Stops lying over any span are taken onto 0 to 1
	 */
	public function testStopsAreTakenOntoZeroToOne()
	{
		$line = new ColorLine(ColorLine::PAD, [[-0.5, self::RED, 1.0], [0.0, self::GREEN, 1.0], [1.5, self::BLUE, 1.0]]);

		$this->assertSame([-0.5, 1.5], $line->span());
		$this->assertSame([[0.0, self::RED, 1.0], [0.25, self::GREEN, 1.0], [1.0, self::BLUE, 1.0]], $line->normalised());
	}

	/**
	 * REPEAT draws the stops again over each span, and REFLECT every other span back to front
	 *
	 * @dataProvider spreads
	 *
	 * @param int     $extend   REPEAT or REFLECT
	 * @param float[] $offsets  Each stop's offset across the three spans
	 * @param array[] $colours  Each stop's colour
	 */
	public function testStopsAreDrawnAgainOverEachSpan($extend, array $offsets, array $colours)
	{
		$line = new ColorLine($extend, [[0.0, self::RED, 1.0], [0.25, self::GREEN, 1.0], [1.0, self::BLUE, 1.0]]);

		$spread = $line->normalised(-1, 2);

		$this->assertEqualsWithDelta($offsets, array_column($spread, 0), 1e-9);
		$this->assertSame($colours, array_column($spread, 1));
	}

	/**
	 * @return array[] Each extend mode, and the stops it draws over spans -1 to 1
	 */
	public function spreads()
	{
		return [
			'REPEAT' => [ColorLine::REPEAT, [0, 0.25 / 3, 1 / 3, 1 / 3, 1.25 / 3, 2 / 3, 2 / 3, 2.25 / 3, 1], [self::RED, self::GREEN, self::BLUE, self::RED, self::GREEN, self::BLUE, self::RED, self::GREEN, self::BLUE]],
			'REFLECT' => [ColorLine::REFLECT, [0, 0.75 / 3, 1 / 3, 1 / 3, 1.25 / 3, 2 / 3, 2 / 3, 2.75 / 3, 1], [self::BLUE, self::GREEN, self::RED, self::RED, self::GREEN, self::BLUE, self::BLUE, self::GREEN, self::RED]],
		];
	}

	/**
	 * PAD's stops are its own however many spans are asked for, and an extend mode the spec does not
	 * define is PAD
	 */
	public function testPadAndAnUnknownModeDrawTheStopsOnce()
	{
		$stops = [[0.0, self::RED, 1.0], [1.0, self::BLUE, 1.0]];

		$this->assertSame($stops, (new ColorLine(ColorLine::PAD, $stops))->normalised(-1, 2));
		$this->assertSame(ColorLine::PAD, (new ColorLine(7, $stops))->extend);
	}

	/**
	 * A line whose stops share one alpha is drawn at that alpha, and one whose alphas differ needs a mask
	 */
	public function testTheOpacityIsTheStopsAlphaWhereTheyShareOne()
	{
		$this->assertSame(0.5, (new ColorLine(ColorLine::PAD, [[0.0, self::RED, 0.5], [1.0, self::BLUE, 0.5]]))->opacity());
		$this->assertNull((new ColorLine(ColorLine::PAD, [[0.0, self::RED, 0.5], [1.0, self::BLUE, 1.0]]))->opacity());
	}

	/**
	 * Stops all at one offset are a hard edge there, the first stop's colour below and the last's from
	 * it on, padded either way, since there is no length to repeat
	 */
	public function testStopsSharingOneOffsetAreAHardEdge()
	{
		$line = new ColorLine(ColorLine::REPEAT, [[0.5, self::RED, 1.0], [0.5, self::GREEN, 1.0], [0.5, self::BLUE, 1.0]]);

		$this->assertSame(ColorLine::PAD, $line->extend);
		$this->assertSame([-0.5, 1.5], $line->span());
		$this->assertSame([[0.0, self::RED, 1.0], [0.5, self::RED, 1.0], [0.5, self::BLUE, 1.0], [1.0, self::BLUE, 1.0]], $line->normalised());
	}

	/**
	 * Between two stops whose alphas and colours differ, stops are added in the colours of premultiplied
	 * interpolation, enough that a colour interpolated straight between them, times its alpha, strays no
	 * further than TOLERANCE from premultiplied interpolation; more where the alpha changes more, up to
	 * MAX_PARTS
	 *
	 * @dataProvider varyingAlphas
	 *
	 * @param float $from The first stop's alpha
	 * @param float $to   The second's
	 */
	public function testStopsAreAddedWhereTheAlphaAndColourBothVary($from, $to)
	{
		$line = [[0.0, self::RED, $from], [1.0, self::BLUE, $to]];
		$stops = ColorLine::premultiplied($line);

		$this->assertGreaterThan(2, count($stops));
		foreach ($stops as $stop) {
			$this->assertEqualsWithDelta(self::premultipliedAt($line, $stop[0]), $stop[1], 1e-9);
		}

		for ($i = 1; $i < count($stops); $i++) {
			for ($t = 0.1; $t < 1; $t += 0.1) {
				$offset = $stops[$i - 1][0] + ($stops[$i][0] - $stops[$i - 1][0]) * $t;
				$alpha = $from + ($to - $from) * $offset;
				$exact = self::premultipliedAt($line, $offset);
				foreach ([0, 2] as $channel) {
					$straight = $stops[$i - 1][1][$channel] + ($stops[$i][1][$channel] - $stops[$i - 1][1][$channel]) * $t;
					$this->assertLessThanOrEqual(ColorLine::TOLERANCE, $alpha * abs($straight - $exact[$channel]));
				}
			}
		}

		$this->assertLessThan(count($stops), count(ColorLine::premultiplied([[0.0, self::RED, 0.8], [1.0, self::BLUE, 1.0]])));
	}

	/**
	 * However steeply the alpha changes, a span is divided into no more than MAX_PARTS
	 */
	public function testASpanIsDividedIntoNoMoreThanMaxParts()
	{
		$this->assertCount(ColorLine::MAX_PARTS + 1, ColorLine::premultiplied([[0.0, self::RED, 1.0], [1.0, self::BLUE, 0.001]]));
	}

	/**
	 * @return float[][] Alphas at the two ends of a line from red to blue
	 */
	public function varyingAlphas()
	{
		return [
			'rising from a fifth' => [0.2, 1.0],
			'falling to a tenth' => [1.0, 0.1],
		];
	}

	/**
	 * A stop at alpha 0 has no colour of its own: each side of it is its neighbour's colour, so a stop
	 * between two is drawn twice
	 */
	public function testAStopAtAlphaZeroTakesItsNeighboursColour()
	{
		$fading = ColorLine::premultiplied([[0.0, self::RED, 0.0], [1.0, self::BLUE, 1.0]]);
		$this->assertSame([self::BLUE], array_values(array_unique(array_column($fading, 1), SORT_REGULAR)));

		$gap = ColorLine::premultiplied([[0.0, self::RED, 1.0], [0.5, self::GREEN, 0.0], [1.0, self::BLUE, 1.0]]);
		$middle = array_values(array_filter($gap, function ($stop) {
			return $stop[0] == 0.5;
		}));
		$this->assertEqualsWithDelta([[0.5, self::RED, 0], [0.5, self::BLUE, 0]], $middle, 1e-9);
	}

	/**
	 * Where either the alpha or the colour stays the same, interpolating each alone is already right
	 */
	public function testStopsAreLeftWhereOnlyOneOfAlphaAndColourVaries()
	{
		$constant = [[0.0, self::RED, 0.5], [1.0, self::BLUE, 0.5]];
		$fade = [[0.0, self::RED, 0.0], [0.5, self::RED, 0.0], [1.0, self::RED, 1.0]];

		$this->assertSame($constant, ColorLine::premultiplied($constant));
		$this->assertSame($fade, ColorLine::premultiplied($fade));
	}

	/**
	 * @param array[] $line   Two stops, [offset, colour, alpha], at 0 and 1
	 * @param float   $offset
	 *
	 * @return float[] The colour premultiplied interpolation gives at the offset
	 */
	private static function premultipliedAt(array $line, $offset)
	{
		list(, $a, $alphaA) = $line[0];
		list(, $b, $alphaB) = $line[1];
		$alpha = $alphaA + ($alphaB - $alphaA) * $offset;

		$colour = [];
		foreach ($a as $channel => $value) {
			$colour[] = ($value * $alphaA * (1 - $offset) + $b[$channel] * $alphaB * $offset) / $alpha;
		}

		return $colour;
	}

	/**
	 * Stops cut to part of their range: one interpolated at each end, and the part taken onto 0 to 1
	 */
	public function testStopsAreCutToAPartOfTheirRange()
	{
		$stops = [[0.0, self::RED, 1.0], [0.25, self::GREEN, 1.0], [1.0, self::BLUE, 1.0]];

		$this->assertEqualsWithDelta(
			[[0, [0.5, 0.5, 0], 1], [0.2, self::GREEN, 1], [1, [0, 1 / 3, 2 / 3], 1]],
			ColorLine::between($stops, 0.125, 0.75),
			1e-9
		);
	}
}
