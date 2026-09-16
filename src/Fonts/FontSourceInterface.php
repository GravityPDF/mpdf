<?php

namespace Mpdf\Fonts;

/**
 * The font file FontSubsetter builds a program from: the file opened, its table directory read, and
 * the two readers the metrics path has that the subsetter uses as well.
 *
 * TTFontFile is the implementation. The subsetter reads the table bytes itself, through the reader
 * open() hands back, so what it needs from the parser is where each table is and those two readers.
 */
interface FontSourceInterface
{

	/**
	 * Start reading one font file. Every other method reads the file most recently opened.
	 *
	 * @return FileReader The open file, which the subsetter reads the tables through
	 */
	public function open($file);

	/**
	 * Move to one font of a TrueType Collection, or leave a plain font's reader where it is.
	 *
	 * @param int $TTCfontID Which font of the collection, or 0 for a plain font
	 */
	public function selectFont($TTCfontID);

	/**
	 * Read the table directory from the reader's current position.
	 *
	 * @param bool $debug Whether to check every table against the checksum the directory states
	 */
	public function readTableDirectory($debug = false);

	/**
	 * @param string $tag A table name, e.g. 'cmap'
	 *
	 * @return bool Whether the table directory lists it, even at a length of zero
	 */
	public function hasTable($tag);

	/**
	 * @param string $tag A table name, e.g. 'cmap'
	 *
	 * @return int[] Where it starts and how long it is, or [0, 0] where the font has no such table
	 */
	public function getTablePosition($tag);

	/**
	 * @return int The size below which a table is read into memory whole, rather than a piece at a time
	 */
	public function getMaxStrLenRead();

	/**
	 * Read a format 4 cmap subtable.
	 *
	 * @param int   $unicode_cmap_offset Where the subtable starts, from the start of the file
	 * @param array $glyphToChar         Filled with the characters each glyph id is mapped from
	 * @param array $charToGlyph         Filled with the glyph id each character maps to
	 *
	 * @return int The highest character the subtable covers
	 */
	public function getCMAP4($unicode_cmap_offset, &$glyphToChar, &$charToGlyph);

	/**
	 * The width of every character the font maps, and the width to draw one it does not.
	 *
	 * @param int   $numberOfHMetrics hhea's count of full metric records
	 * @param int   $numGlyphs        maxp's glyph count
	 * @param array $glyphToChar      The characters each glyph id is mapped from
	 * @param float $scale            What each width is multiplied by
	 * @param int   $maxUniChar       The highest character mapped, which sizes the width table
	 *
	 * @return array [$charWidths, $defaultWidth]
	 */
	public function getHMTX($numberOfHMetrics, $numGlyphs, &$glyphToChar, $scale, $maxUniChar);

}
