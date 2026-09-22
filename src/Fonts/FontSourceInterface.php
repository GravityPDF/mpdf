<?php

namespace Mpdf\Fonts;

/**
 * The font file FontSubsetter builds a program from: the file opened, its table directory read, and
 * the cmap reader the metrics path has that the subsetter uses as well.
 *
 * TTFontFile is the implementation. The subsetter reads the table bytes itself, through the reader
 * open() hands back, so what it needs from the parser is where each table is and getCMAP4().
 * The file reader is shared: selectFont(), readTableDirectory() and getCMAP4() read through it too,
 * from wherever the subsetter left it, so a source hands back the reader it reads itself.
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

}
