<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Mpdf;

/**
 * The colour formats mPDF draws, and which of a font's it draws the font in.
 */
class ColorFormats
{

	/**
	 * The formats mPDF can draw, each with the ColorGlyphSource that draws it, in the order a font that
	 * carries several is drawn in.
	 *
	 * Whether a font is drawable is kept in its cached metrics - see drawable() - so adding a format
	 * here calls for raising MetricsGenerator::CACHE_FORMAT, or a font of that format cached before is
	 * served as one that is not.
	 */
	const SOURCES = [
		'COLRv1' => 'Mpdf\Fonts\Color\ColrV1Source',
		'COLRv0' => 'Mpdf\Fonts\Color\ColrV0Source',
		'CBDT' => 'Mpdf\Fonts\Color\CbdtSource',
		'sbix' => 'Mpdf\Fonts\Color\SbixSource',
	];

	/**
	 * Whether a font carries a format mPDF can draw. Such a font is written as Type3 fonts, whether or
	 * not a document draws it in colour.
	 *
	 * @param string[] $fontFormats The formats the font carries
	 *
	 * @return bool
	 */
	public static function drawable(array $fontFormats)
	{
		return (bool) array_intersect($fontFormats, array_keys(self::SOURCES));
	}

	/**
	 * Whether a document may draw colour fonts in colour: not where PDF/A-1b or PDF/X-1a forbid the
	 * transparency every colour format is drawn with, nor where restrictColorSpace holds the document to
	 * colours an RGB bitmap is not.
	 *
	 * Asked whenever it matters rather than once, since each of those settings can be changed on the
	 * document after the fonts it starts with are added.
	 *
	 * @param Mpdf $mpdf The document
	 *
	 * @return bool
	 */
	public static function inColor(Mpdf $mpdf)
	{
		return !$mpdf->PDFA && !$mpdf->PDFX && !$mpdf->restrictColorSpace;
	}

	/**
	 * Whether a document draws a font in colour: it carries a format mPDF draws, and the document may
	 * draw colour
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 * @param Mpdf  $mpdf The document
	 *
	 * @return bool
	 */
	public static function drawsInColor(array $font, Mpdf $mpdf)
	{
		return !empty($font['colorFormats']) && self::drawable($font['colorFormats']) && self::inColor($mpdf);
	}

	/**
	 * What draws a font's glyphs in a document, each glyph by the first that has it: each format the
	 * font carries, in the order of SOURCES, where the document may draw colour, then its outlines,
	 * where it has any. A COLR version 1 font's glyph with no paint is so drawn from its version 0
	 * layers.
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 * @param Mpdf  $mpdf The document
	 *
	 * @return string[] The ColorGlyphSource classes
	 */
	public static function sources(array $font, Mpdf $mpdf)
	{
		$sources = [];
		if (!empty($font['colorFormats']) && self::inColor($mpdf)) {
			$sources = array_values(array_intersect_key(self::SOURCES, array_flip($font['colorFormats'])));
		}
		if (!empty($font['hasOutlines'])) {
			$sources[] = 'Mpdf\Fonts\Color\OutlineSource';
		}

		return $sources;
	}

	/**
	 * Whether a font written as Type3 draws nothing in a document: nothing draws its glyphs - see
	 * sources() - so its characters are left blank, unless a backup font has them.
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 * @param Mpdf  $mpdf The document
	 *
	 * @return bool
	 */
	public static function blank(array $font, Mpdf $mpdf)
	{
		return !empty($font['colorFormats']) && self::drawable($font['colorFormats']) && !self::sources($font, $mpdf);
	}
}
