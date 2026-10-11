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
 * draws it: its colour, font, font size and baseline shift, as Mpdf::setCSS() records them, and under CssMode::STANDARD
 * any style, thickness and underline position its CSS sets. mPDF draws one line of each kind, so where an element and
 * its ancestor set the same decoration the element's own is drawn.
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
	 * Each decoration's TextVars bit, and the keyword that sets it
	 */
	const LINES = [
		TextVars::FD_UNDERLINE => 'underline',
		TextVars::FD_LINETHROUGH => 'line-through',
		TextVars::FD_OVERLINE => 'overline',
	];

	/**
	 * The keys of a decoration's text parameter that Mpdf::setCSS() sets only where the element's CSS asks for them, as
	 * keys, so an element's own decoration does not keep those of the one it replaces
	 */
	const DRAWN_AS = ['style' => true, 'thickness' => true, 'offset' => true, 'under' => true];

	/**
	 * The values of text-decoration-style
	 */
	const STYLES = ['solid', 'double', 'dotted', 'dashed', 'wavy'];

	/**
	 * Which way from its position a decoration's second line goes, and a wave's first arch: away from the text for an
	 * underline and an overline, and either side of the line for a line-through. In PDF units, which run up the page
	 */
	const UNDER = -1;
	const THROUGH = 0;
	const OVER = 1;

	/**
	 * Where a cubic Bézier arch's control points go along a half wave, as a fraction of it, to follow a sine
	 */
	const SINE_CONTROL = 0.3642;

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
	 * @param Mpdf $mpdf
	 *
	 * @return array The decorations of the current text state, in the shape enter() takes a parent's
	 */
	public static function of(Mpdf $mpdf)
	{
		$decorations = ['textvar' => 0, 'textparam' => []];
		foreach (self::PARAMETERS as $bit => $parameter) {
			if (($mpdf->textvar & $bit) && isset($mpdf->textparam[$parameter])) {
				$decorations['textvar'] |= $bit;
				$decorations['textparam'][$parameter] = $mpdf->textparam[$parameter];
			}
		}

		return $decorations;
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

	/**
	 * The PDF operators that stroke a decoration in a style other than solid, in the stroke colour and line width already
	 * set. All lengths are in PDF units.
	 *
	 * - double: two lines of the thickness, a thickness apart, the second away from the text
	 * - dotted: round dots the thickness across, a dot's width apart
	 * - dashed: dashes three thicknesses long, two apart
	 * - wavy: a sine three thicknesses high from crest to trough, of half wavelength three thicknesses, fitted to the
	 *   width with whole half waves, each a cubic Bézier arch
	 *
	 * @param string $style One of STYLES other than solid
	 * @param float $x Where the decoration starts
	 * @param float $y Where its centre line runs
	 * @param float $width
	 * @param float $thickness
	 * @param int $side UNDER, THROUGH or OVER
	 *
	 * @return string
	 */
	public static function stroke($style, $x, $y, $width, $thickness, $side)
	{
		$line = function ($y) use ($x, $width) {
			return sprintf('%.3F %.3F m %.3F %.3F l S', $x, $y, $x + $width, $y);
		};

		switch ($style) {
			case 'double':
				$first = $side === self::THROUGH ? $y + $thickness : $y;
				$second = $side === self::THROUGH ? $y - $thickness : $y + 2 * $thickness * $side;

				return $line($first) . ' ' . $line($second);

			case 'dotted':
				return sprintf('1 J [0 %.3F] 0 d ', 2 * $thickness) . $line($y) . ' [] 0 d';

			case 'dashed':
				return sprintf('[%.3F %.3F] 0 d ', 3 * $thickness, 2 * $thickness) . $line($y) . ' [] 0 d';

			case 'wavy':
				$half = 3 * $thickness;
				$count = max(1, (int) round($width / $half));
				$half = $width / $count;
				// A cubic's control points at height h raise its middle by 3h/4
				$height = 1.5 * $thickness * 4 / 3;
				$crest = $side === self::THROUGH ? self::OVER : $side;

				$path = sprintf('1 j %.3F %.3F m', $x, $y);
				for ($i = 0; $i < $count; $i++) {
					$start = $x + $i * $half;
					$control = $y + $crest * $height;
					$path .= sprintf(
						' %.3F %.3F %.3F %.3F %.3F %.3F c',
						$start + self::SINE_CONTROL * $half,
						$control,
						$start + (1 - self::SINE_CONTROL) * $half,
						$control,
						$start + $half,
						$y
					);
					$crest = -$crest;
				}

				return $path . ' S';
		}

		return $line($y);
	}
}
