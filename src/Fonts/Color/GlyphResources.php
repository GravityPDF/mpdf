<?php

namespace Mpdf\Fonts\Color;

/**
 * What a colour glyph's drawing can use besides paths and colours. The Type3 font a glyph is drawn in
 * shares the document's resource dictionary, so each of these is registered where a page would
 * register it.
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
}
