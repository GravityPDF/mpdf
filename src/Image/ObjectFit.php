<?php

namespace Mpdf\Image;

use Mpdf\SizeConverter;

/**
 * Where an image given object-fit or object-position is drawn inside its content box
 *
 * Such an image carries object_fit, keyed fit (fill, contain, cover, none or scale-down), position ([x percent,
 * x offset, y percent, y offset], offsets in millimetres) and natural ([width, height] it has when given neither).
 */
class ObjectFit
{

	/**
	 * @var string[] The values of object-fit
	 */
	const FITS = ['fill', 'contain', 'cover', 'none', 'scale-down'];

	/**
	 * @var int[] object-position's initial value, 50% 50%
	 */
	const CENTRE = [50, 0, 50, 0];

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

		switch ($fit) {
			case 'contain':
				$scale = min($boxW / $naturalW, $boxH / $naturalH);
				break;
			case 'cover':
				$scale = max($boxW / $naturalW, $boxH / $naturalH);
				break;
			case 'none':
				$scale = 1;
				break;
			case 'scale-down':
				$scale = min(1, $boxW / $naturalW, $boxH / $naturalH);
				break;
			default:
				return [$boxW, $boxH];
		}

		return [$naturalW * $scale, $naturalH * $scale];
	}

	/**
	 * Where the image is drawn: fitted into its content box, keyed INNER-X, INNER-Y, INNER-WIDTH and INNER-HEIGHT,
	 * and placed by its object-position
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
		$fit = $objattr['object_fit'];
		list($w, $h) = self::size($fit['fit'], $boxW, $boxH, $fit['natural'][0] / $k, $fit['natural'][1] / $k);

		$position = $fit['position'];
		$x = $objattr['INNER-X'] + $position[0] / 100 * ($boxW - $w) + $position[1] / $k;
		$y = $objattr['INNER-Y'] + $position[2] / 100 * ($boxH - $h) + $position[3] / $k;

		return [$x, $y, $w, $h];
	}

	/**
	 * Reads object-position: one or two keywords, percentages or lengths, as background-position is read. A single
	 * value is the horizontal one, or the vertical one when it is top or bottom, and the other axis is centred.
	 *
	 * @param string $value
	 * @param \Mpdf\SizeConverter $sizeConverter
	 * @param float $fontSize What em is relative to
	 *
	 * @return float[]|null [x percent, x offset, y percent, y offset], or null when the value is not a position
	 */
	public static function position($value, SizeConverter $sizeConverter, $fontSize)
	{
		$bits = preg_split('/\s+/', strtolower(trim($value)));
		if (count($bits) === 1) {
			$bits = self::isVertical($bits[0]) ? ['center', $bits[0]] : [$bits[0], 'center'];
		}
		if (count($bits) !== 2) {
			return null;
		}

		// A vertical keyword first, or a horizontal one second, means the pair is written the other way round
		if (self::isVertical($bits[0]) || self::isHorizontal($bits[1])) {
			$bits = [$bits[1], $bits[0]];
		}
		if (self::isVertical($bits[0]) || self::isHorizontal($bits[1])) {
			return null;
		}

		$x = self::axis($bits[0], $sizeConverter, $fontSize);
		$y = self::axis($bits[1], $sizeConverter, $fontSize);

		return $x === null || $y === null ? null : array_merge($x, $y);
	}

	/**
	 * One axis of object-position
	 *
	 * @param string $bit A keyword, a percentage or a length
	 * @param \Mpdf\SizeConverter $sizeConverter
	 * @param float $fontSize
	 *
	 * @return float[]|null The percentage of the room left in the box, and an offset in millimetres
	 */
	private static function axis($bit, SizeConverter $sizeConverter, $fontSize)
	{
		if (isset(self::$keywords[$bit])) {
			return [self::$keywords[$bit], 0];
		}
		if (substr($bit, -1) === '%' && is_numeric(substr($bit, 0, -1))) {
			return [(float) $bit, 0];
		}
		// A number with no unit is a length only when it is zero
		if (!$sizeConverter->isLength($bit) || (is_numeric($bit) && (float) $bit != 0)) {
			return null;
		}

		return [0, $sizeConverter->convert($bit, 0, $fontSize, false)];
	}

	/**
	 * Whether a part of object-position can only be vertical
	 *
	 * @param string $bit
	 *
	 * @return bool
	 */
	private static function isVertical($bit)
	{
		return $bit === 'top' || $bit === 'bottom';
	}

	/**
	 * Whether a part of object-position can only be horizontal
	 *
	 * @param string $bit
	 *
	 * @return bool
	 */
	private static function isHorizontal($bit)
	{
		return $bit === 'left' || $bit === 'right';
	}

}
