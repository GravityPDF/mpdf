<?php

namespace Mpdf\Fonts\Color;

/**
 * What a colour glyph's drawing can use besides paths and colours. Each is registered on the document,
 * once however many glyphs use it, and named in the resource dictionary of the Type3 font the glyph is
 * drawn in.
 *
 * A group or soft mask is drawn with the same resource dictionary as the glyph, so its content can use
 * whatever the glyph's can.
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

	/**
	 * @param string $mode A PDF blend mode, e.g. 'SoftLight'
	 *
	 * @return string Content setting what is painted to blend with what is under it so, e.g. '/GS3 gs'
	 */
	public function blend($mode);

	/**
	 * @param array $shading 'coords' => the shading's /Coords, four for an axial shading's two points or
	 *                       six for a radial one's two circles; 'stops' => each [offset from 0 to 1,
	 *                       colour], the colour [red, green, blue] or [grey], from 0 to 1, the first
	 *                       stop at 0 and the last at 1
	 *
	 * @return string The name the content paints it by with sh, e.g. '/Sh2'
	 */
	public function shading(array $shading);

	/**
	 * @param string  $content  What the group draws
	 * @param float[] $box      [xMin, yMin, xMax, yMax] it is drawn within, in the space it is drawn in
	 * @param bool    $isolated Whether what it draws is composited only with itself, and the result
	 *                          then with what is under it, rather than each thing it draws with what
	 *                          is under it
	 *
	 * @return string The name the content draws the group by with Do, e.g. '/Fx4'
	 */
	public function group($content, array $box, $isolated = false);

	/**
	 * @param string  $content    What the mask is drawn from
	 * @param float[] $box        [xMin, yMin, xMax, yMax] it is drawn within, in the space it is drawn in
	 * @param bool    $luminosity Whether the mask is the content's brightness, rather than its alpha
	 * @param bool    $inverted   Whether what the content covers is masked out, rather than let through
	 *
	 * @return string Content masking what is painted after it so, e.g. '/SM1 gs'
	 */
	public function softMask($content, array $box, $luminosity = false, $inverted = false);
}
