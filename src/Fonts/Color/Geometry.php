<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Utils\NumericString;

/**
 * The transforms, boxes and numbers the vector colour formats draw with. A transform is a PDF matrix,
 * [a, b, c, d, e, f], and a box [xMin, yMin, xMax, yMax].
 */
class Geometry
{

	const IDENTITY = [1, 0, 0, 1, 0, 0];

	/**
	 * @param float[] $first  A transform
	 * @param float[] $second Another
	 *
	 * @return float[] The transform doing the first, then the second
	 */
	public static function multiply(array $first, array $second)
	{
		list($a, $b, $c, $d, $e, $f) = $first;
		list($a2, $b2, $c2, $d2, $e2, $f2) = $second;

		return [
			$a * $a2 + $b * $c2,
			$a * $b2 + $b * $d2,
			$c * $a2 + $d * $c2,
			$c * $b2 + $d * $d2,
			$e * $a2 + $f * $c2 + $e2,
			$e * $b2 + $f * $d2 + $f2,
		];
	}

	/**
	 * @param float[] $box A box
	 *
	 * @return float[] The transform taking the unit square onto it
	 */
	public static function boxMatrix(array $box)
	{
		return [$box[2] - $box[0], 0, 0, $box[3] - $box[1], $box[0], $box[1]];
	}

	/**
	 * @param float[] $matrix A transform
	 *
	 * @return float[]|null The transform undoing it, or null where it flattens space to a line or a
	 *                      point, which nothing undoes
	 */
	public static function inverse(array $matrix)
	{
		list($a, $b, $c, $d, $e, $f) = $matrix;
		$determinant = $a * $d - $b * $c;
		if (abs($determinant) < 1e-9) {
			return null;
		}

		return [$d / $determinant, -$b / $determinant, -$c / $determinant, $a / $determinant, ($c * $f - $d * $e) / $determinant, ($b * $e - $a * $f) / $determinant];
	}

	/**
	 * @param float[] $matrix A transform
	 * @param float[] $box    A box
	 *
	 * @return float[] The box around the box's corners once transformed
	 */
	public static function bounds(array $matrix, array $box)
	{
		$xs = [];
		$ys = [];
		foreach (self::corners($box) as $corner) {
			$xs[] = $matrix[0] * $corner[0] + $matrix[2] * $corner[1] + $matrix[4];
			$ys[] = $matrix[1] * $corner[0] + $matrix[3] * $corner[1] + $matrix[5];
		}

		return [min($xs), min($ys), max($xs), max($ys)];
	}

	/**
	 * @param float[] $box [xMin, yMin, xMax, yMax]
	 *
	 * @return float[][] Its four corners, each [x, y]
	 */
	public static function corners(array $box)
	{
		return [[$box[0], $box[1]], [$box[0], $box[3]], [$box[2], $box[1]], [$box[2], $box[3]]];
	}

	/**
	 * @param float[] $matrix What takes a space to glyph space
	 * @param float[] $box    A box in glyph space
	 *
	 * @return float[]|null The box in that space, as the box around it there, or null where the space
	 *                      is flattened to a line or a point, and nothing in it shows
	 */
	public static function boxIn(array $matrix, array $box)
	{
		$inverse = self::inverse($matrix);

		return $inverse === null ? null : self::bounds($inverse, $box);
	}

	/**
	 * @param float[] $box [xMin, yMin, xMax, yMax]
	 *
	 * @return string The box's x, y, width and height, as re takes them
	 */
	public static function rectangle(array $box)
	{
		return self::numbers([$box[0], $box[1], $box[2] - $box[0], $box[3] - $box[1]]);
	}

	/**
	 * @param float[] $values
	 *
	 * @return string The values as content writes them, spaced
	 */
	public static function numbers(array $values)
	{
		return implode(' ', array_map([__CLASS__, 'number'], $values));
	}

	/**
	 * @param float $value
	 *
	 * @return string The value to five places and no more than it needs
	 */
	public static function number($value)
	{
		return NumericString::decimal($value, 5);
	}
}
