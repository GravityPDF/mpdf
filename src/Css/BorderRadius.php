<?php

namespace Mpdf\Css;

use Mpdf\SizeConverter;
use Mpdf\Utils\NumericString;

/**
 * The border-radius of a block, as blk[] keeps it: each corner's horizontal and vertical radius in millimetres under
 * border_radius_<corner>_<axis>, and the percentages under border_radius_percent. A percentage is of the border box,
 * horizontal of its width and vertical of its height, which the block has only once it is painted.
 */
class BorderRadius
{

	/**
	 * The corner and axis each longhand sets, the axis as an index: 0 horizontal, 1 vertical
	 */
	const PROPERTIES = [
		'BORDER-TOP-LEFT-RADIUS-H' => ['TL', 0],
		'BORDER-TOP-LEFT-RADIUS-V' => ['TL', 1],
		'BORDER-TOP-RIGHT-RADIUS-H' => ['TR', 0],
		'BORDER-TOP-RIGHT-RADIUS-V' => ['TR', 1],
		'BORDER-BOTTOM-RIGHT-RADIUS-H' => ['BR', 0],
		'BORDER-BOTTOM-RIGHT-RADIUS-V' => ['BR', 1],
		'BORDER-BOTTOM-LEFT-RADIUS-H' => ['BL', 0],
		'BORDER-BOTTOM-LEFT-RADIUS-V' => ['BL', 1],
	];

	const AXES = ['H', 'V'];

	/**
	 * Keep one radius longhand on a block. A length is converted now; a percentage waits for the box, and leaves the
	 * length at 0 so the block counts as rounded.
	 *
	 * @param array $blk
	 * @param string $property One of PROPERTIES
	 * @param string $value
	 * @param SizeConverter $sizeConverter
	 * @param float $fontSize For a length in em
	 */
	public static function set(array &$blk, $property, $value, SizeConverter $sizeConverter, $fontSize)
	{
		list($corner, $axis) = self::PROPERTIES[$property];

		if (NumericString::containsPercentChar($value)) {
			$blk['border_radius_percent'][$corner][$axis] = (float) $value;
			$blk[self::key($corner, $axis)] = 0;
			return;
		}

		unset($blk['border_radius_percent'][$corner][$axis]);
		$blk[self::key($corner, $axis)] = $sizeConverter->convert($value, 0, $fontSize, false);
	}

	/**
	 * Whether any radius longhand reached the block
	 *
	 * @param array $blk
	 *
	 * @return bool
	 */
	public static function isDeclared(array $blk)
	{
		foreach (self::PROPERTIES as $slot) {
			if (isset($blk[self::key($slot[0], $slot[1])])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The radii of the block's corners, keyed TL/TR/BR/BL as [horizontal, vertical] in millimetres, with the
	 * percentages resolved against the box being painted.
	 *
	 * @param array $blk
	 * @param float $width The border box's width
	 * @param float $height The border box's height, or that of the part of it on this page
	 *
	 * @return array
	 */
	public static function radii(array $blk, $width, $height)
	{
		$radii = ['TL' => [0, 0], 'TR' => [0, 0], 'BR' => [0, 0], 'BL' => [0, 0]];

		foreach (self::PROPERTIES as $slot) {
			$key = self::key($slot[0], $slot[1]);
			if (isset($blk[$key])) {
				$radii[$slot[0]][$slot[1]] = $blk[$key];
			}
		}

		if (isset($blk['border_radius_percent'])) {
			foreach ($blk['border_radius_percent'] as $corner => $shares) {
				foreach ($shares as $axis => $share) {
					$radii[$corner][$axis] = $share / 100 * ($axis ? $height : $width);
				}
			}
		}

		return $radii;
	}

	/**
	 * The blk[] key of a corner's radius along one axis
	 *
	 * @param string $corner TL, TR, BR or BL
	 * @param int $axis 0 horizontal, 1 vertical
	 *
	 * @return string
	 */
	private static function key($corner, $axis)
	{
		return 'border_radius_' . $corner . '_' . self::AXES[$axis];
	}
}
