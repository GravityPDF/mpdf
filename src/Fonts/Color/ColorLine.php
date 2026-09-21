<?php

namespace Mpdf\Fonts\Color;

/**
 * The colours of a COLR version 1 gradient along its length: stops, each an offset and a colour, and
 * what lies beyond the last stop at either end.
 *
 * Stops are used in the order of their offsets, which need not be the order the font lists them in.
 * Where several share an offset, the first gives the colour below it and the last the colour from it
 * on, and the rest are dropped. Stops that all share one offset are a hard edge there, padded both
 * ways, since there is no span to repeat. A PDF shading has one function over 0 to 1, so the stops are
 * taken from wherever they lie onto that range - see normalised() - and the gradient's geometry moved
 * to match.
 *
 * Colours are interpolated premultiplied by alpha, as CPAL asks. A shading interpolates the colour and
 * its alpha mask separately, so premultiplied() adds stops where both vary. CPAL also asks for
 * interpolation in linear light, which a PDF shading cannot do.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/cpal#interpolation-of-colors
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/colr#color-references-colorstop-and-colorline
 */
class ColorLine
{

	/**
	 * The last stop's colour carries on past it, and the first's before it: a PDF shading's /Extend
	 */
	const PAD = 0;

	/**
	 * The stops are drawn again after the last, and before the first
	 */
	const REPEAT = 1;

	/**
	 * As REPEAT, every other time back to front
	 */
	const REFLECT = 2;

	/**
	 * How far premultiplied() lets a colour stray from premultiplied interpolation, as a fraction of full
	 * intensity: half of one of 8 bits' 255 steps
	 */
	const TOLERANCE = 0.5 / 255;

	/**
	 * The most parts premultiplied() divides a span between two stops into
	 */
	const MAX_PARTS = 32;

	/**
	 * The most times a repeated or reflected gradient's stops are drawn over, either way from the stops
	 * themselves
	 */
	const MAX_REPEATS = 64;

	/**
	 * @var int PAD, REPEAT or REFLECT. A value the spec does not define is PAD.
	 */
	public $extend;

	/**
	 * @var array[] Each stop as [offset, [red, green, blue] from 0 to 1, alpha from 0 to 1], in order
	 */
	public $stops = [];

	/**
	 * @param int     $extend PAD, REPEAT or REFLECT
	 * @param array[] $stops  Each stop as [offset, [red, green, blue], alpha], as the font lists them
	 */
	public function __construct($extend, array $stops)
	{
		$this->extend = in_array($extend, [self::REPEAT, self::REFLECT], true) ? $extend : self::PAD;

		// Sorted by offset, and within an offset by the order the font gave them
		$order = array_keys($stops);
		usort($order, function ($a, $b) use ($stops) {
			return $stops[$a][0] === $stops[$b][0] ? $a - $b : ($stops[$a][0] < $stops[$b][0] ? -1 : 1);
		});

		foreach ($order as $i => $key) {
			$next = isset($order[$i + 1]) ? $stops[$order[$i + 1]][0] : null;
			$previous = $i > 0 ? $stops[$order[$i - 1]][0] : null;
			// Of stops sharing an offset, only the first and the last count
			if ($stops[$key][0] !== $previous || $stops[$key][0] !== $next) {
				$this->stops[] = $stops[$key];
			}
		}

		// Stops all at one offset: a hard edge, with a stop 1 either side so the line has a span
		if (count($this->stops) === 2 && $this->stops[0][0] === $this->stops[1][0]) {
			list($below, $above) = $this->stops;
			$this->stops = [[$below[0] - 1, $below[1], $below[2]], $below, $above, [$above[0] + 1, $above[1], $above[2]]];
			$this->extend = self::PAD;
		}
	}

	/**
	 * @return float[] [the first stop's offset, the last's]
	 */
	public function span()
	{
		$last = end($this->stops);

		return [$this->stops[0][0], $last[0]];
	}

	/**
	 * The stops taken from the span they lie over onto 0 to 1, and for REPEAT and REFLECT drawn again
	 * over each whole span from $from to $to, which the gradient's geometry is then taken over instead
	 *
	 * @param int $from The first span drawn, 0 being the stops' own
	 * @param int $to   The span after the last drawn
	 *
	 * @return array[] Each stop as [offset from 0 to 1, colour, alpha]
	 */
	public function normalised($from = 0, $to = 1)
	{
		list($first, $last) = $this->span();
		$stops = [];
		foreach ($this->stops as $stop) {
			$stops[] = [($stop[0] - $first) / ($last - $first), $stop[1], $stop[2]];
		}

		if ($this->extend === self::PAD) {
			return $stops;
		}

		$spread = [];
		$reversed = array_reverse($stops);
		for ($span = $from; $span < $to; $span++) {
			$reflect = $this->extend === self::REFLECT && $span % 2 !== 0;
			foreach ($reflect ? $reversed : $stops as $stop) {
				$offset = $reflect ? 1 - $stop[0] : $stop[0];
				$spread[] = [($span - $from + $offset) / ($to - $from), $stop[1], $stop[2]];
			}
		}

		return $spread;
	}

	/**
	 * @return array The colour of the middle stop, [[red, green, blue], alpha]: what a gradient PDF
	 *               cannot draw is filled with
	 */
	public function middle()
	{
		$stop = $this->stops[(int) (count($this->stops) / 2)];

		return [$stop[1], $stop[2]];
	}

