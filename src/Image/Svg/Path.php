<?php

namespace Mpdf\Image\Svg;

use Mpdf\Utils\NumericString;

/**
 * An SVG shape as the moves, lines and cubic curves a PDF path is drawn with: path data, or one of the
 * basic shapes, which SVG defines as paths.
 *
 * Path data is split into tokens in one pass - commands, numbers, commas and anything else - then read
 * up to its first error and drawn that far, as SVG 1.1 says. Quadratic curves are raised to cubics,
 * and each arc is drawn as a cubic per quarter turn or less.
 *
 * @see https://www.w3.org/TR/SVG11/paths.html
 * @see https://www.w3.org/TR/SVG11/implnote.html#ArcImplementationNotes
 */
class Path
{

	/**
	 * How far along a quarter circle's tangents its cubic's control points lie
	 */
	const KAPPA = 0.5522847498;

	/**
	 * Path data's tokens: a letter, a number, or any other character but whitespace, which separates
	 * them and is left out
	 */
	const TOKENS = '/[a-zA-Z]|[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?|[^ \t\r\n]/';

	/**
	 * @var array[] Each segment: ['M', x, y], ['L', x, y], ['C', x1, y1, x2, y2, x, y] or ['Z']
	 */
	public $segments = [];

	/**
	 * @var string[] The path data being read, as tokens
	 */
	private $tokens = [];

	/**
	 * @var int Which token is next
	 */
	private $at = 0;

	/**
	 * @param string $data A path's d attribute
	 *
	 * @return Path
	 */
	public static function parse($data)
	{
		$path = new self();
		preg_match_all(self::TOKENS, $data, $tokens);
		$path->tokens = $tokens[0];
		$path->read();

		return $path;
	}

	/**
	 * @param float $x
	 * @param float $y
	 * @param float $width
	 * @param float $height
	 * @param float $rx     The corners' horizontal radius, already limited to half the width
	 * @param float $ry     Their vertical radius, already limited to half the height
	 *
	 * @return Path
	 */
	public static function rectangle($x, $y, $width, $height, $rx, $ry)
	{
		$path = new self();
		$right = $x + $width;
		$bottom = $y + $height;

		if ($rx <= 0 || $ry <= 0) {
			$path->segments = [['M', $x, $y], ['L', $right, $y], ['L', $right, $bottom], ['L', $x, $bottom], ['Z']];

			return $path;
		}

		$kx = $rx * self::KAPPA;
		$ky = $ry * self::KAPPA;
		$path->segments = [
			['M', $x + $rx, $y],
			['L', $right - $rx, $y],
			['C', $right - $rx + $kx, $y, $right, $y + $ry - $ky, $right, $y + $ry],
			['L', $right, $bottom - $ry],
			['C', $right, $bottom - $ry + $ky, $right - $rx + $kx, $bottom, $right - $rx, $bottom],
			['L', $x + $rx, $bottom],
			['C', $x + $rx - $kx, $bottom, $x, $bottom - $ry + $ky, $x, $bottom - $ry],
			['L', $x, $y + $ry],
			['C', $x, $y + $ry - $ky, $x + $rx - $kx, $y, $x + $rx, $y],
			['Z'],
		];

		return $path;
	}

	/**
	 * @param float $cx
	 * @param float $cy
	 * @param float $rx
	 * @param float $ry
	 *
	 * @return Path
	 */
	public static function ellipse($cx, $cy, $rx, $ry)
	{
		$kx = $rx * self::KAPPA;
		$ky = $ry * self::KAPPA;

		$path = new self();
		$path->segments = [
			['M', $cx + $rx, $cy],
			['C', $cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry],
			['C', $cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy],
			['C', $cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry],
			['C', $cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy],
			['Z'],
		];

		return $path;
	}

	/**
	 * @param float[] $points x, y, x, y and so on: a pair short at the end is left off
	 * @param bool    $closed Whether the last point joins the first, as a polygon's does
	 *
	 * @return Path
	 */
	public static function points(array $points, $closed)
	{
		$path = new self();
		for ($i = 0; $i + 1 < count($points); $i += 2) {
			$path->segments[] = [$i === 0 ? 'M' : 'L', $points[$i], $points[$i + 1]];
		}
		if ($closed && $path->segments) {
			$path->segments[] = ['Z'];
		}

		return $path;
	}

