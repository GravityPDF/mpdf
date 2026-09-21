<?php

namespace Mpdf\Fonts\Color;

/**
 * Filling the clip with a gradient, the way both vector formats draw one: an axial or a radial
 * shading, its stops written out over as many spans as cover the area for REPEAT and REFLECT, and
 * where its alpha varies, masked by the brightness of a grey shading of the same shape. The colours are
 * interpolated premultiplied by alpha, as CPAL asks of COLR and SVG alike - see ColorLine - and a
 * radial gradient is cut where its radius reaches 0, since a shading cannot take a negative radius.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/cpal#interpolation-of-colors
 */
trait FillsWithGradients
{

	/**
	 * @param ColorLine      $line      The gradient's stops
	 * @param float[]        $geometry  Its coordinates for its first stop and its last: two points, or
	 *                                  two circles as [x, y, r] each
	 * @param float[]        $box       The area to cover, in the gradient's space
	 * @param GlyphResources $resources Where the shadings, and a mask, are registered
	 *
	 * @return string Content filling the clip with the gradient, or nothing where it is transparent
	 */
	private function gradientFill(ColorLine $line, array $geometry, array $box, GlyphResources $resources)
	{
		list($from, $to) = $line->extend === ColorLine::PAD ? [0, 1] : self::spans($geometry, $box);
		$cut = self::nonNegative(self::along($geometry, $from, $to), $line->normalised($from, $to));
		if ($cut === null) {
			return '';
		}
		list($coords, $stops) = $cut;

		$colours = [];
		foreach (ColorLine::premultiplied($stops) as $stop) {
			$colours[] = [$stop[0], $stop[1]];
		}
		$alphas = [];
		foreach ($stops as $stop) {
			$alphas[] = [$stop[0], [$stop[2]]];
		}

		$content = $resources->shading(['coords' => $coords, 'stops' => $colours]) . " sh\n";
		$opacity = $line->opacity();
		if ($opacity === null) {
			$mask = $resources->shading(['coords' => $coords, 'stops' => $alphas]) . " sh\n";

			return sprintf("q %s\n", $resources->softMask($mask, $box, true)) . $content . "Q\n";
		}

		if ($opacity <= 0) {
			return '';
		}

		return $opacity < 1 ? sprintf("q %s\n", $resources->alpha($opacity)) . $content . "Q\n" : $content;
	}

	/**
	 * The whole spans of a repeated or reflected gradient's stops needed to cover an area
	 *
	 * @param float[] $geometry The shading's coordinates for offsets 0 and 1: two points, or two circles
	 * @param float[] $box      The area, in the gradient's space
	 *
	 * @return int[] [the first span, the span after the last], 0 being the stops' own
	 */
	private static function spans(array $geometry, array $box)
	{
		$corners = [[$box[0], $box[1]], [$box[0], $box[3]], [$box[2], $box[1]], [$box[2], $box[3]]];

		if (count($geometry) === 4) {
			// Each corner's offset along the axis
			list($x0, $y0, $x1, $y1) = $geometry;
			$dx = $x1 - $x0;
			$dy = $y1 - $y0;
			$offsets = [];
			foreach ($corners as $corner) {
				$offsets[] = (($corner[0] - $x0) * $dx + ($corner[1] - $y0) * $dy) / ($dx * $dx + $dy * $dy);
			}

			return [(int) max(-ColorLine::MAX_REPEATS, floor(min($offsets))), (int) min(ColorLine::MAX_REPEATS, ceil(max($offsets)))];
		}

		// Out from the stops each way until a circle takes in every corner, or shrinks to nothing
		$spans = [];
		foreach ([-1, 1] as $direction) {
			$span = $direction < 0 ? 0 : 1;
			while (abs($span) < ColorLine::MAX_REPEATS) {
				$circle = self::along($geometry, $span, $span);
				if ($circle[2] <= 0 || self::covers($circle, $corners)) {
					break;
				}
				$span += $direction;
			}
			$spans[] = $span;
		}

		return $spans;
	}

	/**
	 * @param float[]   $circle  [x, y, r]
	 * @param float[][] $corners
	 *
	 * @return bool Whether every corner lies within the circle
	 */
	private static function covers(array $circle, array $corners)
	{
		foreach ($corners as $corner) {
			if (hypot($corner[0] - $circle[0], $corner[1] - $circle[1]) > $circle[2]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A shading's coordinates moved to other offsets along it
	 *
	 * @param float[] $geometry Its coordinates for offsets 0 and 1: two points, or two circles
	 * @param float   $from     The offset the new coordinates start at
	 * @param float   $to       The offset they end at
	 *
	 * @return float[] The coordinates for offsets $from and $to; a radius may come out below 0
	 */
	private static function along(array $geometry, $from, $to)
	{
		$half = count($geometry) / 2;
		$start = [];
		$end = [];
		for ($i = 0; $i < $half; $i++) {
			$start[] = $geometry[$i] + $from * ($geometry[$i + $half] - $geometry[$i]);
			$end[] = $geometry[$i] + $to * ($geometry[$i + $half] - $geometry[$i]);
		}

		return array_merge($start, $end);
	}

	/**
	 * A radial shading cut to where its radius is not below 0, all a PDF shading can draw, its stops cut
	 * to match. An axial shading is left as it is.
	 *
	 * @param float[] $coords Two points, or two circles as [x0, y0, r0, x1, y1, r1]
	 * @param array[] $stops  The stops from the first to the second, from 0 to 1
	 *
	 * @return array|null [coords, stops], or null where the radius is nowhere above 0
	 */
	private static function nonNegative(array $coords, array $stops)
	{
		if (count($coords) === 4 || ($coords[2] >= 0 && $coords[5] >= 0)) {
			return [$coords, $stops];
		}

		list(, , $r0, , , $r1) = $coords;
		if ($r0 <= 0 && $r1 <= 0) {
			return null;
		}

		$tip = $r0 / ($r0 - $r1);
		list($from, $to) = $r0 < 0 ? [$tip, 1] : [0, $tip];
		$cut = self::along($coords, $from, $to);
		$cut[$r0 < 0 ? 2 : 5] = 0;

		return [$cut, ColorLine::between($stops, $from, $to)];
	}
}
