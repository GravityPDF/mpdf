<?php

namespace Mpdf\Image;

/**
 * Where an image given object-fit or object-position is drawn inside its content box
 *
 * An image carries object_fit (contain, cover, none or scale-down; fill is left out, as it draws the image at the size
 * of the box), object_position ([x percent, x offset, y percent, y offset], offsets in millimetres) and natural_w and
 * natural_h, the size the image has when given neither a width nor a height.
 */
class ObjectFit
{

	/**
	 * @var string[] The object-fit values that draw the image other than at the size of its box
	 */
	const FITS = ['contain', 'cover', 'none', 'scale-down'];

	/**
	 * The keywords of object-position, as the percentage they stand for
	 *
	 * @var array<string, int>
	 */
	private static $keywords = ['left' => 0, 'top' => 0, 'center' => 50, 'right' => 100, 'bottom' => 100];

	/**
	 * The size the image is drawn at in a box of the given size
	 *
	 * @param string $fit
	 * @param float $boxW
	 * @param float $boxH
	 * @param float $naturalW
	 * @param float $naturalH
	 *
	 * @return float[] Width and height
	 */
	public static function size($fit, $boxW, $boxH, $naturalW, $naturalH)
	{
		if ($naturalW <= 0 || $naturalH <= 0) {
			return [$boxW, $boxH];
		}

		$contain = min($boxW / $naturalW, $boxH / $naturalH);
		$scales = [
			'contain' => $contain,
			'cover' => max($boxW / $naturalW, $boxH / $naturalH),
			'none' => 1,
			'scale-down' => min(1, $contain),
		];
		$scale = isset($scales[$fit]) ? $scales[$fit] : null;
		if ($scale === null) {
			return [$boxW, $boxH];
		}

		return [$naturalW * $scale, $naturalH * $scale];
	}

	/**
	 * The image's rectangle on the page: its content box, keyed INNER-X, INNER-Y, INNER-WIDTH and INNER-HEIGHT, with
	 * the image fitted into it and placed by its object-position
	 *
	 * @param array $objattr The image, placed on the page
	 * @param float $k The factor a table has shrunk the image by
	 *
	 * @return float[] x, y, width and height
	 */
	public static function place(array $objattr, $k)
	{
		$boxW = $objattr['INNER-WIDTH'];
		$boxH = $objattr['INNER-HEIGHT'];
		$fit = isset($objattr['object_fit']) ? $objattr['object_fit'] : 'fill';
		list($w, $h) = self::size($fit, $boxW, $boxH, $objattr['natural_w'] / $k, $objattr['natural_h'] / $k);

		$position = isset($objattr['object_position']) ? $objattr['object_position'] : [50, 0, 50, 0];
		$x = $objattr['INNER-X'] + $position[0] / 100 * ($boxW - $w) + $position[1] / $k;
		$y = $objattr['INNER-Y'] + $position[2] / 100 * ($boxH - $h) + $position[3] / $k;

		return [$x, $y, $w, $h];
	}

	/**
	 * Reads object-position: one or two keywords, percentages or lengths, as background-position is read. A single
	 * value is the horizontal one, or the vertical one when it is top or bottom, and the other axis is centred.
	 *
	 * @param string $value
	 * @param callable $length Turns a length into millimetres
	 *
	 * @return float[]|null [x percent, x offset, y percent, y offset], or null when the value is not a position
	 */
	public static function position($value, $length)
	{
		$bits = preg_split('/\s+/', strtolower(trim($value)));
		if (count($bits) === 1) {
			$bits = in_array($bits[0], ['top', 'bottom'], true) ? ['center', $bits[0]] : [$bits[0], 'center'];
		}
		if (count($bits) !== 2) {
			return null;
		}

		// A vertical keyword first, or a horizontal one second, means the pair is written the other way round
		if (in_array($bits[0], ['top', 'bottom'], true) || in_array($bits[1], ['left', 'right'], true)) {
			$bits = [$bits[1], $bits[0]];
		}
		if (in_array($bits[0], ['top', 'bottom'], true) || in_array($bits[1], ['left', 'right'], true)) {
			return null;
		}

		$x = self::axis($bits[0], $length);
		$y = self::axis($bits[1], $length);
		if ($x === null || $y === null) {
			return null;
		}

		return array_merge($x, $y);
	}

	/**
	 * One axis of object-position
	 *
	 * @param string $bit A keyword, a percentage or a length
	 * @param callable $length
	 *
	 * @return float[]|null The percentage of the room left in the box, and an offset in millimetres
	 */
	private static function axis($bit, $length)
	{
		if (isset(self::$keywords[$bit])) {
			return [self::$keywords[$bit], 0];
		}
		if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)%$/', $bit)) {
			return [(float) $bit, 0];
		}
		if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([a-z]+)?$/', $bit, $m)) {
			if (!isset($m[2]) && (float) $bit != 0) {
				return null;
			}

			return [0, call_user_func($length, $bit)];
		}

		return null;
	}

}