	/**
	 * @param float[] $matrix A transform
	 *
	 * @return Path The path with each of its points transformed, which transforms its cubics exactly
	 */
	public function transformed(array $matrix)
	{
		$path = new self();
		foreach ($this->segments as $segment) {
			for ($i = 1; $i < count($segment); $i += 2) {
				list($x, $y) = [$segment[$i], $segment[$i + 1]];
				$segment[$i] = $matrix[0] * $x + $matrix[2] * $y + $matrix[4];
				$segment[$i + 1] = $matrix[1] * $x + $matrix[3] * $y + $matrix[5];
			}
			$path->segments[] = $segment;
		}

		return $path;
	}

	/**
	 * @return string The path as content draws it, one operator a line, or nothing where it draws no
	 *                more than moves
	 */
	public function content()
	{
		$content = '';
		$draws = false;
		foreach ($this->segments as $segment) {
			$operator = array_shift($segment);
			if ($operator === 'Z') {
				$content .= "h\n";
				continue;
			}
			$draws = $draws || $operator !== 'M';
			foreach ($segment as $value) {
				$content .= NumericString::decimal($value, 5) . ' ';
			}
			$content .= strtolower($operator) . "\n";
		}

		return $draws ? $content : '';
	}

	/**
	 * @return float[]|null The box around what the path draws, curves by their extremes rather than
	 *                      their control points, or null where it has no points
	 */
	public function bounds()
	{
		$xs = [];
		$ys = [];
		$x = $y = $startX = $startY = 0;
		foreach ($this->segments as $segment) {
			if ($segment[0] === 'Z') {
				list($x, $y) = [$startX, $startY];
				continue;
			}

			if ($segment[0] === 'C') {
				$px = [$x, $segment[1], $segment[3], $segment[5]];
				$py = [$y, $segment[2], $segment[4], $segment[6]];
				foreach (array_merge(self::extremes($px), self::extremes($py)) as $t) {
					$xs[] = self::cubic($px, $t);
					$ys[] = self::cubic($py, $t);
				}
			}
			$xs[] = $x = $segment[count($segment) - 2];
			$ys[] = $y = $segment[count($segment) - 1];
			if ($segment[0] === 'M') {
				list($startX, $startY) = [$x, $y];
			}
		}

		return $xs ? [min($xs), min($ys), max($xs), max($ys)] : null;
	}

	/**
	 * @param float[] $values One coordinate of a cubic's four points
	 *
	 * @return float[] Where between its ends, from 0 to 1, the coordinate turns back
	 */
	private static function extremes(array $values)
	{
		list($p0, $p1, $p2, $p3) = $values;

		// The derivative, over three: a t^2 + b t + c
		$a = -$p0 + 3 * $p1 - 3 * $p2 + $p3;
		$b = 2 * ($p0 - 2 * $p1 + $p2);
		$c = $p1 - $p0;

		if (abs($a) < 1e-12) {
			$roots = abs($b) < 1e-12 ? [] : [-$c / $b];
		} else {
			$discriminant = $b * $b - 4 * $a * $c;
			$roots = $discriminant < 0 ? [] : [(-$b + sqrt($discriminant)) / (2 * $a), (-$b - sqrt($discriminant)) / (2 * $a)];
		}

		return array_filter($roots, function ($t) {
			return $t > 0 && $t < 1;
		});
	}

	/**
	 * @param float[] $values One coordinate of a cubic's four points
	 * @param float   $t      From 0 to 1
	 *
	 * @return float The coordinate at $t
	 */
	private static function cubic(array $values, $t)
	{
		$u = 1 - $t;

		return $u * $u * $u * $values[0] + 3 * $u * $u * $t * $values[1] + 3 * $u * $t * $t * $values[2] + $t * $t * $t * $values[3];
	}

