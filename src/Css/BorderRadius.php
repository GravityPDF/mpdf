<?php

namespace Mpdf\Css;

use Mpdf\RoundedBox;
use Mpdf\SizeConverter;
use Mpdf\Utils\NumericString;

/**
 * The border-radius longhands of an element, read into radii keyed TL/TR/BR/BL as [horizontal, vertical] in
 * millimetres, with percentages kept apart in the same shape until the border box is known: a horizontal percentage
 * is of its width and a vertical one of its height. A block keeps its radii in blk[] under border_radius_<corner>_<axis>
 * and its percentages under border_radius_percent.
 */
class BorderRadius
{

	/**
	 * The corner, axis (0 horizontal, 1 vertical) and blk[] key of each longhand
	 */
	const PROPERTIES = [
		'BORDER-TOP-LEFT-RADIUS-H' => ['TL', 0, 'border_radius_TL_H'],
		'BORDER-TOP-LEFT-RADIUS-V' => ['TL', 1, 'border_radius_TL_V'],
		'BORDER-TOP-RIGHT-RADIUS-H' => ['TR', 0, 'border_radius_TR_H'],
		'BORDER-TOP-RIGHT-RADIUS-V' => ['TR', 1, 'border_radius_TR_V'],
		'BORDER-BOTTOM-RIGHT-RADIUS-H' => ['BR', 0, 'border_radius_BR_H'],
		'BORDER-BOTTOM-RIGHT-RADIUS-V' => ['BR', 1, 'border_radius_BR_V'],
		'BORDER-BOTTOM-LEFT-RADIUS-H' => ['BL', 0, 'border_radius_BL_H'],
		'BORDER-BOTTOM-LEFT-RADIUS-V' => ['BL', 1, 'border_radius_BL_V'],
	];

	/**
	 * The radii an element's properties give it, and the percentages among them
	 *
	 * @param array $properties The element's CSS properties, upper-cased
	 * @param SizeConverter $sizeConverter
	 * @param float $fontSize For a length in em
	 *
	 * @return array [$radii, $percent]: the corners given a radius, a percentage axis at 0, and the percentages keyed
	 * the same way
	 */
	public static function parse(array $properties, SizeConverter $sizeConverter, $fontSize)
	{
		$radii = [];
		$percent = [];

		foreach (self::PROPERTIES as $property => $slot) {
			if (!isset($properties[$property])) {
				continue;
			}
			list($corner, $axis) = $slot;
			if (!isset($radii[$corner])) {
				$radii[$corner] = [0, 0];
			}
			if (NumericString::containsPercentChar($properties[$property])) {
				$percent[$corner][$axis] = (float) $properties[$property];
			} else {
				$radii[$corner][$axis] = $sizeConverter->convert($properties[$property], 0, $fontSize, false);
			}
		}

		return [$radii, $percent];
	}

	/**
	 * Radii with their percentages resolved against the border box
	 *
	 * @param array $radii As parse() gives them
	 * @param array $percent As parse() gives them
	 * @param float $width The border box's width
	 * @param float $height Its height
	 *
	 * @return array
	 */
	public static function resolve(array $radii, array $percent, $width, $height)
	{
		$box = [$width, $height];

		foreach ($percent as $corner => $shares) {
			foreach ($shares as $axis => $share) {
				$radii[$corner][$axis] = $share / 100 * $box[$axis];
			}
		}

		return $radii;
	}

	/**
	 * Keep one longhand on a block. A length is converted now; a percentage waits for the box, and leaves the length at
	 * 0 so the block counts as rounded.
	 *
	 * @param array $blk
	 * @param string $property One of PROPERTIES
	 * @param string $value
	 * @param SizeConverter $sizeConverter
	 * @param float $fontSize
	 */
	public static function set(array &$blk, $property, $value, SizeConverter $sizeConverter, $fontSize)
	{
		list($corner, $axis, $key) = self::PROPERTIES[$property];
		list($radii, $percent) = self::parse([$property => $value], $sizeConverter, $fontSize);

		$blk[$key] = $radii[$corner][$axis];
		unset($blk['border_radius_percent'][$corner][$axis]);
		if (isset($percent[$corner][$axis])) {
			$blk['border_radius_percent'][$corner][$axis] = $percent[$corner][$axis];
		}
	}

	/**
	 * Whether any longhand reached the block
	 *
	 * @param array $blk
	 *
	 * @return bool
	 */
	public static function isDeclared(array $blk)
	{
		foreach (self::PROPERTIES as $slot) {
			if (isset($blk[$slot[2]])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The radii of a block's four corners, with its percentages resolved against the box being painted
	 *
	 * @param array $blk
	 * @param float $width The border box's width
	 * @param float $height The border box's height, or that of the part of it on this page
	 *
	 * @return array
	 */
	public static function radii(array $blk, $width, $height)
	{
		$radii = RoundedBox::SQUARE;

		foreach (self::PROPERTIES as $slot) {
			if (isset($blk[$slot[2]])) {
				$radii[$slot[0]][$slot[1]] = $blk[$slot[2]];
			}
		}

		return self::resolve($radii, isset($blk['border_radius_percent']) ? $blk['border_radius_percent'] : [], $width, $height);
	}
}