	/**
	 * @return float|null The alpha every stop has, or null where they differ
	 */
	public function opacity()
	{
		$alphas = array_unique(array_map(function ($stop) {
			return (string) $stop[2];
		}, $this->stops));

		return count($alphas) === 1 ? (float) reset($alphas) : null;
	}

	/**
	 * The stops between $from and $to, taken onto 0 to 1
	 *
	 * @param array[] $stops Each as [offset from 0 to 1, colour, alpha], in order
	 * @param float   $from
	 * @param float   $to
	 *
	 * @return array[] Those stops, with one interpolated at each end
	 */
	public static function between(array $stops, $from, $to)
	{
		$between = [array_merge([0.0], self::at($stops, $from))];
		foreach ($stops as $stop) {
			if ($stop[0] > $from && $stop[0] < $to) {
				$between[] = [($stop[0] - $from) / ($to - $from), $stop[1], $stop[2]];
			}
		}
		$between[] = array_merge([1.0], self::at($stops, $to));

		return $between;
	}

	/**
	 * The stops with more added wherever both colour and alpha change between two, so that a colour
	 * shading and an alpha mask, interpolated separately, together draw premultiplied interpolation. The
	 * mask needs none of them, its alpha being linear between the stops as they are.
	 *
	 * @param array[] $stops Each as [offset, colour, alpha], in order
	 *
	 * @return array[] Each as [offset, colour, alpha]
	 */
	public static function premultiplied(array $stops)
	{
		if (count($stops) < 2) {
			return $stops;
		}

		$drawn = [self::edge($stops[0], $stops[1])];
		for ($i = 1; $i < count($stops); $i++) {
			$a = $stops[$i - 1];
			$b = $stops[$i];
			$start = self::edge($a, $b);
			if ($start != end($drawn)) {
				$drawn[] = $start;
			}

			$parts = self::parts($a, $b);
			for ($part = 1; $part < $parts; $part++) {
				$drawn[] = array_merge([$a[0] + ($b[0] - $a[0]) * $part / $parts], self::mix($a, $b, $part / $parts));
			}
			$drawn[] = self::edge($b, $a);
		}

		return $drawn;
	}

	/**
	 * A stop at one end of a span, in its neighbour's colour where its own alpha is 0 and the neighbour's
	 * is not: premultiplied, it has no colour of its own
	 *
	 * @param array $stop      [offset, colour, alpha]
	 * @param array $neighbour The stop at the span's other end
	 *
	 * @return array
	 */
	private static function edge(array $stop, array $neighbour)
	{
		return $stop[2] == 0 && $neighbour[2] != 0 ? [$stop[0], $neighbour[1], $stop[2]] : $stop;
	}

	/**
	 * How many parts a span needs for its colours, interpolated alone, to stay within TOLERANCE of
	 * premultiplied interpolation. The part nearest the lower alpha strays furthest: by the change of
	 * alpha over it, times the change of the premultiplied colour's weight, times the change of colour,
	 * over 4.
	 *
	 * @param array $a A stop, as [offset, colour, alpha]
	 * @param array $b The next
	 *
	 * @return int 1 where the colour, the alpha or either end's alpha of 0 leaves nothing to add
	 */
	private static function parts(array $a, array $b)
	{
		$low = min($a[2], $b[2]);
		$high = max($a[2], $b[2]);
		$change = 0;
		foreach ($a[1] as $channel => $value) {
			$change = max($change, abs($b[1][$channel] - $value));
		}

		if ($low <= 0 || $low == $high || $change == 0) {
			return 1;
		}

		for ($parts = 1; $parts < self::MAX_PARTS; $parts++) {
			if (($high - $low) / $parts * $high / ($parts * $low + $high - $low) * $change / 4 <= self::TOLERANCE) {
				break;
			}
		}

		return $parts;
	}

	/**
	 * The colour and alpha at an offset, interpolated between the stops either side of it
	 *
	 * @param array[] $stops  Each as [offset, colour, alpha], in order
	 * @param float   $offset Where
	 *
	 * @return array [colour, alpha], the first stop's before it and the last's after
	 */
	private static function at(array $stops, $offset)
	{
		if ($offset <= $stops[0][0]) {
			return [$stops[0][1], $stops[0][2]];
		}

		for ($i = 1; $i < count($stops); $i++) {
			if ($stops[$i][0] >= $offset) {
				return self::mix($stops[$i - 1], $stops[$i], ($offset - $stops[$i - 1][0]) / ($stops[$i][0] - $stops[$i - 1][0]));
			}
		}

		$last = end($stops);

		return [$last[1], $last[2]];
	}

	/**
	 * The colour and alpha a fraction of the way from one stop to the next, the colour interpolated
	 * premultiplied by alpha
	 *
	 * @param array $a A stop, as [offset, colour, alpha]
	 * @param array $b The next
	 * @param float $t How far from the first to the second, from 0 to 1
	 *
	 * @return array [colour, alpha]: at alpha 0 at one end, the colour is the other end's throughout
	 */
	private static function mix(array $a, array $b, $t)
	{
		$alpha = $a[2] + ($b[2] - $a[2]) * $t;
		if ($alpha > 0) {
			$weights = [$a[2] * (1 - $t) / $alpha, $b[2] * $t / $alpha];
		} elseif ($a[2] == $b[2]) {
			$weights = [1 - $t, $t];
		} else {
			$weights = $a[2] == 0 ? [0, 1] : [1, 0];
		}

		$colour = [];
		foreach ($a[1] as $channel => $value) {
			$colour[] = $value * $weights[0] + $b[1][$channel] * $weights[1];
		}

		return [$colour, $alpha];
	}
}