	/**
	 * Reads the path data into segments, up to its end or its first error
	 */
	private function read()
	{
		// The current point, where the subpath started, and the last control point, for S and T
		$x = $y = $startX = $startY = 0;
		$control = null;
		$command = $upper = null;
		$count = count($this->tokens);

		while (true) {
			$this->skipComma();
			if ($this->at >= $count) {
				return;
			}

			$token = $this->tokens[$this->at];
			if (ctype_alpha($token)) {
				$this->at++;
				$command = $token;
				$upper = strtoupper($token);
			} elseif ($command === null || $upper === 'Z') {
				// Numbers with no command before them, or after a close
				return;
			}

			$relative = $command !== $upper;
			$dx = $relative ? $x : 0;
			$dy = $relative ? $y : 0;
			$previous = $control;
			$control = null;

			switch ($upper) {
				case 'Z':
					$this->segments[] = ['Z'];
					$x = $startX;
					$y = $startY;
					break;

				case 'M':
					$point = $this->numbers(2);
					if ($point === null) {
						return;
					}
					$x = $startX = $point[0] + $dx;
					$y = $startY = $point[1] + $dy;
					$this->segments[] = ['M', $x, $y];
					// Pairs after the first are lines
					$command = $relative ? 'l' : 'L';
					$upper = 'L';
					break;

				case 'L':
				case 'H':
				case 'V':
					$point = $this->numbers($upper === 'L' ? 2 : 1);
					if ($point === null) {
						return;
					}
					if ($upper === 'L') {
						list($x, $y) = [$point[0] + $dx, $point[1] + $dy];
					} elseif ($upper === 'H') {
						$x = $point[0] + $dx;
					} else {
						$y = $point[0] + $dy;
					}
					$this->segments[] = ['L', $x, $y];
					break;

				case 'C':
				case 'S':
					$smooth = $upper === 'S';
					$points = $this->numbers($smooth ? 4 : 6);
					if ($points === null) {
						return;
					}
					if ($smooth) {
						// The first control point is the last one reflected, where the last command was a cubic
						$first = $previous !== null && $previous[2] === 'C' ? [2 * $x - $previous[0], 2 * $y - $previous[1]] : [$x, $y];
						$points = array_merge([$first[0] - $dx, $first[1] - $dy], $points);
					}
					$segment = ['C', $points[0] + $dx, $points[1] + $dy, $points[2] + $dx, $points[3] + $dy, $points[4] + $dx, $points[5] + $dy];
					$this->segments[] = $segment;
					$control = [$segment[3], $segment[4], 'C'];
					list($x, $y) = [$segment[5], $segment[6]];
					break;

				case 'Q':
				case 'T':
					$smooth = $upper === 'T';
					$points = $this->numbers($smooth ? 2 : 4);
					if ($points === null) {
						return;
					}
					if ($smooth) {
						$q = $previous !== null && $previous[2] === 'Q' ? [2 * $x - $previous[0], 2 * $y - $previous[1]] : [$x, $y];
					} else {
						$q = [$points[0] + $dx, $points[1] + $dy];
						$points = array_slice($points, 2);
					}
					$end = [$points[0] + $dx, $points[1] + $dy];
					// A quadratic's control point two thirds of the way from each end
					$this->segments[] = ['C', $x + 2 / 3 * ($q[0] - $x), $y + 2 / 3 * ($q[1] - $y), $end[0] + 2 / 3 * ($q[0] - $end[0]), $end[1] + 2 / 3 * ($q[1] - $end[1]), $end[0], $end[1]];
					$control = [$q[0], $q[1], 'Q'];
					list($x, $y) = $end;
					break;

				case 'A':
					$arc = $this->arc();
					if ($arc === null) {
						return;
					}
					list($rx, $ry, $angle, $large, $sweep, $endX, $endY) = $arc;
					$endX += $dx;
					$endY += $dy;
					foreach (self::arcCurves($x, $y, $rx, $ry, $angle, $large, $sweep, $endX, $endY) as $segment) {
						$this->segments[] = $segment;
					}
					list($x, $y) = [$endX, $endY];
					break;

				default:
					return;
			}
		}
	}

	/**
	 * @param int $count How many numbers the command takes
	 *
	 * @return float[]|null They, or null where the data runs out or has something else first
	 */
	private function numbers($count)
	{
		$numbers = [];
		for ($i = 0; $i < $count; $i++) {
			if (isset($this->tokens[$this->at]) && $this->tokens[$this->at] === ',') {
				$this->at++;
			}
			if (!isset($this->tokens[$this->at]) || !is_numeric($this->tokens[$this->at])) {
				return null;
			}
			$numbers[] = (float) $this->tokens[$this->at++];
		}

		return $numbers;
	}

	/**
	 * @return array|null An arc's rx, ry, x-axis-rotation, large-arc-flag, sweep-flag, x and y, or null
	 *                    where the data does not have them. The flags are one character each, which
	 *                    need nothing between them and what follows, so a flag is the first character
	 *                    of a number token, the rest of it the next token: 01100 is 0, 1 and 100.
	 */
	private function arc()
	{
		$arc = $this->numbers(3);
		if ($arc === null) {
			return null;
		}
		for ($i = 0; $i < 2; $i++) {
			$this->skipComma();
			$token = isset($this->tokens[$this->at]) ? $this->tokens[$this->at] : '';
			if ($token === '' || ($token[0] !== '0' && $token[0] !== '1')) {
				return null;
			}
			if (strlen($token) === 1) {
				$this->at++;
			} else {
				$this->tokens[$this->at] = substr($token, 1);
			}
			$arc[] = $token[0] === '1';
		}
		$end = $this->numbers(2);

		return $end === null ? null : array_merge($arc, $end);
	}

