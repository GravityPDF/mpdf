<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;

/**
 * The font values that are computed from the parent element's: font-weight, whose bolder and lighter step from the
 * parent's weight, and font-size: larger and smaller. Used in the standard CSS mode
 */
final class RelativeFontValues
{

	const NORMAL_WEIGHT = 400;

	const BOLD_WEIGHT = 700;

	/**
	 * What bolder and lighter give, from CSS Fonts Level 4: each row is the lowest parent weight it applies to, then
	 * bolder and lighter. Null keeps the parent's weight
	 */
	private static $relativeWeights = [
		[900, null, 700],
		[750, 900, 700],
		[550, 900, 400],
		[350, 700, 100],
		[100, 400, 100],
		[0, 400, null],
	];

	/**
	 * The weight an element's font-weight computes to
	 *
	 * @param string $value normal, bold, bolder, lighter, or a number from 1 to 1000
	 * @param int|float $parentWeight The parent element's computed weight
	 *
	 * @return int|float|null Null for a value that is not a font weight
	 */
	public static function weight($value, $parentWeight)
	{
		$value = strtolower(trim($value));

		switch ($value) {
			case 'normal':
				return self::NORMAL_WEIGHT;

			case 'bold':
				return self::BOLD_WEIGHT;

			case 'bolder':
			case 'lighter':
				foreach (self::$relativeWeights as $row) {
					if ($parentWeight >= $row[0]) {
						$weight = $value === 'bolder' ? $row[1] : $row[2];

						return $weight === null ? $parentWeight : $weight;
					}
				}

				return null;
		}

		if (preg_match('/^\d+(\.\d+)?$/', $value, $m) && $value >= 1 && $value <= 1000) {
			return isset($m[1]) ? (float) $value : (int) $value;
		}

		return null;
	}

	/**
	 * Whether a weight is drawn with the bold face of a family that has a regular face and a bold one. Those are
	 * weights 400 and 700, and CSS Fonts' matching gives the bold face to any weight above 500: of the hundreds, 600 and up.
	 *
	 * A family registered with more weights would instead be given the face nearest the weight by the same matching,
	 * which would need its faces keyed by weight rather than by the B style.
	 *
	 * @param int|float $weight
	 *
	 * @return bool
	 */
	public static function isBold($weight)
	{
		return $weight > 500;
	}

	/**
	 * The computed weight of text drawn in the B style given. Some tags and a replayed text buffer set the style on its
	 * own, so the weight is taken while it agrees with the style, and otherwise is bold's or normal's
	 *
	 * @param int|float $weight
	 * @param bool $bold Whether the B style is set
	 *
	 * @return int|float
	 */
	public static function weightForStyle($weight, $bold)
	{
		if (self::isBold($weight) === (bool) $bold) {
			return $weight;
		}

		return $bold ? self::BOLD_WEIGHT : self::NORMAL_WEIGHT;
	}

	/**
	 * The computed font-weight of the parent of the element setCSS() is styling, which bolder and lighter step from. A
	 * block starts from the state its parent block saved, and a table cell from its table's weight; an inline element
	 * is styled in its parent's state
	 *
	 * @param Mpdf $mpdf
	 * @param string $type As setCSS() takes it
	 *
	 * @return int|float
	 */
	public static function parentWeight(Mpdf $mpdf, $type)
	{
		if ($type === 'BLOCK' && $mpdf->blklvl > 0 && isset($mpdf->blk[$mpdf->blklvl - 1]['InlineProperties']['weight'])) {
			return $mpdf->blk[$mpdf->blklvl - 1]['InlineProperties']['weight'];
		}

		if ($type === 'TABLECELL') {
			return self::tableWeight($mpdf->base_table_properties);
		}

		return self::weightForStyle($mpdf->fontWeight, $mpdf->B);
	}

	/**
	 * @param array $tableProperties The properties the table being written hands its cells, as Mpdf::$base_table_properties
	 *
	 * @return int|float The computed font-weight of the table, which its cells inherit
	 */
	public static function tableWeight(array $tableProperties)
	{
		return isset($tableProperties['FONT-WEIGHT']) ? (float) $tableProperties['FONT-WEIGHT'] : self::NORMAL_WEIGHT;
	}

	/**
	 * @param string $value A font-size value
	 *
	 * @return float|null What larger or smaller multiply the parent's size by, or null for any other value
	 */
	public static function sizeRatio($value)
	{
		switch (strtolower(trim($value))) {
			case 'larger':
				return 1.2;

			case 'smaller':
				return 1 / 1.2;
		}

		return null;
	}
}
