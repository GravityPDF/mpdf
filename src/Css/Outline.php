<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\SizeConverter;

/**
 * The outline longhands of an element, read into what drawing it needs. An outline takes no space in layout: it is
 * drawn outside the border box, outline-offset away from it, over whatever is there.
 */
class Outline
{

	/**
	 * The styles an outline is drawn in. auto is drawn solid, and hidden is not an outline style
	 */
	const STYLES = ['solid', 'double', 'dotted', 'dashed', 'groove', 'ridge', 'inset', 'outset'];

	/**
	 * The outline an element's properties give it
	 *
	 * @param array $properties The element's CSS properties, upper-cased, with currentColor resolved
	 * @param SizeConverter $sizeConverter
	 * @param ColorConverter $colorConverter
	 * @param float $fontSize For a length in em
	 * @param array $warnings Where a colour the PDF/A or PDF/X profile cannot use is reported
	 *
	 * @return array|null Keyed w (width), style, c (colour) and offset, or null where nothing is drawn
	 */
	public static function parse(array $properties, SizeConverter $sizeConverter, ColorConverter $colorConverter, $fontSize, array &$warnings = [])
	{
		$style = isset($properties['OUTLINE-STYLE']) ? strtolower(trim($properties['OUTLINE-STYLE'])) : 'none';
		if ($style === 'auto') {
			$style = 'solid';
		}
		if (!in_array($style, self::STYLES, true) || !isset($properties['OUTLINE-COLOR'])) {
			return null;
		}

		$width = isset($properties['OUTLINE-WIDTH']) ? $properties['OUTLINE-WIDTH'] : 'medium';
		$width = $sizeConverter->convert($width, 0, $fontSize, false);
		$color = $colorConverter->convert($properties['OUTLINE-COLOR'], $warnings);
		if ($width <= 0 || !$color) {
			return null;
		}

		$offset = isset($properties['OUTLINE-OFFSET']) ? $sizeConverter->convert($properties['OUTLINE-OFFSET'], 0, $fontSize, false) : 0;

		return ['w' => $width, 'style' => $style, 'c' => $color, 'offset' => $offset];
	}
}
