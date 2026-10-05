<?php

namespace Mpdf\Css;

use Mpdf\CssMode;
use Mpdf\Mpdf;

/**
 * The text decorations drawn over an element's text, as a set of their own rather than as an inherited property.
 *
 * Under CssMode::STANDARD an element's underline, overline and line-through propagate to its in-flow descendants and
 * are drawn over their text whatever they set, in the colour and at the thickness and position of the element that
 * set them. text-decoration: none on a descendant removes only its own decoration. Floats, positioned blocks and
 * inline blocks take none of their ancestors' decorations, and nor do tables, whose cells start from a text state of
 * their own.
 *
 * Each decoration is a TextVars bit in the text state, with the text parameter that says how the element that set it
 * draws it: its colour, font, font size and baseline shift, as Mpdf::setCSS() records them. mPDF draws one line of each
 * kind, so where an element and its ancestor set the same decoration the element's own is drawn.
 *
 * Under CssMode::LEGACY text-decoration is inherited as it was in mPDF v7.
 */
final class TextDecorations
{

	/**
	 * Each decoration's TextVars bit, and the text parameter that holds how it is drawn
	 */
	const PARAMETERS = [
		TextVars::FD_UNDERLINE => 'u-decoration',
		TextVars::FD_LINETHROUGH => 's-decoration',
		TextVars::FD_OVERLINE => 'o-decoration',
	];

	/**
	 * Under CssMode::STANDARD, starts an element's text with the decorations of the element it is in, or with none when
	 * the element is floated, positioned out of the flow or an inline block. Call it before Mpdf::setCSS() adds the
	 * element's own.
	 *
	 * @param Mpdf $mpdf
	 * @param array $properties The element's merged CSS
	 * @param array|null $parent The text state of the element it is in, as Mpdf::saveInlineProperties() saves it, or
	 *                           null when that is the current text state
	 */
	public static function enter(Mpdf $mpdf, array $properties, $parent = null)
	{
		if ($mpdf->cssMode === CssMode::LEGACY) {
			return;
		}

		$propagate = self::propagateInto($properties);
		if ($propagate && $parent === null) {
			return;
		}

		foreach (self::PARAMETERS as $bit => $parameter) {
			if ($propagate && isset($parent['textvar'], $parent['textparam'][$parameter]) && ($parent['textvar'] & $bit)) {
				$mpdf->textvar |= $bit;
				$mpdf->textparam[$parameter] = $parent['textparam'][$parameter];
			} else {
				$mpdf->textvar &= ~$bit;
				unset($mpdf->textparam[$parameter]);
			}
		}
	}

	/**
	 * Whether the decorations an element sets are seen. Under CssMode::STANDARD a decoration is drawn in the colour of
	 * the element that sets it, so an element whose color is transparent, set or inherited, draws its own unseen, and
	 * the decorations it is in stay drawn over its text. Call it once Mpdf::setCSS() has applied the element's color.
	 *
	 * @param Mpdf $mpdf
	 *
	 * @return bool
	 */
	public static function seen(Mpdf $mpdf)
	{
		return $mpdf->cssMode === CssMode::LEGACY || empty($mpdf->textparam['transparent']);
	}

	/**
	 * @param array $properties An element's merged CSS
	 *
	 * @return bool Whether the element takes the decorations of the element it is in
	 */
	private static function propagateInto(array $properties)
	{
		$float = isset($properties['FLOAT']) ? strtolower($properties['FLOAT']) : '';
		$position = isset($properties['POSITION']) ? strtolower($properties['POSITION']) : '';
		$display = isset($properties['DISPLAY']) ? strtolower($properties['DISPLAY']) : '';

		return !in_array($float, ['left', 'right'], true)
			&& !in_array($position, ['absolute', 'fixed'], true)
			&& !in_array($display, ['inline-block', 'inline-table'], true);
	}
}