	/**
	 * Skips a comma, which may lie between any two tokens but not twice
	 */
	private function skipComma()
	{
		if (isset($this->tokens[$this->at]) && $this->tokens[$this->at] === ',') {
			$this->at++;
		}
	}

	/**
	 * An elliptical arc as cubics, from its endpoints to its centre and angles as SVG 1.1 F.6.5 does
	 *
	 * @param float $x1     Where it starts
	 * @param float $y1
	 * @param float $rx     Its radii
	 * @param float $ry
	 * @param float $angle  The ellipse's rotation, in degrees
	 * @param bool  $large  Whether the arc is the longer way round
	 * @param bool  $sweep  Whether it runs the positive-angle way
	 * @param float $x2     Where it ends
	 * @param float $y2
	 *
	 * @return array[] The segments: none where the ends are the same, a line where a radius is 0
	 */
	private static function arcCurves($x1, $y1, $rx, $ry, $angle, $large, $sweep, $x2, $y2)
	{
		if ($x1 == $x2 && $y1 == $y2) {
			return [];
		}

		$rx = abs($rx);
		$ry = abs($ry);
		if ($rx == 0 || $ry == 0) {
			return [['L', $x2, $y2]];
		}

		$phi = deg2rad(fmod($angle, 360));
		$cos = cos($phi);
		$sin = sin($phi);

		$dx = ($x1 - $x2) / 2;
		$dy = ($y1 - $y2) / 2;
		$x1p = $cos * $dx + $sin * $dy;
		$y1p = -$sin * $dx + $cos * $dy;

		// Radii too small to reach are scaled up until they just do
		$lambda = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
		if ($lambda > 1) {
			$rx *= sqrt($lambda);
			$ry *= sqrt($lambda);
		}

		$numerator = $rx * $rx * $ry * $ry - $rx * $rx * $y1p * $y1p - $ry * $ry * $x1p * $x1p;
		$denominator = $rx * $rx * $y1p * $y1p + $ry * $ry * $x1p * $x1p;
		$coefficient = sqrt(max(0, $numerator / $denominator)) * ($large === $sweep ? -1 : 1);
		$cxp = $coefficient * $rx * $y1p / $ry;
		$cyp = -$coefficient * $ry * $x1p / $rx;
		$cx = $cos * $cxp - $sin * $cyp + ($x1 + $x2) / 2;
		$cy = $sin * $cxp + $cos * $cyp + ($y1 + $y2) / 2;

		$start = atan2(($y1p - $cyp) / $ry, ($x1p - $cxp) / $rx);
		$delta = atan2((-$y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx) - $start;
		if (!$sweep && $delta > 0) {
			$delta -= 2 * M_PI;
		} elseif ($sweep && $delta < 0) {
			$delta += 2 * M_PI;
		}

		$count = max(1, (int) ceil(abs($delta) / (M_PI / 2) - 1e-9));
		$step = $delta / $count;
		$t = 4 / 3 * tan($step / 4);

		$point = function ($a) use ($cx, $cy, $rx, $ry, $cos, $sin) {
			return [$cx + $rx * cos($a) * $cos - $ry * sin($a) * $sin, $cy + $rx * cos($a) * $sin + $ry * sin($a) * $cos];
		};
		$tangent = function ($a) use ($rx, $ry, $cos, $sin) {
			return [-$rx * sin($a) * $cos - $ry * cos($a) * $sin, -$rx * sin($a) * $sin + $ry * cos($a) * $cos];
		};

		$segments = [];
		for ($i = 0; $i < $count; $i++) {
			$a1 = $start + $i * $step;
			$a2 = $a1 + $step;
			list($px1, $py1) = $point($a1);
			list($px2, $py2) = $i === $count - 1 ? [$x2, $y2] : $point($a2);
			list($tx1, $ty1) = $tangent($a1);
			list($tx2, $ty2) = $tangent($a2);
			$segments[] = ['C', $px1 + $t * $tx1, $py1 + $t * $ty1, $px2 - $t * $tx2, $py2 - $t * $ty2, $px2, $py2];
		}

		return $segments;
	}
}
