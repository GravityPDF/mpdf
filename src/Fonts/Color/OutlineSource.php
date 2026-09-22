<?php

namespace Mpdf\Fonts\Color;

/**
 * A glyph's outline, filled in the colour of the text: how a colour font draws a glyph with no colour
 * of its own, and every glyph where it is not drawn in colour.
 */
class OutlineSource implements ColorGlyphSource
{

	/**
	 * @var ColorFontFile
	 */
	private $file;

	/**
	 * @param ColorFontFile $file The font
	 */
	public function __construct(ColorFontFile $file)
	{
		$this->file = $file;
	}

	/**
	 * @inheritdoc
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		$path = $this->file->outline()->path($glyph);

		return $path === '' ? null : $path . "f\n";
	}
}
