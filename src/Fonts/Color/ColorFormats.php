<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\FileReader;
use Mpdf\Mpdf;
use Mpdf\TTFontFile;
use Psr\Log\LoggerInterface;

/**
 * The colour formats mPDF draws, and which of a font's it draws the font in.
 */
class ColorFormats
{

	/**
	 * The formats mPDF can draw, each with the ColorGlyphSource that draws it, in the order a font that
	 * carries several is drawn in
	 */
	const SOURCES = [
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
	 * The format a document draws a font in
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 * @param Mpdf  $mpdf The document
	 *
	 * @return string The format, or '' where the font is not drawn in colour
	 */
	public static function drawn(array $font, Mpdf $mpdf)
	{
		return empty($font['colorFormats']) ? '' : self::choose($font['colorFormats'], self::inColor($mpdf));
	}

	/**
	 * Whether a font draws nothing in a document: its glyphs exist only in a colour format, and the
	 * document may not draw colour. Its characters are left blank, unless a backup font has them.
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 * @param Mpdf  $mpdf The document
	 *
	 * @return bool
	 */
	public static function blank(array $font, Mpdf $mpdf)
	{
		return !empty($font['colorFormats']) && self::drawable($font['colorFormats']) && !self::inColor($mpdf);
	}

	/**
	 * The format a font is drawn in: the first of SOURCES that it carries
	 *
	 * @param string[] $fontFormats The formats the font carries
	 * @param bool     $color       Whether the document may use colour
	 *
	 * @return string The format, or '' where the font is drawn without colour
	 */
	public static function choose(array $fontFormats, $color)
	{
		$formats = $color ? array_intersect(array_keys(self::SOURCES), $fontFormats) : [];

		return $formats ? reset($formats) : '';
	}

	/**
	 * @param string          $format One of the keys of SOURCES
	 * @param TTFontFile      $font   The font, its table directory read
	 * @param FileReader      $reader The font file
	 * @param int             $unitsPerEm
	 * @param LoggerInterface $logger Told of a glyph the format has but mPDF cannot draw
	 *
	 * @return ColorGlyphSource
	 */
	public static function source($format, TTFontFile $font, FileReader $reader, $unitsPerEm, LoggerInterface $logger)
	{
		$class = self::SOURCES[$format];

		return new $class($font, $reader, $unitsPerEm, $logger);
	}
}
