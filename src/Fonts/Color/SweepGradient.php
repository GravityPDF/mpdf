<?php

namespace Mpdf\Fonts\Color;

/**
 * A sweep gradient as the triangles of a free-form mesh shading: thin wedges fanning out from its
 * centre, each corner carrying the offset its angle has along the stops, which the shading's function
 * takes to a colour.
 *
 * The offset runs from 0 at one angle to 1 at another, and is held at 0 or 1 beyond them. Angles are
 * counter-clockwise from the positive x axis, from 0 to 360 degrees, as the spec measures them. A
 * repeated or reflected gradient is given its stops already spread over the turn - see
 * ColorLine::normalised() - so the offset is linear in the angle throughout.
 *
 * A wedge's edges fall on every stop's angle, so a hard edge falls between two wedges, and no wedge is
 * wider than MAX_WEDGE, so interpolating across its straight edge draws each angle's colour closely.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/colr#sweep-gradients
 */
class SweepGradient
{

	/**
	 * The widest a wedge is, in degrees
	 */
	const MAX_WEDGE = 4;

	/**
	 * @param float   $x       The centre
	 * @param float   $y
	 * @param float   $first   The angle of offset 0, in degrees
	 * @param float   $last    The angle of offset 1; where it is $first, the offset steps from 0 to 1
	 *                         there
	 * @param float[] $offsets Each stop's offset, from 0 to 1
	 * @param float[] $box     The area to cover
	 *
	 * @return array[] Each triangle as three corners, each [x, y, offset], the centre first
	 */
	public static function triangles($x, $y, $first, $last, array $offsets, array $box)
	{
		$angles = [0.0, 360.0];
		foreach ($offsets as $offset) {
			$angles[] = $first + $offset * ($last - $first);
		}

		// Far enough out that each wedge's straight edge lies beyond every corner of the box
		$radius = 0;
		foreach (Geometry::corners($box) as $corner) {
			$radius = max($radius, hypot($corner[0] - $x, $corner[1] - $y));
		}
		$radius = ($radius + 1) / cos(deg2rad(self::MAX_WEDGE / 2));

		$corner = function ($angle, $offset) use ($x, $y, $radius) {
			return [$x + $radius * cos(deg2rad($angle)), $y + $radius * sin(deg2rad($angle)), $offset];
		};

		$angles = self::within($angles);
		$triangles = [];
		for ($i = 1, $count = count($angles); $i < $count; $i++) {
			$parts = (int) ceil(($angles[$i] - $angles[$i - 1]) / self::MAX_WEDGE);
			$step = ($angles[$i] - $angles[$i - 1]) / $parts;
			for ($part = 0; $part < $parts; $part++) {
				$from = $angles[$i - 1] + $step * $part;
				$to = $from + $step;
				if ($first === $last) {
					$start = $end = $from + $step / 2 < $first ? 0.0 : 1.0;
				} else {
					$start = self::offset($from, $first, $last);
					$end = self::offset($to, $first, $last);
				}
				$triangles[] = [[$x, $y, ($start + $end) / 2], $corner($from, $start), $corner($to, $end)];
			}
		}

		return $triangles;
	}

	/**
	 * @param float[] $angles In degrees
	 *
	 * @return float[] Those from 0 to 360, each once, in order
	 */
	private static function within(array $angles)
	{
		$within = [];
		foreach ($angles as $angle) {
			if ($angle >= 0 && $angle <= 360) {
				$within[sprintf('%.6F', $angle)] = (float) $angle;
			}
		}
		sort($within);

		return $within;
	}

	/**
	 * @param float $angle
	 * @param float $first The angle of offset 0
	 * @param float $last  The angle of offset 1, which is not $first
	 *
	 * @return float The offset at the angle, held at 0 or 1 beyond the two
	 */
	private static function offset($angle, $first, $last)
	{
		return min(1.0, max(0.0, ($angle - $first) / ($last - $first)));
	}
}
