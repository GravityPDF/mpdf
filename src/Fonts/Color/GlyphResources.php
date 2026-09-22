<?php

namespace Mpdf\Fonts\Color;

/**
 * What a colour glyph's drawing can use besides paths and colours. Each is registered on the document,
 * once however many glyphs use it, and named in the resource dictionary of the Type3 font the glyph is
 * drawn in.
 */
interface GlyphResources
{

	/**
	 * @param string $data A PNG or a JPEG
	 *
	 * @return array|null [the name the glyph's content draws it with Do by, e.g. '/I3', its width in
	 *                    pixels, its height in pixels], or null where the image cannot be drawn
	 */
	public function image($data);

	/**
	 * @param float $opacity From 0, transparent, to 1
	 *
	 * @return string Content setting fills to that opacity, e.g. '/GS2 gs'
	 */
	public function alpha($opacity);
}
