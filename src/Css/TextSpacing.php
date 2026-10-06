<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;

/**
 * The letter and word spacing of the text state. Mpdf keeps each as the value letter-spacing or word-spacing was
 * given in, and as the length it comes to at the current font size: fixedlSpacing, or false for none, and
 * minwSpacing, or 0 for none.
 */
final class TextSpacing
{

	/**
	 * Sets the letter and word spacing of the text state, each as given and as the length it comes to at the current
	 * font size
	 *
	 * @param Mpdf $mpdf
	 * @param SizeConverter $sizeConverter
	 * @param string $letterSpacing A letter-spacing, as given
	 * @param string $wordSpacing A word-spacing, as given
	 */
	public static function set(Mpdf $mpdf, SizeConverter $sizeConverter, $letterSpacing, $wordSpacing)
	{
		$mpdf->lSpacingCSS = $letterSpacing;
		$mpdf->wSpacingCSS = $wordSpacing;
		$mpdf->fixedlSpacing = self::length($sizeConverter, $letterSpacing, $mpdf->FontSize, false);
		$mpdf->minwSpacing = self::length($sizeConverter, $wordSpacing, $mpdf->FontSize, 0);
	}

	/**
	 * @param SizeConverter $sizeConverter
	 * @param string $spacing A letter-spacing or word-spacing, as given
	 * @param float $fontSize The current font size, in mm, which an em is taken of
	 * @param false|int $none What stands for no fixed spacing
	 *
	 * @return float|false|int The length the spacing is, in mm, or $none where it gives none or is normal
	 */
	public static function length(SizeConverter $sizeConverter, $spacing, $fontSize, $none)
	{
		if (($spacing || $spacing === '0') && strtoupper($spacing) !== 'NORMAL') {
			return $sizeConverter->convert($spacing, $fontSize);
		}

		return $none;
	}
}
