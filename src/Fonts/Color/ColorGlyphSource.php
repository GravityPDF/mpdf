<?php

namespace Mpdf\Fonts\Color;

/**
 * One colour format of a font, drawing its glyphs as PDF content.
 *
 * Each format - a bitmap table, COLR layers, SVG documents - is one of these, so the Type3 font a
 * colour font is written as never needs to know which it is drawing.
 */
interface ColorGlyphSource
{

	/**
	 * @param int            $glyph     The glyph id
	 * @param GlyphResources $resources Where the drawing registers the images it uses
	 *
	 * @return string|null PDF content drawing the glyph in font units, with the origin on the baseline,
	 *                     or null where the format has no colour glyph for it
	 */
	public function draw($glyph, GlyphResources $resources);
}
