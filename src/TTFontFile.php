<?php

namespace Mpdf;

use Mpdf\Fonts\FileReader;
use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\GlyphString;
use Mpdf\Fonts\Table\ClassDef;
use Mpdf\Fonts\Table\Coverage;
use Mpdf\Fonts\Table\LookupFlag;
use Mpdf\Fonts\Table\SequenceRule;
use Mpdf\Fonts\TableChecksum;

// NOTE*** If you change the defined constants below, be sure to delete all temporary font data files in /ttfontdata/
// to force mPDF to regenerate cached font files.
if (!defined('_OTL_OLD_SPEC_COMPAT_2')) {
	define('_OTL_OLD_SPEC_COMPAT_2', true);
}

// Define the value used in the "head" table of a created TTF file
// 0x74727565 "true" for Mac
// 0x00010000 for Windows
// Either seems to work for a font embedded in a PDF file
// when read by Adobe Reader on a Windows PC(!)
// Recalculate correct metadata/profiles when making subset fonts (not SIP/SMP)
// e.g. xMin, xMax, maxNContours
if (!function_exists('\Mpdf\unicode_hex')) {
	/**
	 * A Unicode code point as the five upper-case hex digits the OTL code writes characters at.
	 *
	 * Kept, and kept here behind the same guard, because it has been a public function of the Mpdf
	 * namespace since 5.7.1 and upstream declares it in this file. A caller outside the library gets
	 * it exactly as before: once this file has loaded, since PHP autoloads classes and not functions.
	 *
	 * @deprecated Use Mpdf\Fonts\GlyphString::of()
	 */
	function unicode_hex($unicode_dec)
	{
		return GlyphString::of($unicode_dec);
	}
}

/**
 * TTFontFile class
 *
 * This class is based on The ReportLab Open Source PDF library
 * written in Python - http://www.reportlab.com/software/opensource/
 * together with ideas from the OpenOffice source code and others.
 * This header must be retained in any redistribution or
 * modification of the file.
 *
 * @author Ian Back <ianb@bpm1.com>
 * @license LGPL
 */
class TTFontFile
{

	use Strict;

	/**
	 * The font file, as something that can be read
	 *
	 * @var FileReader
	 */
	protected $reader;

	/**
	 * Which glyphs a lookup skips, made by _getGDEFtables() from the classes it reads, which every
	 * GSUB read follows
	 *
	 * @var LookupFlag
	 */
	protected $lookupFlag;

	private $fontCache;

	private $fontDescriptor;

	var $GPOSFeatures;

	var $GPOSLookups;

	var $GPOSScriptLang;

	var $MarkAttachmentType;

	var $MarkGlyphSets;

	var $GlyphClassMarks;

	var $GlyphClassLigatures;

	var $GlyphClassBases;

	var $GlyphClassComponents;

	var $GSUBScriptLang;

	var $rtlPUAstr;

	var $fontkey;

	var $useOTL;

	var $sFamilyClass;

	var $sFamilySubClass;

	var $sipset;

	var $smpset;

	var $numTables;

	var $searchRange;

	var $entrySelector;

	var $rangeShift;

	var $tables;

	var $filename;

	var $charToGlyph;

	var $ascent;

	var $descent;

	var $lineGap;

	var $hheaascent;

	var $hheadescent;

	var $hhealineGap;

	var $advanceWidthMax;

	var $typoAscender;

	var $typoDescender;

	var $typoLineGap;

	var $usWinAscent;

	var $usWinDescent;

	var $strikeoutSize;

	var $strikeoutPosition;

	var $name;

	var $familyName;

	var $styleName;

	var $fullName;

	var $uniqueFontID;

	var $unitsPerEm;

	var $bbox;

	var $capHeight;

	var $xHeight;

	var $stemV;

	var $italicAngle;

	var $flags;

	var $underlinePosition;

	var $underlineThickness;

	var $charWidths;

	var $defaultWidth;

	var $maxStrLenRead;

	var $numTTCFonts;

	var $TTCFonts;

	var $maxUniChar;

	var $kerninfo;

	var $haskernGPOS;

	var $hassmallcapsGSUB;

	var $LuCoverage;

	public $panose;

	public $version;

	public $fontRevision;

	public $restrictedUse;

	public $glyphIDtoUni;

	public $glyphToChar;

	public $GSUBFeatures;

	public $GSUBLookups;

	public $GSLuCoverage;

	/**
	 * @param FontCache $fontCache      Where a parsed font is kept
	 * @param string    $fontDescriptor Which of the font's three sets of vertical metrics to believe:
	 *                                  'winTypo', 'mac' or 'win'
	 */
	public function __construct(FontCache $fontCache, $fontDescriptor)
	{
		$this->fontCache = $fontCache;
		$this->fontDescriptor = $fontDescriptor;

		// Maximum size of glyf table to read in as string (otherwise reads each glyph from file)
		$this->maxStrLenRead = 200000;
	}

	/**
	 * Read one font: its table directory, its metrics, its character map, and its layout tables.
	 *
	 * Everything a document needs to lay text out with the font. The font program itself is built
	 * separately, by Fonts\FontSubsetter.
	 *
	 * @param string $file      The font file to read
	 * @param string $fontkey   The name the document registered the font under
	 * @param int    $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 * @param bool   $debug     Whether to check the font's own tables as they are read
	 * @param bool   $BMPonly   Whether to stop the character map at the Basic Multilingual Plane
	 * @param int    $useOTL    Which scripts the document asked to be laid out from the font's own
	 *                          tables, as a mask over the script groups
	 *
	 * @throws \Mpdf\Exception\FontException If the file is not a TrueType font mPDF can embed
	 */
	public function getMetrics($file, $fontkey, $TTCfontID = 0, $debug = false, $BMPonly = false, $useOTL = 0)
	{
		$this->useOTL = $useOTL;
		$this->fontkey = $fontkey;

		$this->charWidths = '';
		$this->charToGlyph = [];
		$this->kerninfo = [];
		$this->haskernGPOS = false;
		$this->hassmallcapsGSUB = false;
		$this->ascent = 0;
		$this->descent = 0;
		$this->lineGap = 0;
		$this->hheaascent = 0;
		$this->hheadescent = 0;
		$this->hhealineGap = 0;
		$this->xHeight = 0;
		$this->capHeight = 0;
		$this->panose = [];
		$this->sFamilyClass = 0;
		$this->sFamilySubClass = 0;
		$this->typoAscender = 0;
		$this->typoDescender = 0;
		$this->typoLineGap = 0;
		$this->usWinAscent = 0;
		$this->usWinDescent = 0;
		$this->advanceWidthMax = 0;
		$this->strikeoutSize = 0;
		$this->strikeoutPosition = 0;

		$this->open($file);

		// Closed however the read ends. A font that turns out to be unreadable would otherwise keep its
		// file open for as long as this object lives, and on Windows an open file cannot be deleted or
		// replaced. finally rather than catch, so that an Error on PHP 7 releases it too
		try {
			$this->readHeader($TTCfontID, $debug);
			$this->extractInfo($debug, $BMPonly, $useOTL);
		} finally {
			$this->reader->close();
		}
	}

	/**
	 * Read enough of the open font file that seek_table can find any of its tables: the version, the
	 * font within a collection if that is what it is, and the table directory.
	 *
	 * @param int  $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 * @param bool $debug     Whether to check every table against the checksum the directory states
	 *
	 * @throws \Exception If the file is not a TrueType font this can read. Which exception that is
	 *                    comes from collectionWithoutFontId, unreadableCollection and
	 *                    notATrueTypeFont, so that a subclass can complain in its own terms
	 */
	protected function readHeader($TTCfontID = 0, $debug = false)
	{
		$this->tables = [];
		$this->numTTCFonts = 0;
		$this->TTCFonts = [];
		$this->version = $version = $this->reader->readUInt32();

		if ($version === 0x4F54544F) {
			throw new \Mpdf\Exception\FontException(sprintf('Fonts with postscript outlines are not supported (%s)', $this->filename));
		}

		if ($version === 0x74746366) {
			if (!$TTCfontID) {
				throw $this->collectionWithoutFontId();
			}

			$this->selectFont($TTCfontID);
		} elseif (!in_array($version, [0x00010000, 0x74727565], true)) {
			throw $this->notATrueTypeFont($version);
		}

		$this->readTableDirectory($debug);
	}

	/**
	 * @return \Exception Because the file is a TrueType Collection and nothing said which font of it
	 *                    to read
	 */
	protected function collectionWithoutFontId()
	{
		return new \Mpdf\Exception\FontException(sprintf('TTCfontID for a TrueType Collection is not defined in mPDF "fontdata" configuration (%s)', $this->filename));
	}

	/**
	 * @param int $version The TrueType Collection header version the file states
	 *
	 * @return \Exception Because no version of the collection format but 1.0 and 2.0 is read
	 */
	protected function unreadableCollection($version)
	{
		return new \Mpdf\Exception\FontException(sprintf('Error parsing TrueType Collection: version=%s (%s)', $version, $this->filename));
	}

	/**
	 * @param int $version The font version the file states
	 *
	 * @return \Exception Because it is neither of the two versions a TrueType font states
	 */
	protected function notATrueTypeFont($version)
	{
		return new \Mpdf\Exception\FontException(sprintf('Not a TrueType font: version=%s)', $version));
	}

	/**
	 * Read the table directory: where each of the font's tables is and how long it is.
	 *
	 * @param bool $debug Whether to check every table against the checksum the directory states
	 */
	function readTableDirectory($debug = false)
	{
		$this->numTables = $this->reader->readUInt16();
		$this->searchRange = $this->reader->readUInt16();
		$this->entrySelector = $this->reader->readUInt16();
		$this->rangeShift = $this->reader->readUInt16();
		$this->tables = [];

		for ($i = 0; $i < $this->numTables; $i++) {
			$record = [];
			$record['tag'] = $this->reader->readTag();
			$record['checksum'] = [$this->reader->readUInt16(), $this->reader->readUInt16()];
			$record['offset'] = $this->reader->readUInt32();
			$record['length'] = $this->reader->readUInt32();
			$this->tables[$record['tag']] = $record;
		}

		if ($debug) {
			$this->checksumTables();
		}
	}

	/**
	 * Start reading one font file.
	 *
	 * getMetrics and getCTG open their own; FontSubsetter opens one through here and reads the same
	 * handle, so that the table directory this parses and the two readers built on it that the
	 * subsetter borrows are all looking at the font it is building from.
	 *
	 * @return FileReader The open file, for a caller that reads it itself
	 */
	public function open($file)
	{
		$this->filename = $file;
		$this->reader = new FileReader($file);

		return $this->reader;
	}

	/**
	 * Move to the start of one font within a TrueType Collection, having read its header.
	 *
	 * A collection is a list of offsets to whole fonts sharing one glyf table, so everything after
	 * this reads as though the file held only the font asked for. Does nothing for a plain font,
	 * which is what a TTCfontID of 0 means.
	 */
	public function selectFont($TTCfontID)
	{
		$this->numTTCFonts = 0;
		$this->TTCFonts = [];

		if ($TTCfontID <= 0) {
			return;
		}

		$this->version = $version = $this->reader->readUInt32(); // TTC Header version now
		if (!in_array($version, [0x00010000, 0x00020000], true)) {
			throw $this->unreadableCollection($version);
		}

		$this->numTTCFonts = $this->reader->readUInt32();
		for ($i = 1; $i <= $this->numTTCFonts; $i++) {
			$this->TTCFonts[$i]['offset'] = $this->reader->readUInt32();
		}

		$this->reader->seek($this->TTCFonts[$TTCfontID]['offset']);
		$this->version = $this->reader->readUInt32(); // TTFont version again now
	}

	/**
	 * Check every table against the checksum the table directory states for it.
	 *
	 * head is the exception: its own checksum adjustment is part of the bytes being summed, so it is
	 * subtracted back out before the comparison.
	 *
	 * @throws \Mpdf\Exception\FontException On the first table that does not sum to what it claims
	 */
	function checksumTables()
	{
		// Check the checksums for all tables
		foreach ($this->tables as $t) {
			if ($t['length'] > 0 && $t['length'] < $this->maxStrLenRead) { // 1.02
				$table = $this->reader->bytesAt($t['offset'], $t['length']);
				$checksum = TableChecksum::of($table);
				if ($t['tag'] === 'head') {
					$up = unpack('n*', substr($table, 8, 4));
					$adjustment[0] = $up[1];
					$adjustment[1] = $up[2];
					$checksum = TableChecksum::subtract($checksum, $adjustment);
				}
				$xchecksum = $t['checksum'];
				if ($xchecksum != $checksum) {
					throw new \Mpdf\Exception\FontException(sprintf('TTF file "%s": invalid checksum %s table: %s (expected %s)', $this->filename, dechex($checksum[0]) . dechex($checksum[1]), $t['tag'], dechex($xchecksum[0]) . dechex($xchecksum[1])));
				}
			}
		}
	}

	/**
	 * @param string $tag A table name, e.g. 'cmap'
	 *
	 * @return array Where it is and how long it is, or [0, 0] where the font has no such table
	 */
	function get_table_pos($tag)
	{
		if (!isset($this->tables[$tag])) {
			return [0, 0];
		}
		$offset = $this->tables[$tag]['offset'];
		$length = $this->tables[$tag]['length'];

		return [$offset, $length];
	}

	/**
	 * Move to a table named in the font's table directory.
	 *
	 * @return int The absolute position of the table, or of $offset_in_table bytes into it
	 */
	function seek_table($tag, $offset_in_table = 0)
	{
		list($pos, $dummy) = $this->get_table_pos($tag);
		$this->reader->seek($pos + $offset_in_table);

		return $pos + $offset_in_table;
	}

	/**
	 * Read only the character map of a font, for a font embedded whole rather than subsetted.
	 *
	 * @param string $file      The font file to read
	 * @param int    $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 * @param bool   $debug     Whether to check the font's own tables as they are read
	 * @param bool   $useOTL    Whether the document laid the font out with the font's own tables,
	 *                          which brings in the Private Use Area codes given to glyphs the cmap
	 *                          reaches only through a substitution
	 *
	 * @return array Character to glyph id
	 */
	function getCTG($file, $TTCfontID = 0, $debug = false, $useOTL = false)
	{
		// Only called if font is not to be used as embedded subset i.e. NOT called for SIP/SMP fonts
		$this->useOTL = $useOTL; // mPDF 5.7.1
		$this->open($file);

		$this->charWidths = '';
		$this->charToGlyph = [];
		$this->tables = [];
		$this->reader->skip(4);

		$this->selectFont($TTCfontID);
		$this->readTableDirectory($debug);

		// cmap - Character to glyph index mapping table
		$cmap_offset = $this->seek_table('cmap');
		$this->reader->skip(2);
		$cmapTableCount = $this->reader->readUInt16();
		$unicode_cmap_offset = 0;
		for ($i = 0; $i < $cmapTableCount; $i++) {
			$platformID = $this->reader->readUInt16();
			$encodingID = $this->reader->readUInt16();
			$offset = $this->reader->readUInt32();
			$save_pos = $this->reader->tell();
			if ($platformID == 3 && $encodingID == 1) { // Microsoft, Unicode
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 4) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			} elseif ($platformID == 0) { // Unicode -- assume all encodings are compatible
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 4) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			$this->reader->seek($save_pos);
		}

		$glyphToChar = [];
		$charToGlyph = [];
		$this->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph);

		// Map Unmapped glyphs - from $numGlyphs
		if ($useOTL) {
			$this->seek_table("maxp");
			$this->reader->skip(4);
			$numGlyphs = $this->reader->readUInt16();
			$bctr = 0xE000;
			for ($gid = 1; $gid < $numGlyphs; $gid++) {
				if (!isset($glyphToChar[$gid])) {
					while (isset($charToGlyph[$bctr])) {
						$bctr++;
					} // Avoid overwriting a glyph already mapped in PUA
					if ($bctr > 0xF8FF) {
						throw new \Mpdf\Exception\FontException(sprintf('Font "%s" cannot map all included glyphs into Private Use Area U+E000-U+F8FF; cannot use useOTL on this font', $file));
					}
					$glyphToChar[$gid][] = $bctr;
					$charToGlyph[$bctr] = $gid;
					$bctr++;
				}
			}
		}

		$this->reader->close();

		return $charToGlyph;
	}

	/**
	 * Read the header of a TrueType Collection: how many fonts it holds and where each one starts.
	 *
	 * @param string $file The collection to read
	 *
	 * @throws \Mpdf\Exception\FontException If the file is not a collection, or is one of a version
	 *                                        this cannot read
	 */
	function getTTCFonts($file)
	{
		$this->filename = $file;

		$this->reader = new FileReader($file);

		$this->numTTCFonts = 0;
		$this->TTCFonts = [];
		$this->version = $version = $this->reader->readUInt32();
		if ($version === 0x74746366) {
			$this->version = $version = $this->reader->readUInt32(); // TTC Header version now
			if (!in_array($version, [0x00010000, 0x00020000], true)) {
				throw new \Mpdf\Exception\FontException(sprintf("Error parsing TrueType Collection: version=%s (%s)", $version, $file));
			}
		} else {
			throw new \Mpdf\Exception\FontException(sprintf("Not a TrueType Collection: version=%s (%s)", $version, $file));
		}

		$this->numTTCFonts = $this->reader->readUInt32();
		for ($i = 1; $i <= $this->numTTCFonts; $i++) {
			$this->TTCFonts[$i]['offset'] = $this->reader->readUInt32();
		}
	}

	/**
	 * Read everything past the table directory: the names, the metrics, the character map, and the
	 * layout tables.
	 *
	 * @param bool $debug   Whether to check the font's own tables as they are read
	 * @param bool $BMPonly Whether to stop the character map at the Basic Multilingual Plane
	 * @param int  $useOTL  Which scripts the document asked to be laid out from the font's own tables
	 */
	function extractInfo($debug = false, $BMPonly = false, $useOTL = 0)
	{
		// Values are all set to 0 or blank at start of getMetrics
		// name - Naming table
		$name_offset = $this->seek_table("name");
		$format = $this->reader->readUInt16();
		if ($format != 0 && $format != 1) {
			throw new \Mpdf\Exception\FontException("Error loading font: Unknown name table format $format for font $this->filename");
		}

		$numRecords = $this->reader->readUInt16();
		$string_data_offset = $name_offset + $this->reader->readUInt16();
		$names = [1 => '', 2 => '', 3 => '', 4 => '', 6 => ''];
		$K = array_keys($names);
		$nameCount = count($names);

		for ($i = 0; $i < $numRecords; $i++) {

			$platformId = $this->reader->readUInt16();
			$encodingId = $this->reader->readUInt16();
			$languageId = $this->reader->readUInt16();
			$nameId = $this->reader->readUInt16();
			$length = $this->reader->readUInt16();
			$offset = $this->reader->readUInt16();

			if (!in_array($nameId, $K)) {
				continue;
			}

			$N = '';
			if ($platformId == 3 && $encodingId == 1 && $languageId == 0x409) { // Microsoft, Unicode, US English, PS Name
				$opos = $this->reader->tell();
				$this->reader->seek($string_data_offset + $offset);
				if ($length % 2 != 0) {
					throw new \Mpdf\Exception\FontException("Error loading font: PostScript name is UTF-16BE string of odd length for font $this->filename");
				}
				$length /= 2;
				$N = '';
				while ($length > 0) {
					$char = $this->reader->readUInt16();
					$N .= (chr($char));
					$length -= 1;
				}
				$this->reader->seek($opos);
			} elseif ($platformId == 1 && $encodingId == 0 && $languageId == 0) { // Macintosh, Roman, English, PS Name
				$opos = $this->reader->tell();
				$N = $this->reader->bytesAt($string_data_offset + $offset, $length);
				$this->reader->seek($opos);
			}
			if ($N && $names[$nameId] == '') {
				$names[$nameId] = $N;
				$nameCount -= 1;
				if ($nameCount == 0) {
					break;
				}
			}
		}

		if ($names[6]) {
			$psName = $names[6];
		} elseif ($names[4]) {
			$psName = preg_replace('/ /', '-', $names[4]);
		} elseif ($names[1]) {
			$psName = preg_replace('/ /', '-', $names[1]);
		} else {
			$psName = '';
		}

		if (!$psName) {
			throw new \Mpdf\Exception\FontException("Error loading font: Could not find PostScript font name '$this->filename'");
		}

		// CHECK IF psName valid (PadaukBook contains illegal characters in Name ID 6 i.e. Postscript Name)
		$psNameInvalid = false;
		$nameLength = strlen($psName);
		for ($i = 0; $i < $nameLength; $i++) {
			$c = $psName[$i];
			$oc = ord($c);
			if ($oc > 126 || strpos(' [](){}<>/%', $c) !== false) {
				//throw new \Mpdf\Exception\FontException("psName=".$psName." contains invalid character ".$c." ie U+".ord(c));
				$psNameInvalid = true;
				break;
			}
		}

		if ($psNameInvalid && $names[4]) {
			$psName = preg_replace('/ /', '-', $names[4]);
		}

		$this->name = $psName;
		if ($names[1]) {
			$this->familyName = $names[1];
		} else {
			$this->familyName = $psName;
		}
		if ($names[2]) {
			$this->styleName = $names[2];
		} else {
			$this->styleName = 'Regular';
		}
		if ($names[4]) {
			$this->fullName = $names[4];
		} else {
			$this->fullName = $psName;
		}
		if ($names[3]) {
			$this->uniqueFontID = $names[3];
		} else {
			$this->uniqueFontID = $psName;
		}

		if (!$psNameInvalid && $names[6]) {
			$this->fullName = $names[6];
		}

		// head - Font header table
		$this->seek_table('head');
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			$ver_min = $this->reader->readUInt16();
			if ($ver_maj != 1) {
				throw new \Mpdf\Exception\FontException('Error loading font: Unknown head table version ' . $ver_maj . '.' . $ver_min);
			}
			$this->fontRevision = $this->reader->readUInt16() . $this->reader->readUInt16();

			$this->reader->skip(4); // checksumAdjustment
			$magic = $this->reader->readUInt32();
			if ($magic !== 0x5F0F3CF5) {
				throw new \Mpdf\Exception\FontException('Error loading font: Invalid head table magic ' . $magic);
			}
			$this->reader->skip(2); // flags
		} else {
			$this->reader->skip(18);
		}
		$this->unitsPerEm = $unitsPerEm = $this->reader->readUInt16();
		$scale = 1000 / $unitsPerEm;
		$this->reader->skip(16); // created and modified, each 8 bytes
		$xMin = $this->reader->readInt16();
		$yMin = $this->reader->readInt16();
		$xMax = $this->reader->readInt16();
		$yMax = $this->reader->readInt16();
		$this->bbox = [($xMin * $scale), ($yMin * $scale), ($xMax * $scale), ($yMax * $scale)];

		$this->reader->skip(3 * 2); // macStyle, lowestRecPPEM, fontDirectionHint
		$indexToLocFormat = $this->reader->readInt16();
		$glyphDataFormat = $this->reader->readInt16();
		if ($glyphDataFormat != 0) {
			throw new \Mpdf\Exception\FontException(sprintf('Error loading font: Unknown glyph data format %s', $glyphDataFormat));
		}

		// hhea metrics table
		if (isset($this->tables["hhea"])) {
			$this->seek_table("hhea");
			$this->reader->skip(4); // majorVersion, minorVersion each ushort
			$hheaAscender = $this->reader->readInt16();
			$hheaDescender = $this->reader->readInt16();
			$hheaLineGap = $this->reader->readInt16();
			$hheaAdvanceWidthMax = $this->reader->readUInt16();
			$this->hheaascent = ($hheaAscender * $scale);
			$this->hheadescent = ($hheaDescender * $scale);
			$this->hhealineGap = ($hheaLineGap * $scale);
			$this->advanceWidthMax = ($hheaAdvanceWidthMax * $scale);
		}

		// OS/2 - OS/2 and Windows metrics table
		$use_typo_metrics = false;
		if (isset($this->tables["OS/2"])) {
			$this->seek_table("OS/2");
			$version = $this->reader->readUInt16();
			$this->reader->skip(2); // xAvgCharWidth
			$usWeightClass = $this->reader->readUInt16();
			$this->reader->skip(2); // usWidthClass
			$fsType = $this->reader->readUInt16();
			if ($fsType == 0x0002 || ($fsType & 0x0300) != 0) {
				$this->restrictedUse = true;
			}

			$this->reader->skip(16); // ySubscript and ySuperscript, 2 x 4 x short
			$yStrikeoutSize = $this->reader->readInt16();
			$yStrikeoutPosition = $this->reader->readInt16();
			$this->strikeoutSize = ($yStrikeoutSize * $scale);
			$this->strikeoutPosition = ($yStrikeoutPosition * $scale);

			$sF = $this->reader->readInt16();
			$this->sFamilyClass = ($sF >> 8);
			$this->sFamilySubClass = ($sF & 0xFF);
			// PANOSE, 10 bytes, per the OS/2 table
			$panose = $this->reader->read(10);
			$this->panose = [];
			$panoseLenght = strlen($panose);
			for ($p = 0; $p < $panoseLenght; $p++) {
				$this->panose[] = ord($panose[$p]);
			}

			$this->reader->skip(20); // ulUnicodeRange1-4, achVendID
			$fsSelection = $this->reader->readUInt16();
			$use_typo_metrics = (($fsSelection & 0x80) === 0x80); // bit#7 = USE_TYPO_METRICS
			$this->reader->skip(4); // first/lastCharIndex

			$sTypoAscender = $this->reader->readInt16();
			$sTypoDescender = $this->reader->readInt16();
			$sTypoLineGap = $this->reader->readInt16();

			if ($sTypoAscender) {
				$this->typoAscender = ($sTypoAscender * $scale);
			}
			if ($sTypoDescender) {
				$this->typoDescender = ($sTypoDescender * $scale);
			}
			if ($sTypoLineGap) {
				$this->typoLineGap = ($sTypoLineGap * $scale);
			}

			$usWinAscent = $this->reader->readUInt16();
			$usWinDescent = $this->reader->readUInt16();
			if ($usWinAscent) {
				$this->usWinAscent = ($usWinAscent * $scale);
			}
			if ($usWinDescent) {
				$this->usWinDescent = ($usWinDescent * $scale);
			}

			if ($version > 1) {
				$this->reader->skip(8); // ulCodePageRange1/2
				$sxHeight = $this->reader->readInt16();
				$this->xHeight = ($sxHeight * $scale);
				$sCapHeight = $this->reader->readInt16();
				$this->capHeight = ($sCapHeight * $scale);
			}
		} else {
			$usWeightClass = 400;
		}
		$this->stemV = 50 + (int) (($usWeightClass / 65.0) ** 2);

		// FONT DESCRIPTOR METRICS
		if ($this->fontDescriptor === 'winTypo') {
			$this->ascent = $this->typoAscender;
			$this->descent = $this->typoDescender;
			$this->lineGap = $this->typoLineGap;
		} elseif ($this->fontDescriptor === 'mac') {
			$this->ascent = $this->hheaascent;
			$this->descent = $this->hheadescent;
			$this->lineGap = $this->hhealineGap;
		} else { // $this->fontDescriptor === 'win'
			$this->ascent = $this->usWinAscent;
			$this->descent = -$this->usWinDescent;
			$this->lineGap = 0;

			// Special case - if either the winAscent or winDescent are greater than the
			// font bounding box yMin yMax, then reduce them accordingly.
			// This works with Myanmar Text (Windows 8 version) to give a
			// line-height normal that is equivalent to that produced in browsers.
			// Also Khmer OS = compatible with MSWord, Wordpad and browser.
			if ($this->ascent > $this->bbox[3]) {
				$this->ascent = $this->bbox[3];
			}

			if ($this->descent < $this->bbox[1]) {
				$this->descent = $this->bbox[1];
			}

			// Override case - if the USE_TYPO_METRICS bit is set on OS/2 fsSelection
			// this is telling the font to use the sTypo values and not the usWinAscent values.
			// This works as a fix with Cambria Math to give a normal line-height;
			// at present, this is the only font I have found with this bit set;
			// although note that MS WordPad and windows FF browser uses the big line-height from winAscent
			// but Word 2007 get it right
			if ($use_typo_metrics && $this->typoAscender) {
				$this->ascent = $this->typoAscender;
				$this->descent = $this->typoDescender;
				$this->lineGap = $this->typoLineGap;
			}
		}

		// post - PostScript table
		$this->seek_table('post');
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			if ($ver_maj < 1 || $ver_maj > 4) {
				throw new \Mpdf\Exception\FontException(sprintf('Error loading font: Unknown post table version %s', $ver_maj));
			}
			$this->reader->skip(2); // minor version
		} else {
			$this->reader->skip(4);
		}

		$this->italicAngle = $this->reader->readInt16() + $this->reader->readUInt16() / 65536.0;
		$this->underlinePosition = $this->reader->readInt16() * $scale;
		$this->underlineThickness = $this->reader->readInt16() * $scale;
		$isFixedPitch = $this->reader->readUInt32();

		$this->flags = 4;

		if ($this->italicAngle != 0) {
			$this->flags |= 64;
		}
		if ($usWeightClass >= 600) {
			$this->flags |= 262144;
		}
		if ($isFixedPitch) {
			$this->flags |= 1;
		}

		// hhea - Horizontal header table
		$this->seek_table('hhea');
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			if ($ver_maj != 1) {
				throw new \Mpdf\Exception\FontException(sprintf('Error loading font: Unknown hhea table version %s', $ver_maj));
			}
			$this->reader->skip(30); // minorVersion through the four reserved shorts - metricDataFormat sits 32 bytes in
		} else {
			$this->reader->skip(32);
		}

		$metricDataFormat = $this->reader->readInt16();

		if ($metricDataFormat != 0) {
			throw new \Mpdf\Exception\FontException(sprintf('Error loading font: Unknown horizontal metric data format "%s"', $metricDataFormat));
		}

		$numberOfHMetrics = $this->reader->readUInt16();

		if ($numberOfHMetrics == 0) {
			throw new \Mpdf\Exception\FontException('Error loading font: Number of horizontal metrics is 0');
		}

		// maxp - Maximum profile table
		$this->seek_table('maxp');
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			if ($ver_maj != 1) {
				throw new \Mpdf\Exception\FontException(sprintf('Error loading font: Unknown maxp table version %s', $ver_maj));
			}
			$this->reader->skip(2); // minor version
		} else {
			$this->reader->skip(4);
		}
		$numGlyphs = $this->reader->readUInt16();

		// cmap - Character to glyph index mapping table
		$cmap_offset = $this->seek_table('cmap');
		$this->reader->skip(2); // version
		$cmapTableCount = $this->reader->readUInt16();
		$unicode_cmap_offset = 0;
		for ($i = 0; $i < $cmapTableCount; $i++) {
			$platformID = $this->reader->readUInt16();
			$encodingID = $this->reader->readUInt16();
			$offset = $this->reader->readUInt32();
			$save_pos = $this->reader->tell();
			if (($platformID == 3 && $encodingID == 1) || $platformID == 0) { // Microsoft, Unicode
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 4) {
					if (!$unicode_cmap_offset) {
						$unicode_cmap_offset = $cmap_offset + $offset;
					}
					if ($BMPonly) {
						break;
					}
				}
			} elseif ((($platformID == 3 && $encodingID == 10) || $platformID == 0) && !$BMPonly) { // Microsoft, Unicode Format 12 table HKCS
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 12) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			$this->reader->seek($save_pos);
		}

		if (!$unicode_cmap_offset) {
			throw new \Mpdf\Exception\FontException(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $this->filename));
		}

		$sipset = false;
		$smpset = false;

		$this->rtlPUAstr = '';
		$this->GSUBScriptLang = [];
		$this->GSUBFeatures = [];
		$this->GSUBLookups = [];
		$this->GPOSScriptLang = [];
		$this->GPOSFeatures = [];
		$this->GPOSLookups = [];
		$this->glyphIDtoUni = '';

		// Format 12 CMAP does characters above Unicode BMP i.e. some HKCS characters U+20000 and above
		if ($format == 12 && !$BMPonly) {
			$maxUniChar = 0;
			$this->reader->seek($unicode_cmap_offset + 4);
			$length = $this->reader->readUInt32();
			$limit = $unicode_cmap_offset + $length;
			$this->reader->skip(4); // language

			$nGroups = $this->reader->readUInt32();

			$glyphToChar = [];
			$charToGlyph = [];
			for ($i = 0; $i < $nGroups; $i++) {
				$startCharCode = $this->reader->readUInt32();
				$endCharCode = $this->reader->readUInt32();
				$startGlyphCode = $this->reader->readUInt32();
				// ZZZ98
				if ($endCharCode > 0x20000 && $endCharCode < 0x2FFFF) {
					$sipset = true;
				} elseif ($endCharCode > 0x10000 && $endCharCode < 0x1FFFF) {
					$smpset = true;
				}
				$offset = 0;
				for ($unichar = $startCharCode; $unichar <= $endCharCode; $unichar++) {
					$glyph = $startGlyphCode + $offset;
					$offset++;
					// ZZZ98
					if ($unichar < 0x30000) {
						$charToGlyph[$unichar] = $glyph;
						$maxUniChar = max($unichar, $maxUniChar);
						$glyphToChar[$glyph][] = $unichar;
					}
				}
			}
		} else {
			$glyphToChar = [];
			$charToGlyph = [];
			$maxUniChar = $this->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph);
		}
		$this->sipset = $sipset;
		$this->smpset = $smpset;

		// Map Unmapped glyphs (or glyphs mapped to upper PUA U+F00000 onwards i.e. > U+2FFFF) - from $numGlyphs
		if ($this->useOTL) {

			$bctr = 0xE000;

			for ($gid = 1; $gid < $numGlyphs; $gid++) {

				if (!isset($glyphToChar[$gid])) {

					while (isset($charToGlyph[$bctr])) {
						$bctr++;
					}

					// Avoid overwriting a glyph already mapped in PUA
					// ZZZ98
					if (($bctr > 0xF8FF) && ($bctr < 0x2CEB0)) {
						if (!$BMPonly) {
							$bctr = 0x2CEB0; // Use unassigned area 0x2CEB0 to 0x2F7FF (space for 10,000 characters)
							$this->sipset = $sipset = true; // forces subsetting; also ensure charwidths are saved
							while (isset($charToGlyph[$bctr])) {
								$bctr++;
							}
						} else {
							throw new \Mpdf\Exception\FontException(sprintf('The font "%s" does not have enough space to map all (unmapped) included glyphs into Private Use Area U+E000-U+F8FF', $names[1]));
						}
					}

					$glyphToChar[$gid][] = $bctr;
					$charToGlyph[$bctr] = $gid;
					$maxUniChar = max($bctr, $maxUniChar);
					$bctr++;
				}
			}
		}

		$this->glyphToChar = $glyphToChar;
		$this->maxUniChar = $maxUniChar;

		$this->GSUBScriptLang = [];
		$this->rtlPUAstr = '';
		if ($useOTL) {
			$this->_getGDEFtables();
			list($this->GSUBScriptLang, $this->GSUBFeatures, $this->GSUBLookups, $this->rtlPUAstr) = $this->_getGSUBtables();
			list($this->GPOSScriptLang, $this->GPOSFeatures, $this->GPOSLookups) = $this->_getGPOStables();
			$this->glyphIDtoUni = str_pad('', 256 * 256 * 3, "\x00");
			foreach ($glyphToChar as $gid => $arr) {
				if (isset($glyphToChar[$gid][0])) {
					$char = $glyphToChar[$gid][0];

					if ($char != 0 && $char != 65535) {
						$this->glyphIDtoUni[$gid * 3] = chr($char >> 16);
						$this->glyphIDtoUni[$gid * 3 + 1] = chr(($char >> 8) & 0xFF);
						$this->glyphIDtoUni[$gid * 3 + 2] = chr($char & 0xFF);
					}
				}
			}
		}

		// if xHeight and/or CapHeight are not available from OS/2 (e.g. eraly versions)
		// Calculate from yMax of 'x' or 'H' Glyphs...
		if ($this->xHeight == 0) {
			if (isset($charToGlyph[0x78])) {
				$gidx = $charToGlyph[0x78]; // U+0078 (LATIN SMALL LETTER X)
				$start = $this->seek_table('loca');
				if ($indexToLocFormat == 0) {
					$this->reader->skip($gidx * 2);
					$locax = $this->reader->readUInt16() * 2;
				} elseif ($indexToLocFormat == 1) {
					$this->reader->skip($gidx * 4);
					$locax = $this->reader->readUInt32();
				}
				$start = $this->seek_table('glyf');
				$this->reader->skip($locax);
				$this->reader->skip(8);
				$yMaxx = $this->reader->readInt16();
				$this->xHeight = $yMaxx * $scale;
			}
		}

		if ($this->capHeight == 0) {
			if (isset($charToGlyph[0x48])) {
				$gidH = $charToGlyph[0x48]; // U+0048 (LATIN CAPITAL LETTER H)
				$start = $this->seek_table('loca');
				if ($indexToLocFormat == 0) {
					$this->reader->skip($gidH * 2);
					$locaH = $this->reader->readUInt16() * 2;
				} elseif ($indexToLocFormat == 1) {
					$this->reader->skip($gidH * 4);
					$locaH = $this->reader->readUInt32();
				}
				$start = $this->seek_table('glyf');
				$this->reader->skip($locaH);
				$this->reader->skip(8);
				$yMaxH = $this->reader->readInt16();
				$this->capHeight = $yMaxH * $scale;
			} else {
				$this->capHeight = $this->ascent;
			}
			// final default is to set it = to Ascent
		}

		// hmtx - Horizontal metrics table
		list($this->charWidths, $this->defaultWidth) = $this->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale, $maxUniChar);

		// kern - Kerning pair table
		// Recognises old form of Kerning table - as required by Windows - Format 0 only
		$kern_offset = $this->seek_table("kern");
		$version = $this->reader->readUInt16();
		$nTables = $this->reader->readUInt16();

		// subtable header
		$sversion = $this->reader->readUInt16();
		$slength = $this->reader->readUInt16();
		$scoverage = $this->reader->readUInt16();
		$format = $scoverage >> 8;
		if ($kern_offset && $version == 0 && $format == 0) {
			// Format 0
			$nPairs = $this->reader->readUInt16();
			$this->reader->skip(6);
			for ($i = 0; $i < $nPairs; $i++) {
				$left = $this->reader->readUInt16();
				$right = $this->reader->readUInt16();
				$val = $this->reader->readInt16();
				if (isset($glyphToChar[$left]) && count($glyphToChar[$left]) == 1 && isset($glyphToChar[$right]) && count($glyphToChar[$right]) == 1) {
					if ($left != 32 && $right != 32) {
						$this->kerninfo[$glyphToChar[$left][0]][$glyphToChar[$right][0]] = intval($val * $scale);
					}
				}
			}
		}
	}

	/**
	 * Read GDEF: which glyphs are marks, bases, ligatures and components, which attachment class each
	 * mark belongs to, and the mark glyph sets a lookup can filter to.
	 *
	 * A lookup's flags are read against all of these, so they are parsed before GSUB or GPOS is.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gdef
	 */
	function _getGDEFtables()
	{
		// https://learn.microsoft.com/en-us/typography/opentype/spec/gdef
		if (isset($this->tables["GDEF"])) {
			$gdef_offset = $this->seek_table("GDEF");

			// ULONG Version of the GDEF table-currently 0x00010000
			$ver_maj = $this->reader->readUInt16();
			$ver_min = $this->reader->readUInt16();
			$GlyphClassDef_offset = $this->reader->readUInt16();
			$AttachList_offset = $this->reader->readUInt16();
			$LigCaretList_offset = $this->reader->readUInt16();
			$MarkAttachClassDef_offset = $this->reader->readUInt16();

			// GDEF 1.2 added the MarkGlyphSetsDef offset; 1.3 keeps it and appends an ItemVarStore after it
			if ($ver_min >= 2) {
				$MarkGlyphSetsDef_offset = $this->reader->readUInt16();
			}

			// GlyphClassDef
			if ($GlyphClassDef_offset) {

				$this->reader->seek($gdef_offset + $GlyphClassDef_offset);
				// 1 Base glyph (single character, spacing glyph)
				// 2 Ligature glyph (multiple character, spacing glyph)
				// 3 Mark glyph (non-spacing combining glyph)
				// 4 Component glyph (part of single character, spacing glyph)
				$GlyphByClass = $this->_getClassDefinitionTable();
			} else {
				$GlyphByClass = [];
			}

			if (isset($GlyphByClass[1]) && count($GlyphByClass[1]) > 0) {
				$this->GlyphClassBases = ' ' . implode('| ', $GlyphByClass[1]);
			} else {
				$this->GlyphClassBases = '';
			}
			if (isset($GlyphByClass[2]) && count($GlyphByClass[2]) > 0) {
				$this->GlyphClassLigatures = ' ' . implode('| ', $GlyphByClass[2]);
			} else {
				$this->GlyphClassLigatures = '';
			}
			if (isset($GlyphByClass[3]) && count($GlyphByClass[3]) > 0) {
				$this->GlyphClassMarks = ' ' . implode('| ', $GlyphByClass[3]);
			} else {
				$this->GlyphClassMarks = '';
			}
			if (isset($GlyphByClass[4]) && count($GlyphByClass[4]) > 0) {
				$this->GlyphClassComponents = ' ' . implode('| ', $GlyphByClass[4]);
			} else {
				$this->GlyphClassComponents = '';
			}

			if (isset($GlyphByClass[3]) && count($GlyphByClass[3]) > 0) {
				$Marks = $GlyphByClass[3];
			} else { // to use for MarkAttachmentType
				$Marks = [];
			}

			/* Required for GPOS
			  // Attachment List
			  if ($AttachList_offset) {
			  $this->reader->seek($gdef_offset+$AttachList_offset );
			  }
			  The Attachment Point List table (AttachmentList) identifies all the attachment points defined in the GPOS table and their associated glyphs so a client can quickly access coordinates for each glyph's attachment points. As a result, the client can cache coordinates for attachment points along with glyph bitmaps and avoid recalculating the attachment points each time it displays a glyph. Without this table, processing speed would be slower because the client would have to decode the GPOS lookups that define attachment points and compile the points in a list.

			  The Attachment List table (AttachList) may be used to cache attachment point coordinates along with glyph bitmaps.

			  The table consists of an offset to a Coverage table (Coverage) listing all glyphs that define attachment points in the GPOS table, a count of the glyphs with attachment points (GlyphCount), and an array of offsets to AttachPoint tables (AttachPoint). The array lists the AttachPoint tables, one for each glyph in the Coverage table, in the same order as the Coverage Index.
			  AttachList table
			  Type 	Name 	Description
			  Offset 	Coverage 	Offset to Coverage table - from beginning of AttachList table
			  uint16 	GlyphCount 	Number of glyphs with attachment points
			  Offset 	AttachPoint[GlyphCount] 	Array of offsets to AttachPoint tables-from beginning of AttachList table-in Coverage Index order

			  An AttachPoint table consists of a count of the attachment points on a single glyph (PointCount) and an array of contour indices of those points (PointIndex), listed in increasing numerical order.

			  AttachPoint table
			  Type 	Name 	Description
			  uint16 	PointCount 	Number of attachment points on this glyph
			  uint16 	PointIndex[PointCount] 	Array of contour point indices -in increasing numerical order

			  See Example 3 - https://learn.microsoft.com/en-us/typography/opentype/spec/gdef
			 */

			// Ligature Caret List
			// The Ligature Caret List table (LigCaretList) defines caret positions for all the ligatures in a font.
			// Not required for mDPF
			// MarkAttachmentType
			if ($MarkAttachClassDef_offset) {
				$this->reader->seek($gdef_offset + $MarkAttachClassDef_offset);
				$MarkAttachmentTypes = $this->_getClassDefinitionTable();
				foreach ($MarkAttachmentTypes as $class => $glyphs) {
					if (is_array($Marks) && count($Marks)) {
						$mat = array_diff($Marks, $MarkAttachmentTypes[$class]);
						sort($mat, SORT_STRING);
					} else {
						$mat = [];
					}

					$this->MarkAttachmentType[$class] = ' ' . implode('| ', $mat);
				}
			} else {
				$this->MarkAttachmentType = [];
			}

			// MarkGlyphSets in Version 0x00010002 of GDEF and later
			if ($ver_min >= 2 && $MarkGlyphSetsDef_offset) {
				$this->reader->seek($gdef_offset + $MarkGlyphSetsDef_offset);
				$MarkSetTableFormat = $this->reader->readUInt16();
				$MarkSetCount = $this->reader->readUInt16();
				$MarkSetOffset = [];
				for ($i = 0; $i < $MarkSetCount; $i++) {
					$MarkSetOffset[] = $this->reader->readUInt32();
				}
				for ($i = 0; $i < $MarkSetCount; $i++) {
					// Coverage offsets are relative to the MarkGlyphSetsDef table, not the file
					$this->reader->seek($gdef_offset + $MarkGlyphSetsDef_offset + $MarkSetOffset[$i]);
					$glyphs = $this->_getCoverage();
					$this->MarkGlyphSets[$i] = ' ' . implode('| ', $glyphs);
				}
			} else {
				$this->MarkGlyphSets = [];
			}
		} else {
			throw new \Mpdf\Exception\FontException(sprintf('Unable to set font "%s" to use OTL as it does not include OTL tables (or at least not a GDEF table).', $this->filename));
		}

		// The shaper reads GSUB and GPOS from here rather than from the font, so each is cached whole,
		// one file per table. They used to share one file, GSUB first, which is the only reason a GPOS
		// offset ever had to have the length of GSUB added to it.
		foreach (['GSUB', 'GPOS'] as $tag) {
			if (!isset($this->tables[$tag])) {
				continue;
			}

			$this->seek_table($tag);
			$this->fontCache->write($this->fontkey . '.' . $tag . '.dat', $this->reader->read($this->tables[$tag]['length']));
		}

		$this->fontCache->jsonWrite($this->fontkey . '.GDEFdata.json', $this->gdefClasses());
		$this->lookupFlag = new LookupFlag($this->fontkey, $this->gdefClasses());
	}

	/**
	 * The GDEF classes as the shaper reads them back from the cache, and as LookupFlag takes them
	 *
	 * @return array
	 */
	protected function gdefClasses()
	{
		return [
			'GlyphClassBases' => $this->GlyphClassBases,
			'GlyphClassMarks' => $this->GlyphClassMarks,
			'GlyphClassLigatures' => $this->GlyphClassLigatures,
			'GlyphClassComponents' => $this->GlyphClassComponents,
			'MarkGlyphSets' => $this->MarkGlyphSets,
			'MarkAttachmentType' => $this->MarkAttachmentType,
		];
	}

	/**
	 * A Class Definition table as a list of hex strings per class, for GDEF's glyph and mark
	 * attachment classes and for GPOS pair positioning.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2#class-definition-table
	 *
	 * @param int $offset Seek here first. GDEF's two class definitions are read where the caller
	 *                    already is; a GPOS PairPos subtable names its two by offset.
	 */
	function _getClassDefinitionTable($offset = 0)
	{
		if ($offset > 0) {
			$this->reader->seek($offset);
		}

		$GlyphByClass = [];

		foreach (ClassDef::glyphsByClass($this->reader) as $class => $glyphIDs) {
			$glyphs = [];
			foreach ($glyphIDs as $glyphID) {
				// Several fonts (dejavu..., FreeSerif) carry a MarkAttachClassDef Format 1 with startGlyphID
				// 0 and glyphCount 1, which does not seem to mean anything useful, and FreeSerif has no
				// glyphToChar[0] to go with it
				if (isset($this->glyphToChar[$glyphID][0])) {
					$glyphs[] = GlyphString::of($this->glyphToChar[$glyphID][0]);
				}
			}

			// A class none of whose glyphs a character reaches is left out, not kept empty: GDEF would
			// otherwise make a mark attachment class of it
			if ($glyphs) {
				sort($glyphs, SORT_STRING); // easier to read in development; order is not significant
				$GlyphByClass[$class] = $glyphs;
			}
		}

		return $GlyphByClass;
	}

	/**
	 * Add the Private Use Area glyphs a replacement names to the list magic_reverse_dir reads.
	 *
	 * A replacement is one glyph code where the substitution replaces one glyph with one, and a
	 * space-separated list where it replaces one with several - the dotless form of a letter and the
	 * dots to draw under it, say.
	 *
	 * @param string[] $rtlpua  the list being built
	 * @param string   $replace one glyph code, or several separated by spaces
	 */
	private function addPuaGlyphs(array &$rtlpua, $replace)
	{
		foreach (explode(' ', $replace) as $glyph) {
			// Unanchored: a flattened contextual rule carries its backreferences in the same token as
			// the glyph code, so '0FB93\2' names 0FB93
			if (preg_match('/(0[EF][A-F0-9]{3})/', $glyph, $matched)) {
				$rtlpua[] = $matched[1];
			}
		}
	}

	/**
	 * GSUB - Glyph Substitution
	 *
	 * Returns [$GSUBScriptLang, $gsub, $GSLookup, $rtlPUAstr]: which languages each script offers,
	 * which lookups each feature of each of them runs, the lookup list itself, and the Private Use
	 * Area ranges the RTL glyphs were mapped into.
	 *
	 * The reading is shared with OtlDump, which reports the same table in HTML and used to carry its
	 * own copy of 856 lines of it. What it overrides now is the reporting hooks. @see _getGPOStables
	 */
	function _getGSUBtables()
	{
		if (!isset($this->tables['GSUB'])) {
			$this->reportTableMissing('GSUB');

			return [[], [], [], ''];
		}

		$this->reportTableRead('GSUB');

		$gsubOffset = $this->seek_table('GSUB');
		$this->reader->skip(4); // majorVersion, minorVersion
		$scriptListOffset = $gsubOffset + $this->reader->readUInt16();
		$featureListOffset = $gsubOffset + $this->reader->readUInt16();
		$lookupListOffset = $gsubOffset + $this->reader->readUInt16();

		list($GSUBScriptLang, $gsub, $tags) = $this->readScriptsAndFeatures($scriptListOffset, $featureListOffset);
		$this->hassmallcapsGSUB = isset($tags['smcp']);

		$this->reportScriptList('GSUB', $gsub);

		if (!$this->wantsLookups()) {
			return [$GSUBScriptLang, $gsub, [], ''];
		}

		// Lookup Type 7 is GSUB's Extension Substitution, the indirection a subtable past 64KB is
		// reached through
		$GSLookup = $this->readLookupList($lookupListOffset, $gsubOffset, 7);
		$Lookup = $this->readGSUBsubstitutions($GSLookup, $gsubOffset);

		return [$GSUBScriptLang, $gsub, $GSLookup, $this->useGSUBlookups($Lookup, $gsub, $GSLookup, $gsubOffset)];
	}

	/**
	 * Which glyphs each GSUB subtable could match on, cached for the shaper.
	 *
	 * The gate the shaper opens a subtable with: it offers the glyph it is standing on and asks
	 * whether a match could start there. Only useGSUBlookups() calls this, so a dump that reports
	 * every rule of a lookup rather than gating on the first glyph never reaches it.
	 */
	private function cacheGSUBcoverage(array $GSLookup, $gsubOffset)
	{
		$this->GSLuCoverage = [];
		foreach ($GSLookup as $i => $lookup) {
			for ($c = 0; $c < $lookup['SubtableCount']; $c++) {
				$subtable_offset = $gsubOffset + $lookup['Subtables'][$c];
				$this->reader->seek($subtable_offset);
				$PosFormat = $this->reader->readUInt16();

				if ($lookup['Type'] == 5 && $PosFormat == 3) {
					$this->reader->skip(4); // glyphCount + seqLookupCount
				} elseif ($lookup['Type'] == 6 && $PosFormat == 3) {
					$BacktrackGlyphCount = $this->reader->readUInt16();
					$this->reader->skip(2 * $BacktrackGlyphCount + 2); // backtrackCoverageOffsets + inputGlyphCount
				}

				// Reading position 0's Coverage is the whole of what the gate needs. The shaper offers a
				// subtable the glyph it is standing on and asks whether a match could start there, and every
				// format puts the Coverage of the first input position right here: straight after the format
				// for types 1 to 4 and 8 and for formats 1 and 2 of types 5 and 6, and after the counts and
				// backtrack offsets stepped over above for format 3. Otl::checkContextMatchMultiple starts
				// its input loop at 1 for the same reason - position 0 is what got it called.
				$this->reader->seek($subtable_offset + $this->reader->readUInt16());
				$this->GSLuCoverage[$i][$c] = $this->_getCoverage(false, 2);
			}
		}

		$this->fontCache->jsonWrite($this->fontkey . '.GSUBdata.json', $this->GSLuCoverage);
	}

	/**
	 * Every substitution rule in the GSUB LookupList, read in two passes.
	 *
	 * Takes the lookup list the table read already produced rather than reading it again. The two
	 * want their subtable offsets measured from different places - the shaper reads a cached copy of
	 * GSUB alone and needs them relative to it, where these passes are reading the file itself - and
	 * that difference is arithmetic, which is all the second walk of the list ever came to.
	 *
	 * @param array $GSLookup   The lookup list, subtable offsets relative to the start of GSUB
	 * @param int   $gsubOffset Where GSUB starts, from the start of the file
	 */
	private function readGSUBsubstitutions(array $GSLookup, $gsubOffset)
	{
		$Lookup = [];
		foreach ($this->absoluteSubtables($GSLookup, $gsubOffset) as $i => $lookup) {
			$Lookup[$i] = [
				'Type' => $lookup['Type'],
				'Flag' => $lookup['Flag'],
				'SubtableCount' => $lookup['SubtableCount'],
				'MarkFilteringSet' => $lookup['MarkFilteringSet'],
			];
			foreach ($lookup['Subtables'] as $c => $offset) {
				$Lookup[$i]['Subtable'][$c]['Offset'] = $offset;
			}
		}

		$this->readGSUBsubtables($Lookup);
		$this->readGSUBrules($Lookup);

		return $Lookup;
	}

	/**
	 * Pass one: the shape of every subtable in the GSUB LookupList - its format, and the offsets of
	 * whatever tables that format hangs off itself.
	 *
	 * Shared with OtlDump in full: this is offset arithmetic against the spec and there is nothing in
	 * it a report would want to do differently.
	 */
	private function readGSUBsubtables(array &$Lookup)
	{
		$LookupCount = count($Lookup);

		for ($i = 0; $i < $LookupCount; $i++) {
			for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
				$this->reader->seek($Lookup[$i]['Subtable'][$c]['Offset']);
				$SubstFormat = $this->reader->readUInt16();
				$Lookup[$i]['Subtable'][$c]['Format'] = $SubstFormat;

				/*
				  Lookup['Type'] Enumeration table for glyph substitution
				  Value	Type	Description
				  1	Single	Replace one glyph with one glyph
				  2	Multiple	Replace one glyph with more than one glyph
				  3	Alternate	Replace one glyph with one of many glyphs
				  4	Ligature	Replace multiple glyphs with one glyph
				  5	Context	Replace one or more glyphs in context
				  6	Chaining Context	Replace one or more glyphs in chained context
				  7	Extension Substitution	Extension mechanism for other substitutions (i.e. this excludes the Extension type substitution itself)
				  8	Reverse chaining context single 	Applied in reverse order, replace single glyph in chaining context
				 */

				// LookupType 1: Single Substitution Subtable
				if ($Lookup[$i]['Type'] == 1) {
					$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					if ($SubstFormat == 1) { // Calculated output glyph indices
						$Lookup[$i]['Subtable'][$c]['DeltaGlyphID'] = $this->reader->readInt16();
					} elseif ($SubstFormat == 2) { // Specified output glyph indices
						$GlyphCount = $this->reader->readUInt16();
						for ($g = 0; $g < $GlyphCount; $g++) {
							$Lookup[$i]['Subtable'][$c]['Glyphs'][] = $this->reader->readUInt16();
						}
					}
				} // LookupType 2: Multiple Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 2) {
					$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					$Lookup[$i]['Subtable'][$c]['SequenceCount'] = $SequenceCount = $this->reader->readUInt16();
					for ($s = 0; $s < $SequenceCount; $s++) {
						$Lookup[$i]['Subtable'][$c]['Sequences'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					}
					for ($s = 0; $s < $SequenceCount; $s++) {
						// Sequence Tables
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['Sequences'][$s]['Offset']);
						$Lookup[$i]['Subtable'][$c]['Sequences'][$s]['GlyphCount'] = $this->reader->readUInt16();
						for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['Sequences'][$s]['GlyphCount']; $g++) {
							$Lookup[$i]['Subtable'][$c]['Sequences'][$s]['SubstituteGlyphID'][] = $this->reader->readUInt16();
						}
					}
				} // LookupType 3: Alternate Forms
				elseif ($Lookup[$i]['Type'] == 3) {
					$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					$Lookup[$i]['Subtable'][$c]['AlternateSetCount'] = $AlternateSetCount = $this->reader->readUInt16();
					for ($s = 0; $s < $AlternateSetCount; $s++) {
						$Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					}

					for ($s = 0; $s < $AlternateSetCount; $s++) {
						// AlternateSet Tables
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['Offset']);
						$Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['GlyphCount'] = $this->reader->readUInt16();
						for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['GlyphCount']; $g++) {
							$Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['SubstituteGlyphID'][] = $this->reader->readUInt16();
						}
					}
				} // LookupType 4: Ligature Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 4) {
					$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					$Lookup[$i]['Subtable'][$c]['LigSetCount'] = $LigSetCount = $this->reader->readUInt16();
					for ($s = 0; $s < $LigSetCount; $s++) {
						$Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					}
					for ($s = 0; $s < $LigSetCount; $s++) {
						// LigatureSet Tables
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Offset']);
						$Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount'] = $this->reader->readUInt16();
						for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount']; $g++) {
							$Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigatureOffset'][$g] = $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Offset'] + $this->reader->readUInt16();
						}
					}
					for ($s = 0; $s < $LigSetCount; $s++) {
						for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount']; $g++) {
							// Ligature tables
							$this->reader->seek($Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigatureOffset'][$g]);
							$Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['LigGlyph'] = $this->reader->readUInt16();
							$Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount'] = $this->reader->readUInt16();
							for ($l = 1; $l < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']; $l++) {
								$Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['GlyphID'][$l] = $this->reader->readUInt16();
							}
						}
					}
				} // LookupType 5: Contextual Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 5) {
					// Format 1: Context Substitution
					if ($SubstFormat == 1) {
						$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['SubRuleSetCount'] = $SubRuleSetCount = $this->reader->readUInt16();
						for ($s = 0; $s < $SubRuleSetCount; $s++) {
							$Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						}
						for ($s = 0; $s < $SubRuleSetCount; $s++) {
							$ruleOffsets = SequenceRule::ruleOffsets($this->reader, $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['Offset']);
							$Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleCount'] = count($ruleOffsets);
							foreach ($ruleOffsets as $g => $ruleOffset) {
								$this->reader->seek($ruleOffset);
								list($input, $SubstCount) = SequenceRule::plain($this->reader);
								$Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g] = [
									'GlyphCount' => count($input) + 1,
									'SubstCount' => $SubstCount,
									'Input' => $this->fromPosition1($input),
									'SubstLookupRecord' => SequenceRule::lookupRecords($this->reader, $SubstCount),
								];
							}
						}
					} // Format 2: Class-based Context Glyph Substitution
					elseif ($SubstFormat == 2) {
						$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['ClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['SubClassSetCnt'] = $this->reader->readUInt16();
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubClassSetCnt']; $b++) {
							$offset = $this->reader->readUInt16();
							if ($offset == 0x0000) {
								$Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][] = 0;
							} else {
								$Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $offset;
							}
						}
					} // Format 3: Coverage-based Context Glyph Substitution
					elseif ($SubstFormat == 3) {
						// NB Unlike Lookup Type 6 Format 3, the count of substitutions precedes the Coverage table offsets
						$Lookup[$i]['Subtable'][$c]['InputGlyphCount'] = $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['SubstCount'] = $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['CoverageInput'] = SequenceRule::coverageOffsets($this->reader, $Lookup[$i]['Subtable'][$c]['Offset'], $Lookup[$i]['Subtable'][$c]['InputGlyphCount']);
						$Lookup[$i]['Subtable'][$c]['SubstLookupRecord'] = SequenceRule::lookupRecords($this->reader, $Lookup[$i]['Subtable'][$c]['SubstCount']);
					} else {
						throw new \Mpdf\Exception\FontException("GSUB Lookup Type " . $Lookup[$i]['Type'] . ", Format " . $SubstFormat . " not supported.");
					}
				} // LookupType 6: Chaining Contextual Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 6) {
					// Format 1: Simple Chaining Context Glyph Substitution  p255
					if ($SubstFormat == 1) {
						$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount'] = $this->reader->readUInt16();
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $b++) {
							$Lookup[$i]['Subtable'][$c]['ChainSubRuleSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						}
					} // Format 2: Class-based Chaining Context Glyph Substitution  p257
					elseif ($SubstFormat == 2) {
						$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['BacktrackClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['InputClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['LookaheadClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt'] = $this->reader->readUInt16();
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt']; $b++) {
							$offset = $this->reader->readUInt16();
							if ($offset == 0x0000) {
								$Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][] = $offset;
							} else {
								$Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $offset;
							}
						}
					} // Format 3: Coverage-based Chaining Context Glyph Substitution  p259
					elseif ($SubstFormat == 3) {
						$base = $Lookup[$i]['Subtable'][$c]['Offset'];
						$Lookup[$i]['Subtable'][$c]['CoverageBacktrack'] = SequenceRule::coverageOffsets($this->reader, $base, $this->reader->readUInt16());
						$Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount'] = count($Lookup[$i]['Subtable'][$c]['CoverageBacktrack']);
						$Lookup[$i]['Subtable'][$c]['CoverageInput'] = SequenceRule::coverageOffsets($this->reader, $base, $this->reader->readUInt16());
						$Lookup[$i]['Subtable'][$c]['InputGlyphCount'] = count($Lookup[$i]['Subtable'][$c]['CoverageInput']);
						$Lookup[$i]['Subtable'][$c]['CoverageLookahead'] = SequenceRule::coverageOffsets($this->reader, $base, $this->reader->readUInt16());
						$Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount'] = count($Lookup[$i]['Subtable'][$c]['CoverageLookahead']);
						$Lookup[$i]['Subtable'][$c]['SubstCount'] = $this->reader->readUInt16();
						$Lookup[$i]['Subtable'][$c]['SubstLookupRecord'] = SequenceRule::lookupRecords($this->reader, $Lookup[$i]['Subtable'][$c]['SubstCount']);
					}
				} // LookupType 8: Reverse Chaining Contextual Single Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 8) {
					// Format 1 is the only one the specification defines
					if ($SubstFormat != 1) {
						throw new \Mpdf\Exception\FontException("GSUB Lookup Type " . $Lookup[$i]['Type'] . ", Format " . $SubstFormat . " not supported.");
					}
					$Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->reader->readUInt16();
					$base = $Lookup[$i]['Subtable'][$c]['Offset'];
					$Lookup[$i]['Subtable'][$c]['CoverageBacktrack'] = SequenceRule::coverageOffsets($this->reader, $base, $this->reader->readUInt16());
					$Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount'] = count($Lookup[$i]['Subtable'][$c]['CoverageBacktrack']);
					$Lookup[$i]['Subtable'][$c]['CoverageLookahead'] = SequenceRule::coverageOffsets($this->reader, $base, $this->reader->readUInt16());
					$Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount'] = count($Lookup[$i]['Subtable'][$c]['CoverageLookahead']);
					// One substitute glyph per glyph in the Coverage table - the substitution is written into the
					// subtable itself rather than delegated to a Lookup, as every other contextual type does
					$Lookup[$i]['Subtable'][$c]['GlyphCount'] = $this->reader->readUInt16();
					$Lookup[$i]['Subtable'][$c]['SubstituteGlyphID'] = SequenceRule::values($this->reader, $Lookup[$i]['Subtable'][$c]['GlyphCount']);
				} else {
					throw new \Mpdf\Exception\FontException(sprintf('Lookup Type "%s" not supported.', $Lookup[$i]['Type']));
				}
			}
		}
	}

	/**
	 * Pass two: the Coverage and ClassDef tables each subtable points at, resolved into the glyphs
	 * and the strings of glyphs the rules are matched and replaced with.
	 *
	 * Where pass one records the shape of a subtable, this records what it does. Shared with OtlDump
	 * apart from two decisions about what is worth recording, which are the hooks below.
	 */
	private function readGSUBrules(array &$Lookup)
	{
		$LookupCount = count($Lookup);

		for ($i = 0; $i < $LookupCount; $i++) {
			for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
				$SubstFormat = $Lookup[$i]['Subtable'][$c]['Format'];

				// Stated even where nothing is recorded, so that a subtable whose every entry the
				// Ignore flags turned away reads as empty rather than absent, and the readers below can
				// count it without asking whether it is there
				$Lookup[$i]['Subtable'][$c]['subs'] = [];

				// LookupType 1: Single Substitution Subtable 1 => 1
				if ($Lookup[$i]['Type'] == 1) {
					$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
					$glyphs = $this->_getCoverage(false);
					for ($g = 0; $g < count($glyphs); $g++) {
						$replace = [];
						$replace[] = GlyphString::of($this->glyphToChar[$glyphs[$g]][0]);
						// Flag = Ignore
						if ($this->_checkGSUBignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
							continue;
						}
						if (isset($Lookup[$i]['Subtable'][$c]['DeltaGlyphID'])) { // Format 1
							// The modulo is how a font names a glyph below the one it covers: Cactus
							// Classical Serif reaches its extended em dash with -7504 from glyph 504
							$gid = ($glyphs[$g] + $Lookup[$i]['Subtable'][$c]['DeltaGlyphID']) & 0xFFFF;
						} else { // Format 2
							$gid = $Lookup[$i]['Subtable'][$c]['Glyphs'][$g];
						}
						$substitute = $this->substituteGlyph($gid);
						if ($substitute === null) {
							continue;
						}
						$Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
					}
				} // LookupType 2: Multiple Substitution Subtable 1 => n
				elseif ($Lookup[$i]['Type'] == 2) {
					$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
					$glyphs = $this->_getCoverage();
					for ($g = 0; $g < count($glyphs); $g++) {
						$replace = [];
						$replace[] = $glyphs[$g];
						// Flag = Ignore
						if ($this->_checkGSUBignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
							continue;
						}
						$sequence = isset($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID'])
							? $Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID']
							: [];
						$substitute = $this->multipleSubstitutes($sequence);
						if ($substitute === null) {
							continue;
						}
						$Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
					}
				} // LookupType 3: Alternate Forms 1 => 1 (only first alternate form is used)
				elseif ($Lookup[$i]['Type'] == 3) {
					$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
					$glyphs = $this->_getCoverage();
					for ($g = 0; $g < count($glyphs); $g++) {
						$replace = [];
						$replace[] = $glyphs[$g];
						// Flag = Ignore
						if ($this->_checkGSUBignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
							continue;
						}
						$substitute = $this->alternateSubstitutes($Lookup[$i]['Subtable'][$c]['AlternateSets'][$g]);
						if ($substitute === null) {
							continue;
						}
						$Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
					}
				} // LookupType 4: Ligature Substitution Subtable n => 1
				elseif ($Lookup[$i]['Type'] == 4) {
					$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
					$glyphs = $this->_getCoverage();
					$LigSetCount = $Lookup[$i]['Subtable'][$c]['LigSetCount'];
					for ($s = 0; $s < $LigSetCount; $s++) {
						for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount']; $g++) {
							$replace = [];
							$replace[] = $glyphs[$s];
							// Flag = Ignore
							if ($this->_checkGSUBignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
								continue;
							}
							for ($l = 1; $l < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']; $l++) {
								$gid = $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['GlyphID'][$l];
								$rpl = GlyphString::of($this->glyphToChar[$gid][0]);
								// Flag = Ignore
								if ($this->_checkGSUBignore($Lookup[$i]['Flag'], $rpl, $Lookup[$i]['MarkFilteringSet'])) {
									continue 2;
								}
								$replace[] = $rpl;
							}
							$gid = $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['LigGlyph'];
							$substitute = $this->substituteGlyph($gid);
							if ($substitute === null) {
								continue;
							}
							$Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute, 'CompCount' => $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']];
						}
					}
				} // LookupType 5: Contextual Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 5) {
					// Format 1: Context Substitution
					if ($SubstFormat == 1) {
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
						$Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $CoverageGlyphs = $this->_getCoverage();

						for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubRuleSetCount']; $s++) {
							$Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['FirstGlyph'] = $CoverageGlyphs[$s];
							for ($r = 0; $r < $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleCount']; $r++) {
								$Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$r]['InputGlyphs'] = $this->glyphStrings($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$r]['Input']);
							}
						}
					} // Format 2: Class-based Context Glyph Substitution
					elseif ($SubstFormat == 2) {
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
						$Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $CoverageGlyphs = $this->_getCoverage();

						$InputClasses = $this->_getClasses($Lookup[$i]['Subtable'][$c]['ClassDefOffset']);
						$Lookup[$i]['Subtable'][$c]['InputClasses'] = $InputClasses;
						for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubClassSetCnt']; $s++) {
							if ($Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s] > 0) {
								$ruleOffsets = SequenceRule::ruleOffsets($this->reader, $Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s]);
								$Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRuleCnt'] = count($ruleOffsets);
								foreach ($ruleOffsets as $b => $ruleOffset) {
									$this->reader->seek($ruleOffset);
									list($input, $SubstCount) = SequenceRule::plain($this->reader);
									$Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRule'][$b] = [
										'InputGlyphCount' => count($input) + 1,
										'SubstCount' => $SubstCount,
										'Input' => $this->fromPosition1($input),
										'SubstLookupRecord' => SequenceRule::lookupRecords($this->reader, $SubstCount),
									];
								}
							}
						}
					} // Format 3: Coverage-based Context Glyph Substitution
					elseif ($SubstFormat == 3) {
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['InputGlyphCount']; $b++) {
							$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageInput'][$b]);
							$glyphs = $this->_getCoverage();
							$Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'][] = implode("|", $glyphs);
						}
					}
				} // LookupType 6: Chaining Contextual Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 6) {
					// Format 1: Simple Chaining Context Glyph Substitution  p255
					if ($SubstFormat == 1) {
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
						$Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $CoverageGlyphs = $this->_getCoverage();

						for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $s++) {
							foreach (SequenceRule::ruleOffsets($this->reader, $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetOffset'][$s]) as $r => $ruleOffset) {
								$this->reader->seek($ruleOffset);
								list($backtrack, $input, $lookahead) = SequenceRule::chained($this->reader);
								$SubstCount = $this->reader->readUInt16();
								$Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r] = [
									'BacktrackGlyphCount' => count($backtrack),
									'BacktrackGlyphs' => $this->glyphStrings($backtrack),
									'InputGlyphCount' => count($input) + 1,
									'InputGlyphs' => $this->glyphStrings($this->fromPosition1($input)),
									'LookaheadGlyphCount' => count($lookahead),
									'LookaheadGlyphs' => $this->glyphStrings($lookahead),
									'SubstCount' => $SubstCount,
									'SubstLookupRecord' => SequenceRule::lookupRecords($this->reader, $SubstCount),
								];
							}
						}
					} // Format 2: Class-based Chaining Context Glyph Substitution  p257
					elseif ($SubstFormat == 2) {
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
						$Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $CoverageGlyphs = $this->_getCoverage();

						$BacktrackClasses = $this->_getClasses($Lookup[$i]['Subtable'][$c]['BacktrackClassDefOffset']);
						$Lookup[$i]['Subtable'][$c]['BacktrackClasses'] = $BacktrackClasses;

						$InputClasses = $this->_getClasses($Lookup[$i]['Subtable'][$c]['InputClassDefOffset']);
						$Lookup[$i]['Subtable'][$c]['InputClasses'] = $InputClasses;

						$LookaheadClasses = $this->_getClasses($Lookup[$i]['Subtable'][$c]['LookaheadClassDefOffset']);
						$Lookup[$i]['Subtable'][$c]['LookaheadClasses'] = $LookaheadClasses;

						for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt']; $s++) {
							if ($Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][$s] > 0) {
								$ruleOffsets = SequenceRule::ruleOffsets($this->reader, $Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][$s]);
								$Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRuleCnt'] = count($ruleOffsets);
								foreach ($ruleOffsets as $b => $ruleOffset) {
									$this->reader->seek($ruleOffset);
									list($backtrack, $input, $lookahead) = SequenceRule::chained($this->reader);
									$SubstCount = $this->reader->readUInt16();
									$Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRule'][$b] = [
										'BacktrackGlyphCount' => count($backtrack),
										'Backtrack' => $backtrack,
										'InputGlyphCount' => count($input) + 1,
										'Input' => $this->fromPosition1($input),
										'LookaheadGlyphCount' => count($lookahead),
										'Lookahead' => $lookahead,
										'SubstCount' => $SubstCount,
										'SubstLookupRecord' => SequenceRule::lookupRecords($this->reader, $SubstCount),
									];
								}
							}
						}
					} // Format 3: Coverage-based Chaining Context Glyph Substitution  p259
					elseif ($SubstFormat == 3) {
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']; $b++) {
							$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageBacktrack'][$b]);
							$glyphs = $this->_getCoverage();
							$Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'][] = implode("|", $glyphs);
						}
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['InputGlyphCount']; $b++) {
							$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageInput'][$b]);
							$glyphs = $this->_getCoverage();
							$Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'][] = implode("|", $glyphs);
							// Don't use above value as these are ordered numerically not as need to process
						}
						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']; $b++) {
							$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageLookahead'][$b]);
							$glyphs = $this->_getCoverage();
							$Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'][] = implode("|", $glyphs);
						}
					}
				} // LookupType 8: Reverse Chaining Contextual Single Substitution 1 => 1
				elseif ($Lookup[$i]['Type'] == 8) {
					$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
					$glyphs = $this->_getCoverage();
					$Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'] = [implode("|", $glyphs)];
					for ($g = 0; $g < count($glyphs); $g++) {
						$replace = [];
						$replace[] = $glyphs[$g];
						// Flag = Ignore
						if ($this->_checkGSUBignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
							continue;
						}
						if (!isset($Lookup[$i]['Subtable'][$c]['SubstituteGlyphID'][$g])) {
							continue;
						} // The substitutes must run parallel to the Coverage table; either an error in the font, or something has gone wrong
						$substitute = $this->substituteGlyph($Lookup[$i]['Subtable'][$c]['SubstituteGlyphID'][$g]);
						if ($substitute === null) {
							continue;
						}
						$Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
					}
					for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']; $b++) {
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageBacktrack'][$b]);
						$glyphs = $this->_getCoverage();
						$Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'][] = implode("|", $glyphs);
					}
					for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']; $b++) {
						$this->reader->seek($Lookup[$i]['Subtable'][$c]['CoverageLookahead'][$b]);
						$glyphs = $this->_getCoverage();
						$Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'][] = implode("|", $glyphs);
					}
				}
			}
		}

		return $Lookup;
	}

	/**
	 * An input sequence as SequenceRule reads it, keyed by its position in the rule. The rules here are
	 * kept that way so that position 0 - the glyph or class the rule set was reached through, which
	 * the rule does not list - can be put in front of them and sorted into place.
	 */
	private function fromPosition1(array $values)
	{
		return $values ? array_combine(range(1, count($values)), $values) : [];
	}

	/**
	 * @param int[] $glyphIDs
	 *
	 * @return string[] Each glyph as the character it is matched by, keys kept
	 */
	private function glyphStrings(array $glyphIDs)
	{
		$strings = [];
		foreach ($glyphIDs as $position => $glyphID) {
			$strings[$position] = GlyphString::of($this->glyphToChar[$glyphID][0]);
		}

		return $strings;
	}

	/**
	 * What the parser does with the GSUB rules once it has read them: build, for every script and
	 * language system the font offers, the derived tables the shaper works from, and cache each one.
	 *
	 * OtlDump overrides this to report the rules of the one script it was asked for instead - and so
	 * never caches the coverage above, which only a shaper has a use for. It is the GSUB counterpart
	 * of useGPOSlookups(), and the same argument applies: everything above it is reading, and reading
	 * is what the two are meant not to disagree about.
	 *
	 * @return string The Private Use Area ranges every RTL glyph of this font was mapped into, as a
	 *                regular expression character class for magic_reverse. The report says nothing
	 *                about that mapping, so the dump returns an empty one.
	 */
	protected function useGSUBlookups(array $Lookup, array $gsub, array $GSLookup, $gsubOffset)
	{
		$this->cacheGSUBcoverage($GSLookup, $gsubOffset);

		$rtlpua = []; // All glyphs added to PUA [for magic_reverse]
		foreach ($gsub as $st => $scripts) {
			foreach ($scripts as $t => $langsys) {
				$lul = []; // array of LookupListIndexes
				$tags = []; // corresponding array of feature tags e.g. 'ccmp'

				foreach ($langsys as $tag => $ft) {
					foreach ($ft as $ll) {
						$lul[$ll] = $tag;
					}
				}
				ksort($lul); // Order the Lookups in the order they are in the GUSB table, regardless of Feature order
				$volt = $this->_getGSUBarray($Lookup, $lul, $st);

				// Interrogate $volt
				// isol, fin, medi, init(arab syrc) into $rtlSUB for use in Shaper\Arabic::shape()
				// but also identify all RTL chars in PUA for magic_reverse (arab syrc hebr thaa nko  samr)
				// identify reph, matras, vatu, half forms etc for Indic for final re-ordering
				$rtl = [];
				$rtlSUB = [];
				$finals = '';

				if (strpos('arab syrc hebr thaa nko  samr', $st) !== false) { // all RTL scripts [any/all languages] ? Mandaic

					foreach ($volt as $v) {
						// isol fina fin2 fin3 medi med2 for Syriac
						// ISOLATED FORM :: FINAL :: INITIAL :: MEDIAL :: MED2 :: FIN2 :: FIN3
						// A contextual entry carries the feature's own tag but keeps its replacements in
						// ['rules'], so it has no ['replace'] for this branch to read
						if (strpos('isol fina init medi fin2 fin3 med2', $v['tag']) !== false && !isset($v['context'])) {

							$key = $v['match'];
							$key = preg_replace('/[\(\)]*/', '', $key);
							$sub = $v['replace'];
							if ($v['tag'] === 'isol') {
								$kk = 0;
							} elseif ($v['tag'] === 'fina') {
								$kk = 1;
							} elseif ($v['tag'] === 'init') {
								$kk = 2;
							} elseif ($v['tag'] === 'medi') {
								$kk = 3;
							} elseif ($v['tag'] === 'med2') {
								$kk = 4;
							} elseif ($v['tag'] === 'fin2') {
								$kk = 5;
							} elseif ($v['tag'] === 'fin3') {
								$kk = 6;
							}

							$rtl[$key][$kk] = $sub;
							if (isset($v['prel']) && count($v['prel'])) {
								$rtl[$key]['prel'][$kk] = $v['prel'];
							}
							if (isset($v['postl']) && count($v['postl'])) {
								$rtl[$key]['postl'][$kk] = $v['postl'];
							}
							if (isset($v['ignore']) && $v['ignore']) {
								$rtl[$key]['ignore'][$kk] = $v['ignore'];
							}
							$this->addPuaGlyphs($rtlpua, $sub);

						} else { // Add any other glyphs which are in PUA
							if (isset($v['context']) && $v['context']) {
								foreach ($v['rules'] as $vs) {
									$matchCount = count($vs['match']);
									for ($i = 0; $i < $matchCount; $i++) {
										if (isset($vs['replace'][$i]) && preg_match('/^0[A-F0-9]{4}$/', $vs['match'][$i])) {
											$this->addPuaGlyphs($rtlpua, $vs['replace'][$i]);
										}
									}
								}
							} else {
								$this->addPuaGlyphs($rtlpua, $v['replace']);
							}
						}
					}

					// For kashida, need to determine all final forms except ones already identified by kashida priority rules (see \Mpdf\Otl)
					foreach ($rtl as $base => $variants) {
						if (isset($variants[1])) { // i.e. final form
							// A form of several glyphs is a base and the marks drawn on it, and the kashida
							// point belongs to the base. Otl matches one glyph against this string, so every
							// other glyph of an entry is a substring anyone can match
							list($final) = explode(' ', $variants[1]);

							if (strpos('0FE8E 0FE94 0FEA2 0FEAA 0FEAE 0FEC2 0FEDA 0FEDE 0FB93 0FECA 0FED2 0FED6 0FEEE 0FEF0 0FEF2', $final) === false) { // not already included
								$finals .= $final . ' ';
							}
						}
					}

					ksort($rtl);
					$rtlSUB = $rtl;
				}

				// INDIC - Dynamic properties
				$rphf = [];
				$half = [];
				$pref = [];
				$blwf = [];
				$pstf = [];

				if (strpos('dev2 bng2 gur2 gjr2 ory2 tml2 tel2 knd2 mlm2 deva beng guru gujr orya taml telu knda mlym', $st) !== false) { // all INDIC scripts [any/all languages]
					if (strpos('deva beng guru gujr orya taml telu knda mlym', $st) !== false) {
						$is_old_spec = true;
					} else {
						$is_old_spec = false;
					}

					// First get 'locl' substitutions (reversed!)
					// Contextual entries are skipped here as they are above: their replacements are in
					// ['rules'], not ['replace']
					$loclsubs = [];
					foreach ($volt as $v) {
						if (strpos('locl', $v['tag']) !== false && !isset($v['context'])) {
							$key = $v['match'];
							$key = preg_replace('/[\(\)]*/', '', $key);
							$sub = $v['replace'];
							if ($key && strlen(trim($key)) == 5 && $sub) {
								$loclsubs[$sub] = $key;
							}
						}
					}

					foreach ($volt as $v) {
						// <rphf> <half> <pref> <blwf> <pstf>
						// defines consonant types:
						//     Reph <rphf>
						//     Half forms <half>
						//     Pre-base-reordering forms of Ra/Rra <pref>
						//     Below-base forms <blwf>
						//     Post-base forms <pstf>
						// applied together with <locl> feature to input sequences consisting of two characters
						// This is done for each consonant
						// for <rphf> and <half>, features are applied to Consonant + Halant combinations
						// for <pref>, <blwf> and <pstf>, features are applied to Halant + Consonant combinations
						// Old version eg 'deva' <pref>, <blwf> and <pstf>, features are applied to Consonant + Halant
						// Some malformed fonts still do Consonant + Halant for these - so match both??
						// If these two glyphs form a ligature, with no additional glyphs in context
						// this means the consonant has the corresponding form
						// Currently set to cope with both
						// See also classes/otl.php

						if (strpos('rphf half pref blwf pstf', $v['tag']) !== false) {
							if (isset($v['context']) && $v['context'] && $v['nBacktrack'] == 0 && $v['nLookahead'] == 0) {
								foreach ($v['rules'] as $vs) {
									if (count($vs['match']) == 2 && count($vs['replace']) == 1) {
										$sub = $vs['replace'][0];
										// If Halant Cons   <pref>, <blwf> and <pstf> in New version only
										if (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', $vs['match'][0]) !== false && strpos('pref blwf pstf', $v['tag']) !== false && !$is_old_spec) {
											$key = $vs['match'][1];
											$tag = $v['tag'];
											if (isset($loclsubs[$key])) {
												${$tag}[$loclsubs[$key]] = $sub;
											}
											$tmp = &$$tag;
											$tmp[hexdec($key)] = hexdec($sub);
										} // If Cons Halant    <rphf> and <half> always
										// and <pref>, <blwf> and <pstf> in Old version
										elseif (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', $vs['match'][1]) !== false && (strpos('rphf half', $v['tag']) !== false || (strpos('pref blwf pstf', $v['tag']) !== false && ($is_old_spec || _OTL_OLD_SPEC_COMPAT_2)))) {
											$key = $vs['match'][0];
											$tag = $v['tag'];
											if (isset($loclsubs[$key])) {
												${$tag}[$loclsubs[$key]] = $sub;
											}
											$tmp = &$$tag;
											$tmp[hexdec($key)] = hexdec($sub);
										}
									}
								}
							} elseif (!isset($v['context'])) {
								$key = $v['match'];
								$key = preg_replace('/[\(\)]*/', '', $key);
								$sub = $v['replace'];
								if ($key && strlen(trim($key)) == 11 && $sub) {
									// If Cons Halant    <rphf> and <half> always
									// and <pref>, <blwf> and <pstf> in Old version
									// If Halant Cons   <pref>, <blwf> and <pstf> in New version only
									if (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', substr($key, 0, 5)) !== false && strpos('pref blwf pstf', $v['tag']) !== false && !$is_old_spec) {
										$key = substr($key, 6, 5);
										$tag = $v['tag'];
										if (isset($loclsubs[$key])) {
											${$tag}[$loclsubs[$key]] = $sub;
										}
										$tmp = &$$tag;
										$tmp[hexdec($key)] = hexdec($sub);
									} elseif (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', substr($key, 6, 5)) !== false && (strpos('rphf half', $v['tag']) !== false || (strpos('pref blwf pstf', $v['tag']) !== false && ($is_old_spec || _OTL_OLD_SPEC_COMPAT_2)))) {
										$key = substr($key, 0, 5);
										$tag = $v['tag'];
										if (isset($loclsubs[$key])) {
											${$tag}[$loclsubs[$key]] = $sub;
										}
										$tmp = &$$tag;
										$tmp[hexdec($key)] = hexdec($sub);
									}
								}
							}
						}
					}
				}

				if (count($rtl) || count($rphf) || count($half) || count($pref) || count($blwf) || count($pstf) || $finals) {
					$font = [
						'rtlSUB' => $rtlSUB,
						'finals' => $finals,
						'rphf' => $rphf,
						'half' => $half,
						'pref' => $pref,
						'blwf' => $blwf,
						'pstf' => $pstf,
					];

					$this->fontCache->jsonWrite($this->fontkey . '.GSUB.' . $st . '.' . $t . '.json', $font);
				}
			}
		}

		// All RTL glyphs from font added to (or already in) PUA [reqd for magic_reverse]
		$rtlPUAstr = '';
		if (count($rtlpua)) {
			$rtlpua = array_unique($rtlpua);
			sort($rtlpua);
			$n = count($rtlpua);
			for ($i = 0; $i < $n; $i++) {
				if (hexdec($rtlpua[$i]) < hexdec('E000') || hexdec($rtlpua[$i]) > hexdec('F8FF')) {
					unset($rtlpua[$i]);
				}
			}
			sort($rtlpua, SORT_STRING);

			$rangeid = -1;
			$range = [];
			$prevgid = -2;

			// for each character
			foreach ($rtlpua as $gidhex) {
				$gid = hexdec($gidhex);
				if ($gid == ($prevgid + 1)) {
					$range[$rangeid]['end'] = $gidhex;
					$range[$rangeid]['count']++;
				} else {
					// new range
					$rangeid++;
					$range[$rangeid] = [];
					$range[$rangeid]['start'] = $gidhex;
					$range[$rangeid]['end'] = $gidhex;
					$range[$rangeid]['count'] = 1;
				}
				$prevgid = $gid;
			}

			foreach ($range as $rg) {
				if ($rg['count'] == 1) {
					$rtlPUAstr .= "\x{" . $rg['start'] . "}";
				} elseif ($rg['count'] == 2) {
					$rtlPUAstr .= "\x{" . $rg['start'] . "}\x{" . $rg['end'] . "}";
				} else {
					$rtlPUAstr .= "\x{" . $rg['start'] . "}-\x{" . $rg['end'] . "}";
				}
			}
		}

		return $rtlPUAstr;
	}

	/**
	 * Turn a list of GSUB lookups into the substitution rules the shaper applies.
	 *
	 * @param array  $Lookup    The GSUB lookup list, with subtable offsets already made absolute
	 * @param array  $lul       The lookups to read, as lookup index => the feature tag that asked for
	 *                          it
	 * @param string $scripttag The script the rules are being read for
	 *
	 * @return array One entry per subtable, each holding the rules it states
	 */
	function _getGSUBarray(array $Lookup, $lul, $scripttag)
	{
		// Process (3) LookupList for specific Script-LangSys
		// Generate preg_replace
		$volt = [];
		$reph = '';
		$matraE = '';
		$vatu = '';

		foreach ($lul as $i => $tag) {
			for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
				$SubstFormat = $Lookup[$i]['Subtable'][$c]['Format'];

				// LookupType 1: Single Substitution Subtable
				if ($Lookup[$i]['Type'] == 1) {
					$subCount = count($Lookup[$i]['Subtable'][$c]['subs']);
					for ($s = 0; $s < $subCount; $s++) {
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
						$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
						// Ignore has already been applied earlier on
						$repl = $this->_makeGSUBinputMatch($inputGlyphs, "()");
						$subs = $this->_makeGSUBinputReplacement(1, $substitute, "()", 0, 1, 0);
						$volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $inputGlyphs[0], 'type' => 1];
					}
				} // LookupType 2: Multiple Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 2) {
					if (!isset($Lookup[$i]['Subtable'][$c]['subs'])) {
						continue; // every entry was filtered out by the Ignore flags
					}

					for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
						$substitute = implode(" ", $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute']);
						// Ignore has already been applied earlier on
						$repl = $this->_makeGSUBinputMatch($inputGlyphs, "()");
						$subs = $this->_makeGSUBinputReplacement(1, $substitute, "()", 0, 1, 0);
						$volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $inputGlyphs[0], 'type' => 2];
					}
				} // LookupType 3: Alternate Forms
				elseif ($Lookup[$i]['Type'] == 3) {
					if (!isset($Lookup[$i]['Subtable'][$c]['subs'])) {
						continue; // every entry was filtered out by the Ignore flags
					}

					for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
						$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
						// Ignore has already been applied earlier on
						$repl = $this->_makeGSUBinputMatch($inputGlyphs, "()");
						$subs = $this->_makeGSUBinputReplacement(1, $substitute, "()", 0, 1, 0);
						$volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $inputGlyphs[0], 'type' => 3];
					}
				} // LookupType 4: Ligature Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 4) {
					if (!isset($Lookup[$i]['Subtable'][$c]['subs'])) {
						continue; // every entry was filtered out by the Ignore flags
					}

					for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
						$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
						// Ignore has already been applied earlier on
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						$repl = $this->_makeGSUBinputMatch($inputGlyphs, $ignore);
						$subs = $this->_makeGSUBinputReplacement(count($inputGlyphs), $substitute, $ignore, 0, count($inputGlyphs), 0);
						$volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $inputGlyphs[0], 'type' => 4, 'CompCount' => $Lookup[$i]['Subtable'][$c]['subs'][$s]['CompCount'], 'Lig' => $substitute];
					}
				} // LookupType 5: Chaining Contextual Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 5) {
					// Format 1: Context Substitution
					if ($SubstFormat == 1) {
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubRuleSetCount']; $s++) {
							// SubRuleSet
							$subRule = [];
							foreach ($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'] as $rule) {
								// SubRule
								$inputGlyphs = [];
								if ($rule['GlyphCount'] > 1) {
									$inputGlyphs = $rule['InputGlyphs'];
								}
								$inputGlyphs[0] = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['FirstGlyph'];
								ksort($inputGlyphs);
								$nInput = count($inputGlyphs);

								$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);
								$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => '', 'match' => $contextInputMatch, 'nBacktrack' => 0, 'nInput' => $nInput, 'nLookahead' => 0, 'rules' => [],];

								for ($b = 0; $b < $rule['SubstCount']; $b++) {
									$lup = $rule['SubstLookupRecord'][$b]['LookupListIndex'];
									$seqIndex = $rule['SubstLookupRecord'][$b]['SequenceIndex'];

									// $Lookup[$lup] = secondary Lookup
									for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
										if (!empty($Lookup[$lup]['Subtable'][$lus]['subs'])) {
											foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
												$lookupGlyphs = $luss['Replace'];
												$mLen = count($lookupGlyphs);

												// Only apply if the (first) 'Replace' glyph from the
												// Lookup list is in the [inputGlyphs] at ['SequenceIndex']
												// then apply the substitution
												if (strpos($inputGlyphs[$seqIndex], $lookupGlyphs[0]) === false) {
													continue;
												}
												$REPL = implode(" ", $luss['substitute']);
												if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
													$volt[] = ['match' => $lookupGlyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrackGlyphs, 'postl' => $lookaheadGlyphs, 'ignore' => $ignore];
												} else {
													$subRule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookupGlyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seqIndex, 'key' => $lookupGlyphs[0],];
												}
											}
										}
									}
								}

								if (count($subRule['rules'])) {
									$volt[] = $subRule;
								}
							}
						}
					} // Format 2: Class-based Context Glyph Substitution
					elseif ($SubstFormat == 2) {
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						foreach ($Lookup[$i]['Subtable'][$c]['SubClassSet'] as $inputClass => $cscs) {
							for ($cscrule = 0; $cscrule < $cscs['SubClassRuleCnt']; $cscrule++) {
								$rule = $cscs['SubClassRule'][$cscrule];

								$inputGlyphs = [];

								if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$inputClass])) {
									$inputGlyphs[0] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$inputClass];
								} else {
									$inputGlyphs[0] = '';
								}
								if ($rule['InputGlyphCount'] > 1) {
									//  NB starts at 1
									for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
										$classindex = $rule['Input'][$gcl];
										if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex])) {
											$inputGlyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex];
										} // if class[0] = all glyphs excluding those specified in all other classes
										// set to blank '' for now
										else {
											$inputGlyphs[$gcl] = '';
										}
									}
								}

								$nInput = $rule['InputGlyphCount'];
								$nIsubs = (2 * $nInput) - 1;

								$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);
								$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => '', 'match' => $contextInputMatch, 'nBacktrack' => 0, 'nInput' => $nInput, 'nLookahead' => 0, 'rules' => [],];

								for ($b = 0; $b < $rule['SubstCount']; $b++) {
									$lup = $rule['SubstLookupRecord'][$b]['LookupListIndex'];
									$seqIndex = $rule['SubstLookupRecord'][$b]['SequenceIndex'];

									// $Lookup[$lup] = secondary Lookup
									for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
										if (isset($Lookup[$lup]['Subtable'][$lus]['subs']) && count($Lookup[$lup]['Subtable'][$lus]['subs'])) {
											foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
												$lookupGlyphs = $luss['Replace'];
												$mLen = count($lookupGlyphs);

												// Only apply if the (first) 'Replace' glyph from the
												// Lookup list is in the [inputGlyphs] at ['SequenceIndex']
												// then apply the substitution
												if (strpos($inputGlyphs[$seqIndex], $lookupGlyphs[0]) === false) {
													continue;
												}

												// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
												$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex);
												$REPL = implode(" ", $luss['substitute']);
												// Returns e.g. "REPL\${6}\${8}" or "\${1}\${2} \${3} REPL\${4}\${6}\${8} \${9}"

												if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
													$volt[] = ['match' => $lookupGlyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrackGlyphs, 'postl' => $lookaheadGlyphs, 'ignore' => $ignore];
												} else {
													$subRule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookupGlyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seqIndex, 'key' => $lookupGlyphs[0],];
												}
											}
										}
									}
								}
								if (count($subRule['rules'])) {
									$volt[] = $subRule;
								}
							}
						}

					} // Format 3: Coverage-based Context Glyph Substitution  p259
					elseif ($SubstFormat == 3) {

						// IgnoreMarks flag set on main Lookup table
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];
						$nInput = $Lookup[$i]['Subtable'][$c]['InputGlyphCount'];

						$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);

						// Type 5 is a plain context: it has no backtrack or lookahead sequence, as Format 1 above
						$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => '', 'match' => $contextInputMatch, 'nBacktrack' => 0, 'nInput' => $nInput, 'nLookahead' => 0, 'rules' => [],];

						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
							$lup = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'];
							$seqIndex = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'];
							for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
								if (empty($Lookup[$lup]['Subtable'][$lus]['subs']) || !is_array($Lookup[$lup]['Subtable'][$lus]['subs'])) {
									continue;
								}

								foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
									$lookupGlyphs = $luss['Replace'];

									// Only apply if the (first) 'Replace' glyph from the
									// Lookup list is in the [inputGlyphs] at ['SequenceIndex']
									// then apply the substitution
									if (strpos($inputGlyphs[$seqIndex], $lookupGlyphs[0]) === false) {
										continue;
									}

									// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
									$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex);
									$REPL = implode(" ", $luss['substitute']);

									if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
										$volt[] = ['match' => $lookupGlyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => [], 'postl' => [], 'ignore' => $ignore];
									} else {
										$subRule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookupGlyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seqIndex, 'key' => $lookupGlyphs[0],];
									}
								}
							}
						}
						if (count($subRule['rules'])) {
							$volt[] = $subRule;
						}
					}

				} // LookupType 6: ing Contextual Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 6) {

					// Format 1: Simple Chaining Context Glyph Substitution  p255
					if ($SubstFormat == 1) {
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $s++) {

							// ChainSubRuleSet
							$subRule = [];
							$firstInputGlyph = $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'][$s]; // First input gyyph

							foreach ($Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'] as $rule) {
								// ChainSubRule
								$inputGlyphs = [];
								if ($rule['InputGlyphCount'] > 1) {
									$inputGlyphs = $rule['InputGlyphs'];
								}
								$inputGlyphs[0] = $firstInputGlyph;
								ksort($inputGlyphs);
								$nInput = count($inputGlyphs);

								if ($rule['BacktrackGlyphCount']) {
									$backtrackGlyphs = $rule['BacktrackGlyphs'];
								} else {
									$backtrackGlyphs = [];
								}
								$backtrackMatch = $this->_makeGSUBbacktrackMatch($backtrackGlyphs, $ignore);

								if ($rule['LookaheadGlyphCount']) {
									$lookaheadGlyphs = $rule['LookaheadGlyphs'];
								} else {
									$lookaheadGlyphs = [];
								}

								$lookaheadMatch = $this->_makeGSUBlookaheadMatch($lookaheadGlyphs, $ignore);

								$nBsubs = 2 * count($backtrackGlyphs);
								$nIsubs = (2 * $nInput) - 1;

								$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);
								$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrackMatch, 'match' => ($contextInputMatch . $lookaheadMatch), 'nBacktrack' => count($backtrackGlyphs), 'nInput' => $nInput, 'nLookahead' => count($lookaheadGlyphs), 'rules' => [],];

								for ($b = 0; $b < $rule['SubstCount']; $b++) {
									$lup = $rule['SubstLookupRecord'][$b]['LookupListIndex'];
									$seqIndex = $rule['SubstLookupRecord'][$b]['SequenceIndex'];

									// $Lookup[$lup] = secondary Lookup
									for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
										if (!empty($Lookup[$lup]['Subtable'][$lus]['subs'])) {
											foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
												$lookupGlyphs = $luss['Replace'];
												$mLen = count($lookupGlyphs);

												// Only apply if the (first) 'Replace' glyph from the
												// Lookup list is in the [inputGlyphs] at ['SequenceIndex']
												// then apply the substitution
												if (strpos($inputGlyphs[$seqIndex], $lookupGlyphs[0]) === false) {
													continue;
												}

												// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
												$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex);

												$REPL = implode(" ", $luss['substitute']);

												if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
													$volt[] = ['match' => $lookupGlyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrackGlyphs, 'postl' => $lookaheadGlyphs, 'ignore' => $ignore];
												} else {
													$subRule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookupGlyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seqIndex, 'key' => $lookupGlyphs[0],];
												}
											}
										}
									}
								}

								if (count($subRule['rules'])) {
									$volt[] = $subRule;
								}
							}
						}

					} // Format 2: Class-based Chaining Context Glyph Substitution  p257
					elseif ($SubstFormat == 2) {
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						foreach ($Lookup[$i]['Subtable'][$c]['ChainSubClassSet'] as $inputClass => $cscs) {
							for ($cscrule = 0; $cscrule < $cscs['ChainSubClassRuleCnt']; $cscrule++) {
								$rule = $cscs['ChainSubClassRule'][$cscrule];

								// These contain classes of glyphs as strings
								// $Lookup[$i]['Subtable'][$c]['InputClasses'][(class)] e.g. 02E6|02E7|02E8
								// $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][(class)]
								// $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][(class)]
								// These contain arrays of classIndexes
								// [Backtrack] [Lookahead] and [Input] (Input is from the second position only)

								$inputGlyphs = [];

								if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$inputClass])) {
									$inputGlyphs[0] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$inputClass];
								} else {
									$inputGlyphs[0] = '';
								}
								if ($rule['InputGlyphCount'] > 1) {
									//  NB starts at 1
									for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
										$classindex = $rule['Input'][$gcl];
										if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex])) {
											$inputGlyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex];
										} // if class[0] = all glyphs excluding those specified in all other classes
										// set to blank '' for now
										else {
											$inputGlyphs[$gcl] = '';
										}
									}
								}

								$nInput = $rule['InputGlyphCount'];

								if ($rule['BacktrackGlyphCount']) {
									for ($gcl = 0; $gcl < $rule['BacktrackGlyphCount']; $gcl++) {
										$classindex = $rule['Backtrack'][$gcl];
										if (isset($Lookup[$i]['Subtable'][$c]['BacktrackClasses'][$classindex])) {
											$backtrackGlyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][$classindex];
										} // if class[0] = all glyphs excluding those specified in all other classes
										// set to blank '' for now
										else {
											$backtrackGlyphs[$gcl] = '';
										}
									}
								} else {
									$backtrackGlyphs = [];
								}
								// Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
								$backtrackMatch = $this->_makeGSUBbacktrackMatch($backtrackGlyphs, $ignore);

								if ($rule['LookaheadGlyphCount']) {
									for ($gcl = 0; $gcl < $rule['LookaheadGlyphCount']; $gcl++) {
										$classindex = $rule['Lookahead'][$gcl];
										if (isset($Lookup[$i]['Subtable'][$c]['LookaheadClasses'][$classindex])) {
											$lookaheadGlyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][$classindex];
										} // if class[0] = all glyphs excluding those specified in all other classes
										// set to blank '' for now
										else {
											$lookaheadGlyphs[$gcl] = '';
										}
									}
								} else {
									$lookaheadGlyphs = [];
								}
								// Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
								$lookaheadMatch = $this->_makeGSUBlookaheadMatch($lookaheadGlyphs, $ignore);

								$nBsubs = 2 * count($backtrackGlyphs);
								$nIsubs = (2 * $nInput) - 1;

								$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);
								$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrackMatch, 'match' => ($contextInputMatch . $lookaheadMatch), 'nBacktrack' => count($backtrackGlyphs), 'nInput' => $nInput, 'nLookahead' => count($lookaheadGlyphs), 'rules' => [],];

								for ($b = 0; $b < $rule['SubstCount']; $b++) {
									$lup = $rule['SubstLookupRecord'][$b]['LookupListIndex'];
									$seqIndex = $rule['SubstLookupRecord'][$b]['SequenceIndex'];

									// $Lookup[$lup] = secondary Lookup
									for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
										if (!empty($Lookup[$lup]['Subtable'][$lus]['subs'])) {
											foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
												$lookupGlyphs = $luss['Replace'];
												$mLen = count($lookupGlyphs);

												// Only apply if the (first) 'Replace' glyph from the
												// Lookup list is in the [inputGlyphs] at ['SequenceIndex']
												// then apply the substitution
												if (strpos($inputGlyphs[$seqIndex], $lookupGlyphs[0]) === false) {
													continue;
												}

												// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
												$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex);
												$REPL = implode(" ", $luss['substitute']);
												// Returns e.g. "REPL\${6}\${8}" or "\${1}\${2} \${3} REPL\${4}\${6}\${8} \${9}"

												if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
													$volt[] = ['match' => $lookupGlyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrackGlyphs, 'postl' => $lookaheadGlyphs, 'ignore' => $ignore];
												} else {
													$subRule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookupGlyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seqIndex, 'key' => $lookupGlyphs[0],];
												}
											}
										}
									}
								}
								if (count($subRule['rules'])) {
									$volt[] = $subRule;
								}
							}
						}

					} // Format 3: Coverage-based Chaining Context Glyph Substitution  p259
					elseif ($SubstFormat == 3) {
						// IgnoreMarks flag set on main Lookup table
						$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];
						$CoverageInputGlyphs = implode('|', $inputGlyphs);
						$nInput = $Lookup[$i]['Subtable'][$c]['InputGlyphCount'];

						if ($Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']) {
							$backtrackGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'];
						} else {
							$backtrackGlyphs = [];
						}
						// Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
						$backtrackMatch = $this->_makeGSUBbacktrackMatch($backtrackGlyphs, $ignore);

						if ($Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']) {
							$lookaheadGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'];
						} else {
							$lookaheadGlyphs = [];
						}
						// Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
						$lookaheadMatch = $this->_makeGSUBlookaheadMatch($lookaheadGlyphs, $ignore);

						$nBsubs = 2 * count($backtrackGlyphs);
						$nIsubs = (2 * $nInput) - 1;
						$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);
						$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrackMatch, 'match' => ($contextInputMatch . $lookaheadMatch), 'nBacktrack' => count($backtrackGlyphs), 'nInput' => $nInput, 'nLookahead' => count($lookaheadGlyphs), 'rules' => [],];

						for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
							$lup = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'];
							$seqIndex = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'];
							for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
								if (empty($Lookup[$lup]['Subtable'][$lus]['subs']) || ! is_array($Lookup[$lup]['Subtable'][$lus]['subs'])) {
									continue;
								}

								foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
									$lookupGlyphs = $luss['Replace'];

									// Only apply if the (first) 'Replace' glyph from the
									// Lookup list is in the [inputGlyphs] at ['SequenceIndex']
									// then apply the substitution
									if (strpos($inputGlyphs[$seqIndex], $lookupGlyphs[0]) === false) {
										continue;
									}

									// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
									$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex);
									$REPL = implode(" ", $luss['substitute']);

									if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
										$volt[] = ['match' => $lookupGlyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrackGlyphs, 'postl' => $lookaheadGlyphs, 'ignore' => $ignore];
									} else {
										$subRule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookupGlyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seqIndex, 'key' => $lookupGlyphs[0],];
									}
								}
							}
						}
						if (count($subRule['rules'])) {
							$volt[] = $subRule;
						}
					}
				} // LookupType 8: Reverse Chaining Contextual Single Substitution Subtable
				elseif ($Lookup[$i]['Type'] == 8) {
					if (empty($Lookup[$i]['Subtable'][$c]['subs'])) {
						continue; // every entry was filtered out by the Ignore flags
					}

					// IgnoreMarks flag set on main Lookup table
					$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
					$inputGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];

					if ($Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']) {
						$backtrackGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'];
					} else {
						$backtrackGlyphs = [];
					}
					// Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
					$backtrackMatch = $this->_makeGSUBbacktrackMatch($backtrackGlyphs, $ignore);

					if ($Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']) {
						$lookaheadGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'];
					} else {
						$lookaheadGlyphs = [];
					}
					// Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
					$lookaheadMatch = $this->_makeGSUBlookaheadMatch($lookaheadGlyphs, $ignore);

					// Type 8 replaces exactly one glyph, so the input sequence is always a single Coverage table
					$contextInputMatch = $this->_makeGSUBcontextInputMatch($inputGlyphs, $ignore, [], 0);
					$subRule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrackMatch, 'match' => ($contextInputMatch . $lookaheadMatch), 'nBacktrack' => count($backtrackGlyphs), 'nInput' => 1, 'nLookahead' => count($lookaheadGlyphs), 'rules' => [],];

					foreach ($Lookup[$i]['Subtable'][$c]['subs'] as $luss) {
						if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
							$volt[] = ['match' => $luss['Replace'][0], 'replace' => implode(" ", $luss['substitute']), 'tag' => $tag, 'prel' => $backtrackGlyphs, 'postl' => $lookaheadGlyphs, 'ignore' => $ignore];
						} else {
							$subRule['rules'][] = ['type' => 1, 'match' => $luss['Replace'], 'replace' => $luss['substitute'], 'seqIndex' => 0, 'key' => $luss['Replace'][0],];
						}
					}

					if (count($subRule['rules'])) {
						$volt[] = $subRule;
					}
				}
			}
		}

		return $volt;
	}

	/**
	 * Whether a lookup's flags say to skip one glyph.
	 *
	 * @param int    $flag             The lookup's flags
	 * @param string $glyph            The glyph, as hex
	 * @param int    $MarkFilteringSet The mark glyph set the flags name, where they name one
	 *
	 * @return bool Whether the lookup passes over this glyph rather than matching it
	 */
	function _checkGSUBignore($flag, $glyph, $MarkFilteringSet)
	{
		return $this->lookupFlag->skips($flag, $glyph, $MarkFilteringSet);
	}

	/**
	 * The glyphs a lookup's flags say to skip, as a pattern that matches a run of them.
	 *
	 * Skipping is done in the match rather than by walking the text, so every rule the lookup states
	 * has this dropped between its positions.
	 *
	 * @param int $flag             The lookup's flags
	 * @param int $MarkFilteringSet The mark glyph set the flags name, where they name one
	 *
	 * @return string A capturing group matching any run of the skipped glyphs, or "()" where the
	 *                flags skip nothing
	 */
	function _getGSUBignoreString($flag, $MarkFilteringSet)
	{
		$str = $this->lookupFlag->glyphs($flag, $MarkFilteringSet);

		if ($str) {
			// This originally returned e.g. ((?:(?:[IGNORE8]))*) when NOT specific to a Lookup e.g. rtlSub in
			// arabictypesetting.GSUB.arab.DFLT.php
			// This would save repeatedly saving long text strings if used multiple times
			// When writing e.g. arabictypesetting.GSUB.arab.DFLT.php to file, included as $ignore[8]
			// Would need to also write the $ignore array to that file
			//		// If UseMarkFilteringSet (specific to the Lookup) return the string
			//		if (($flag & 0x0010) && ($flag & 0x0008) != 0x0008) {
			//			return "((?:(?:" . $str . "))*)";
			//		}
			//		else { return "((?:(?:" . "[IGNORE".$ignoreflag."]" . "))*)"; }
			//		// e.g. ((?:(?: 0031| 0032| 0033| 0034| 0045))*)
			// But never finished coding it to add the $ignore array to the file, and it doesn't seem to occur often enough to be worth
			// writing. So just output it as a string:
			return "((?:(?:" . $str . "))*)";
		} else {
			return "()";
		}
	}

	// GSUB Patterns

	/*
	  BACKTRACK                        INPUT                   LOOKAHEAD
	  ==================================  ==================  ==================================
	  (FEEB|FEEC)(ign) ¦(FD12|FD13)(ign) ¦(0612)¦(ign) (0613)¦(ign) (FD12|FD13)¦(ign) (FEEB|FEEC)
	  ----------------  ----------------  -----  ------------  ---------------   ---------------
	  Backtrack 1       Backtrack 2     Input 1   Input 2       Lookahead 1      Lookahead 2
	  --------   ---    ---------  ---    ----   ---   ----   ---   ---------   ---    -------
	  \${1}  \${2}     \${3}   \${4}                      \${5+}  \${6+}    \${7+}  \${8+}

	  nBacktrack = 2               nInput = 2                 nLookahead = 2

	  nBsubs = 2xnBack          nIsubs = (nBsubs+)    nLsubs = (nBsubs+nIsubs+) 2xnLookahead
	  "\${1}\${2} "                 (nInput*2)-1               "\${5+} \${6+}"
	  "REPL"

	  ¦\${1}\${2} ¦\${3}\${4} ¦REPL¦\${5+} \${6+}¦\${7+} \${8+}¦

	  INPUT nInput = 5
	  ============================================================
	  ¦(0612)¦(ign) (0613)¦(ign) (0614)¦(ign) (0615)¦(ign) (0615)¦
	  \${1}  \${2}  \${3}  \${4} \${5} \${6}  \${7} \${8}  \${9} (All backreference numbers are + nBsubs)
	  -----  ------------ ------------ ------------ ------------
	  Input 1   Input 2      Input 3      Input 4      Input 5

	  A======  SequenceIndex=1 ; Lookup match nGlyphs=1
	  B===================  SequenceIndex=1 ; Lookup match nGlyphs=2
	  C===============================  SequenceIndex=1 ; Lookup match nGlyphs=3
	  D=======================  SequenceIndex=2 ; Lookup match nGlyphs=2
	  E=====================================  SequenceIndex=2 ; Lookup match nGlyphs=3
	  F======================  SequenceIndex=4 ; Lookup match nGlyphs=2

	  All backreference numbers are + nBsubs
	  A - "REPL\${2} \${3}\${4} \${5}\${6} \${7}\${8} \${9}"
	  B - "REPL\${2}\${4} \${5}\${6} \${7}\${8} \${9}"
	  C - "REPL\${2}\${4}\${6} \${7}\${8} \${9}"
	  D - "\${1} REPL\${2}\${4}\${6} \${7}\${8} \${9}"
	  E - "\${1} REPL\${2}\${4}\${6}\${8} \${9}"
	  F - "\${1}\${2} \${3}\${4} \${5} REPL\${6}\${8}"
	 */

	/**
	 * The input sequence of a context rule, with the nested lookup's own glyphs standing in at the
	 * positions it applies at.
	 *
	 * Says what has to be there for the nested lookup to fire within the context.
	 *
	 * @param array  $inputGlyphs  The input sequence, one pipe-joined glyph string per position
	 * @param string $ignore       The glyphs the lookup's flags say to skip, between positions
	 * @param array  $lookupGlyphs The nested lookup's own input sequence
	 * @param int    $seqIndex     Which position of the input sequence the nested lookup applies at
	 *
	 * @return string The pattern the sequence matches
	 */
	function _makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex)
	{
		// $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
		// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
		// $inputGlyphs = array of glyphs(glyphstrings) making up Input sequence in Context
		// $lookupGlyphs = array of glyphs (single Glyphs) making up Lookup Input sequence
		$mLen = count($lookupGlyphs); // nGlyphs in the secondary Lookup match
		$nInput = count($inputGlyphs); // nGlyphs in the Primary Input sequence
		$str = "";
		for ($i = 0; $i < $nInput; $i++) {
			if ($i > 0) {
				$str .= $ignore . " ";
			}
			if ($i >= $seqIndex && $i < ($seqIndex + $mLen)) {
				$str .= "(" . $lookupGlyphs[($i - $seqIndex)] . ")";
			} else {
				$str .= "(" . $inputGlyphs[($i)] . ")";
			}
		}

		return $str;
	}

	/**
	 * The input sequence of a context rule, with the skipped glyphs allowed for between positions.
	 *
	 * @param array  $inputGlyphs The input sequence, one pipe-joined glyph string per position
	 * @param string $ignore The glyphs the lookup's flags say to skip, as a capturing group that
	 *                       matches a run of them, or "()" where nothing is skipped
	 *
	 * @return string The pattern the sequence matches
	 */
	function _makeGSUBinputMatch($inputGlyphs, $ignore)
	{
		// $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
		// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
		// $inputGlyphs = array of glyphs(glyphstrings) making up Input sequence in Context
		// $lookupGlyphs = array of glyphs making up Lookup Input sequence - if applicable
		$str = "";
		for ($i = 1; $i <= count($inputGlyphs); $i++) {
			if ($i > 1) {
				$str .= $ignore . " ";
			}
			$str .= "(" . $inputGlyphs[($i - 1)] . ")";
		}

		return $str;
	}

	/**
	 * The backtrack sequence of a chained context rule, read back into writing order.
	 *
	 * A backtrack is stored nearest-first - position 0 is the glyph immediately before the input - so
	 * it is walked backwards to match against the text as written.
	 *
	 * @param array  $backtrackGlyphs The backtrack sequence, one pipe-joined glyph string per position
	 * @param string $ignore The glyphs the lookup's flags say to skip, as a capturing group that
	 *                       matches a run of them, or "()" where nothing is skipped
	 *
	 * @return string The pattern the sequence matches
	 */
	function _makeGSUBbacktrackMatch($backtrackGlyphs, $ignore)
	{
		// $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
		// Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
		// $backtrackGlyphs = array of glyphstrings making up Backtrack sequence
		// 3  2  1  0
		// each item being e.g. E0AD|E0AF|F1FD
		$str = "";
		for ($i = (count($backtrackGlyphs) - 1); $i >= 0; $i--) {
			$str .= "(" . $backtrackGlyphs[$i] . ")" . $ignore . " ";
		}

		return $str;
	}

	/**
	 * The lookahead sequence of a chained context rule, which is already in writing order.
	 *
	 * @param array  $lookaheadGlyphs The lookahead sequence, one pipe-joined glyph string per position
	 * @param string $ignore The glyphs the lookup's flags say to skip, as a capturing group that
	 *                       matches a run of them, or "()" where nothing is skipped
	 *
	 * @return string The pattern the sequence matches
	 */
	function _makeGSUBlookaheadMatch($lookaheadGlyphs, $ignore)
	{
		// $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
		// Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
		// $lookaheadGlyphs = array of glyphstrings making up Lookahead sequence
		// 0  1  2  3
		// each item being e.g. E0AD|E0AF|F1FD
		$str = "";
		for ($i = 0; $i < count($lookaheadGlyphs); $i++) {
			$str .= $ignore . " (" . $lookaheadGlyphs[$i] . ")";
		}

		return $str;
	}

	/**
	 * What a context rule replaces its input sequence with: the nested lookup's output, and a
	 * backreference for every position the nested lookup did not touch.
	 *
	 * The backreferences are what keeps the rest of the context, and the skipped glyphs between its
	 * positions, where they were - the replacement rewrites the whole match, not just the part that
	 * changed.
	 *
	 * @param int    $nInput   Positions in the input sequence
	 * @param string $REPL     The nested lookup's replacement
	 * @param string $ignore   The glyphs the lookup's flags say to skip, or "()" where it skips none
	 * @param int    $nBsubs   Backreferences the backtrack sequence already used, which the ones here
	 *                         are numbered after
	 * @param int    $mLen     Positions the nested lookup matches. Equal to $nInput where there is no
	 *                         nested lookup.
	 * @param int    $seqIndex Which position of the input sequence the nested lookup applies at
	 *
	 * @return string The replacement, with its backreferences
	 */
	function _makeGSUBinputReplacement($nInput, $REPL, $ignore, $nBsubs, $mLen, $seqIndex)
	{
		// Returns e.g. "REPL\${6}\${8}" or "\${1}\${2} \${3} REPL\${4}\${6}\${8} \${9}"
		// $nInput	nGlyphs in the Primary Input sequence
		// $REPL 	replacement glyphs from secondary lookup
		// $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
		// $nBsubs	Number of Backtrack substitutions (= 2x Number of Backtrack glyphs)
		// $mLen 	nGlyphs in the secondary Lookup match - if no secondary lookup, should=$nInput
		// $seqIndex	Sequence Index to apply the secondary match
		if ($ignore == "()") {
			$ign = false;
		} else {
			$ign = true;
		}
		$str = "";
		if ($nInput == 1) {
			$str = $REPL;
		} elseif ($nInput > 1) {
			if ($mLen == $nInput) { // whole string replaced
				$str = $REPL;
				if ($ign) {
					// for every nInput over 1, add another replacement backreference, to move IGNORES after replacement
					for ($x = 2; $x <= $nInput; $x++) {
						$str .= '\\' . ($nBsubs + (2 * ($x - 1)));
					}
				}
			} else { // if only part of string replaced:
				for ($x = 1; $x < ($seqIndex + 1); $x++) {
					if ($x == 1) {
						$str .= '\\' . ($nBsubs + 1);
					} else {
						if ($ign) {
							$str .= '\\' . ($nBsubs + (2 * ($x - 1)));
						}
						$str .= ' \\' . ($nBsubs + 1 + (2 * ($x - 1)));
					}
				}
				if ($seqIndex > 0) {
					$str .= " ";
				}
				$str .= $REPL;
				if ($ign) {
					for ($x = (max(($seqIndex + 1), 2)); $x < ($seqIndex + 1 + $mLen); $x++) { //  move IGNORES after replacement
						$str .= '\\' . ($nBsubs + (2 * ($x - 1)));
					}
				}
				for ($x = ($seqIndex + 1 + $mLen); $x <= $nInput; $x++) {
					if ($ign) {
						$str .= '\\' . ($nBsubs + (2 * ($x - 1)));
					}
					$str .= ' \\' . ($nBsubs + 1 + (2 * ($x - 1)));
				}
			}
		}

		return $str;
	}

	/**
	 * A Coverage table, in whichever of three shapes the caller needs.
	 *
	 * @param bool $convert2hex Return the covered characters as hex strings
	 * @param int  $mode        2 returns unicode => Coverage Index, which is what indexes a
	 *                          subtable's parallel array of substitutions. Anything else, with
	 *                          $convert2hex false, returns the glyph IDs themselves.
	 */
	function _getCoverage($convert2hex = true, $mode = 1)
	{
		$glyphs = Coverage::glyphs($this->reader);

		if (!$convert2hex && $mode != 2) {
			return $glyphs;
		}

		$g = [];
		foreach ($glyphs as $index => $glyphID) {
			// A Coverage table may name a glyph no character reaches, and the position of every glyph
			// after it in the table still has to line up with the rules that index it
			$uni = isset($this->glyphToChar[$glyphID][0]) ? $this->glyphToChar[$glyphID][0] : 0;

			if ($convert2hex) {
				$g[] = GlyphString::of($uni);
			} else {
				$g[$uni] = $index;
			}
		}

		return $g;
	}

	/**
	 * A Class Definition table as one "|"-separated hex string per class, which is the form the
	 * cached GSUB data carries and the shaper's ignore strings are matched against.
	 *
	 * Unlike Otl::_getClasses this keeps class 0, and unlike Otl it drops glyphs no character
	 * reaches rather than testing for them at match time.
	 *
	 * @return array class => "00041|00042|..."
	 */
	function _getClasses($offset)
	{
		$this->reader->seek($offset);
		$GlyphByClass = [];

		foreach (ClassDef::pairs($this->reader) as $pair) {
			list($glyphID, $class) = $pair;

			if (isset($this->glyphToChar[$glyphID][0])) {
				$GlyphByClass[$class][] = GlyphString::of($this->glyphToChar[$glyphID][0]);
			}
		}

		$gbc = [];
		foreach ($GlyphByClass as $class => $garr) {
			$gbc[$class] = implode('|', $garr);
		}

		return $gbc;
	}

	/**
	 * GPOS - Glyph Positioning
	 *
	 * Returns [$GPOSScriptLang, $gpos, $Lookup]: which languages each script offers, which lookups
	 * each feature of each of them runs, and the lookup list itself.
	 *
	 * The reading is shared with OtlDump, which reports the same table in HTML and used to carry its
	 * own copy of every offset in it. What it overrides now is the reporting hooks below.
	 */
	function _getGPOStables()
	{
		if (!isset($this->tables['GPOS'])) {
			$this->reportTableMissing('GPOS');

			return [[], [], []];
		}

		$this->reportTableRead('GPOS');

		$gposOffset = $this->seek_table('GPOS');
		$this->reader->skip(4); // majorVersion, minorVersion
		$scriptListOffset = $gposOffset + $this->reader->readUInt16();
		$featureListOffset = $gposOffset + $this->reader->readUInt16();
		$lookupListOffset = $gposOffset + $this->reader->readUInt16();

		list($GPOSScriptLang, $gpos, $tags) = $this->readScriptsAndFeatures($scriptListOffset, $featureListOffset);
		$this->haskernGPOS = isset($tags['kern']);

		$this->reportScriptList('GPOS', $gpos);

		if (!$this->wantsLookups()) {
			return [$GPOSScriptLang, $gpos, []];
		}

		// Lookup Type 9 is GPOS's Extension Positioning, the indirection a subtable past 64KB is
		// reached through
		$Lookup = $this->readLookupList($lookupListOffset, $gposOffset, 9);

		$this->useGPOSlookups($Lookup, $gposOffset, $gpos);

		return [$GPOSScriptLang, $gpos, $Lookup];
	}

	/**
	 * The ScriptList and FeatureList of a GSUB or GPOS table, which are the same structures in both.
	 *
	 * @param int $scriptListOffset  Absolute, from the start of the file
	 * @param int $featureListOffset Absolute, from the start of the file
	 *
	 * @return array [$scriptLang, $features, $tags]: the languages each script offers as one
	 *               space-separated string, the lookup list indices of every feature of every one of
	 *               them keyed [script][language][tag], and the set of feature tags the table carries
	 */
	private function readScriptsAndFeatures($scriptListOffset, $featureListOffset)
	{
		// ScriptList: which scripts the table speaks for, and where each one's Script table is
		$this->reader->seek($scriptListOffset);
		$scriptCount = $this->reader->readUInt16();
		$scripts = [];
		for ($i = 0; $i < $scriptCount; $i++) {
			$scriptTag = $this->reader->readTag(); // "beng", "deva" etc.
			$scripts[$scriptTag] = $scriptListOffset + $this->reader->readUInt16();
		}

		// Script table: the language systems of one script, the default one first if it has one
		foreach ($scripts as $scriptTag => $scriptOffset) {
			$languages = [];
			$this->reader->seek($scriptOffset);
			$defaultLangSysOffset = $this->reader->readUInt16();
			if ($defaultLangSysOffset > 0) {
				$languages['DFLT'] = $scriptOffset + $defaultLangSysOffset;
			}
			$langSysCount = $this->reader->readUInt16();
			for ($i = 0; $i < $langSysCount; $i++) {
				$langTag = $this->reader->readTag();
				$languages[$langTag] = $scriptOffset + $this->reader->readUInt16();
			}
			$scripts[$scriptTag] = $languages;
		}

		// LangSys table: the features one language system asks for, as indices into the FeatureList
		foreach ($scripts as $scriptTag => $languages) {
			foreach ($languages as $langTag => $langSysOffset) {
				$featureIndices = [];
				$this->reader->seek($langSysOffset);
				$this->reader->readUInt16(); // lookupOrderOffset, reserved and always NULL
				$requiredFeatureIndex = $this->reader->readUInt16();
				if ($requiredFeatureIndex != 0xFFFF) {
					$featureIndices[] = $requiredFeatureIndex;
				}
				$featureIndexCount = $this->reader->readUInt16();
				for ($i = 0; $i < $featureIndexCount; $i++) {
					$featureIndices[] = $this->reader->readUInt16();
				}
				$scripts[$scriptTag][$langTag] = $featureIndices;
			}
		}

		// FeatureList: every feature the table carries, and the lookups each one runs
		$this->reader->seek($featureListOffset);
		$featureCount = $this->reader->readUInt16();
		$features = [];
		$tags = [];
		for ($i = 0; $i < $featureCount; $i++) {
			$tag = $this->reader->readTag();
			$tags[$tag] = true;
			$features[$i] = ['tag' => $tag, 'offset' => $featureListOffset + $this->reader->readUInt16()];
		}

		for ($i = 0; $i < $featureCount; $i++) {
			$this->reader->seek($features[$i]['offset']);
			$this->reader->readUInt16(); // featureParamsOffset, NULL for all but a handful of features
			$features[$i]['LookupCount'] = $lookupIndexCount = $this->reader->readUInt16();
			$features[$i]['LookupListIndex'] = [];
			for ($c = 0; $c < $lookupIndexCount; $c++) {
				$features[$i]['LookupListIndex'][] = $this->reader->readUInt16();
			}
		}

		$table = [];
		$scriptLang = [];
		foreach ($scripts as $scriptTag => $languages) {
			foreach ($languages as $langTag => $featureIndices) {
				$byFirstLookup = [];
				foreach ($featureIndices as $featureIndex) {
					$feature = $features[$featureIndex];
					// A feature that runs no lookups has nothing to be ordered by and nothing to do. The
					// spec permits one and fonts in the wild carry one - Sedan SC's 'smcp' lists no
					// lookups at all - so dropping it is the whole of what is right to do with it: keying
					// the row by the lookup it has not got put it under '', which ksort() then ordered
					// ahead of every real lookup index.
					if (isset($feature['LookupListIndex'][0])) {
						$byFirstLookup[$feature['LookupListIndex'][0]] = $feature;
					}
				}

				// The order the lookups need to be run in is the order the Lookup table lists them,
				// not the order the features were asked for
				ksort($byFirstLookup);
				foreach ($byFirstLookup as $feature) {
					$table[$scriptTag][$langTag][$feature['tag']] = $feature['LookupListIndex'];
				}

				if (!isset($scriptLang[$scriptTag])) {
					$scriptLang[$scriptTag] = '';
				}
				$scriptLang[$scriptTag] .= $langTag . ' ';
			}
		}

		return [$scriptLang, $table, $tags];
	}

	/**
	 * The metadata and subtable offsets of a whole GSUB or GPOS LookupList.
	 *
	 * @param int $lookupListOffset Absolute, from the start of the file
	 * @param int $tableOffset      Where GSUB or GPOS starts, which the subtable offsets come back
	 *                              relative to: the shaper reads a copy of the one table and has no
	 *                              idea where in the file it came from
	 * @param int $extensionType    The lookup type that means "the real subtable is elsewhere" - 7 in
	 *                              GSUB, 9 in GPOS
	 *
	 * @return array Keyed by lookup index, each with Type, Flag, SubtableCount, Subtables and
	 *               MarkFilteringSet
	 */
	private function readLookupList($lookupListOffset, $tableOffset, $extensionType)
	{
		$this->reader->seek($lookupListOffset);
		$lookupCount = $this->reader->readUInt16();

		$offsets = [];
		for ($i = 0; $i < $lookupCount; $i++) {
			$offsets[$i] = $lookupListOffset + $this->reader->readUInt16();
		}

		$lookups = [];
		for ($i = 0; $i < $lookupCount; $i++) {
			$this->reader->seek($offsets[$i]);
			$lookups[$i]['Type'] = $this->reader->readUInt16();
			$lookups[$i]['Flag'] = $flag = $this->reader->readUInt16();
			$lookups[$i]['SubtableCount'] = $subtableCount = $this->reader->readUInt16();

			for ($c = 0; $c < $subtableCount; $c++) {
				// Offset16 from the start of this Lookup table, stored relative to the start of the
				// GSUB or GPOS table it belongs to
				$lookups[$i]['Subtables'][$c] = $offsets[$i] + $this->reader->readUInt16() - $tableOffset;
			}

			// MarkFilteringSet = index (base 0) into the GDEF mark glyph sets structure
			$lookups[$i]['MarkFilteringSet'] = ($flag & 0x0010) === 0x0010 ? $this->reader->readUInt16() : '';

			if ($lookups[$i]['Type'] == $extensionType) {
				// Each subtable is really a stub naming the type and holding an Offset32 to the real
				// one. Overwrite both: the offset is measured from the start of the stub, so adding it
				// to a table-relative offset leaves the result table-relative. The type comes back
				// once per subtable and the spec requires them all to agree, so the last one read
				// stands for the lookup - and a lookup with no subtables keeps the type it had rather
				// than whatever the lookup before it resolved to, which is what the two copies of this
				// did before they became one.
				for ($c = 0; $c < $subtableCount; $c++) {
					$this->reader->seek($tableOffset + $lookups[$i]['Subtables'][$c]);
					$this->reader->readUInt16(); // extensionLookupFormat, always 1
					$lookups[$i]['Type'] = $this->reader->readUInt16();
					$lookups[$i]['Subtables'][$c] += $this->reader->readUInt32();
				}
			}
		}

		return $lookups;
	}

	/**
	 * The same lookup list with its subtable offsets measured from the start of the file rather than
	 * from the start of the table they belong to.
	 *
	 * readLookupList() stores them table-relative because the shaper reads a cached copy of the one
	 * table and has no idea where in the file it came from. Anything still holding the file open -
	 * the rule readers below, and the dump's reporters - wants them absolute, and that is the whole
	 * of the difference between the two.
	 */
	protected function absoluteSubtables(array $lookups, $tableOffset)
	{
		foreach ($lookups as $i => $lookup) {
			foreach ($lookup['Subtables'] as $c => $offset) {
				$lookups[$i]['Subtables'][$c] = $tableOffset + $offset;
			}
		}

		return $lookups;
	}

	/**
	 * What the parser does with a GPOS lookup list once it has read it: work out which glyphs each
	 * subtable could match on, and cache that for the shaper.
	 *
	 * OtlDump overrides this to report the lookups instead. It is a hook rather than code in
	 * _getGPOStables() because it is the whole of the difference between the two: everything above it
	 * is reading, and reading is what the two are meant not to disagree about.
	 */
	protected function useGPOSlookups(array $Lookup, $gposOffset, array $features)
	{
		$this->LuCoverage = [];
		foreach ($Lookup as $i => $lookup) {
			for ($c = 0; $c < $lookup['SubtableCount']; $c++) {
				$subtableOffset = $gposOffset + $lookup['Subtables'][$c];
				$this->reader->seek($subtableOffset);
				$posFormat = $this->reader->readUInt16();

				if ($lookup['Type'] == 7 && $posFormat == 3) {
					$this->reader->skip(4); // glyphCount, seqLookupCount
				} elseif ($lookup['Type'] == 8 && $posFormat == 3) {
					$backtrackGlyphCount = $this->reader->readUInt16();
					$this->reader->skip(2 * $backtrackGlyphCount + 2); // backtrackCoverageOffsets, inputGlyphCount
				}

				// Reading position 0's Coverage is the whole of what the gate needs. The shaper offers a
				// subtable the glyph it is standing on and asks whether a match could start there, and every
				// format puts the Coverage of the first position right here: straight after the format for
				// types 1 to 6 and for formats 1 and 2 of types 7 and 8, and after the counts and backtrack
				// offsets stepped over above for format 3. For types 4, 5 and 6 that Coverage is the mark's,
				// which is the right gate - those lookups are applied standing on the mark.
				$this->reader->seek($subtableOffset + $this->reader->readUInt16());
				$this->LuCoverage[$i][$c] = $this->_getCoverage(false, 2);
			}
		}

		$this->fontCache->jsonWrite($this->fontkey . '.GPOSdata.json', $this->LuCoverage);
	}

	/**
	 * What a Multiple Substitution puts in place of the glyph it covers.
	 *
	 * A Sequence of no glyphs is how a font deletes one, and is legal: the spec allows it, HarfBuzz
	 * honours it, and since #110 so does the shaper, which reads it from GSUBLookups rather than from
	 * here. The parser passes over it because 'subs' feeds _getGSUBarray(), whose derived tables -
	 * rtlSUB and finals for Arabic and Syriac joining, rphf/half/pref/blwf/pstf for Indic, rtlPUAstr
	 * for magic_reverse - have no way to spell "and nothing in its place", and whose readers drop an
	 * empty replacement anyway. Recording one here would push it into a regex-driven path that no
	 * font in the corpus can demonstrate the result of. OtlDump reports it, because a report that
	 * omitted a rule the font carries would be lying about the font.
	 *
	 * @return array|null The replacement glyphs as hex, or null to record nothing for this one
	 */
	protected function multipleSubstitutes(array $sequence)
	{
		if (!$sequence) {
			return null;
		}

		$substitute = [];
		foreach ($sequence as $sub) {
			$substitute[] = GlyphString::of($this->glyphToChar[$sub][0]);
		}

		return $substitute;
	}

	/**
	 * One replacement glyph, as the character the shaper names it by.
	 *
	 * Every glyph of a font read with useOTL has a character: the ones the cmap does not reach are
	 * mapped into the Private Use Area. So the only glyph id without one is a glyph the font has not
	 * got, which Single, Ligature, Alternate and Reverse Chaining Substitution can all name.
	 *
	 * @param int $gid The glyph the rule replaces its match with
	 *
	 * @return array|null It as hex in a list of one, or null to record nothing for this one
	 */
	private function substituteGlyph($gid)
	{
		if (!isset($this->glyphToChar[$gid][0])) {
			return null;
		}

		return [GlyphString::of($this->glyphToChar[$gid][0])];
	}

	/**
	 * What an Alternate Substitution puts in place of the glyph it covers.
	 *
	 * The parser takes the first alternate and stops: mPDF has no way for a document to ask for the
	 * others, so a rule it cannot reach is a rule it does not need. OtlDump reports all of them,
	 * which is most of what makes an `aalt` lookup worth looking at.
	 *
	 * @return array|null The replacement glyph as hex in a list of one, or null where the font's
	 *                    first alternate is a glyph the cmap does not reach
	 */
	protected function alternateSubstitutes(array $alternateSet)
	{
		return $this->substituteGlyph($alternateSet['SubstituteGlyphID'][0]);
	}

	/**
	 * Whether the caller wants the lookup list read at all.
	 *
	 * The parser always does - the lookups are what it is for. OtlDump's summary mode stops at the
	 * scripts and languages, which is the whole of what that page reports.
	 */
	protected function wantsLookups()
	{
		return true;
	}

	/**
	 * Reporting hooks. The parser reads silently; OtlDump writes HTML as it goes, and these are where
	 * it gets to. @see OtlDump
	 */
	protected function reportTableRead($tag)
	{
	}

	/**
	 * @param string $tag A layout table this font does not have, 'GSUB' or 'GPOS'
	 */
	protected function reportTableMissing($tag)
	{
	}

	/**
	 * @param string $tag      The layout table being read, 'GSUB' or 'GPOS'
	 * @param array  $features Its scripts, each language system under them, and the feature tags that
	 *                         language system asks for
	 */
	protected function reportScriptList($tag, array $features)
	{
	}

	/**
	 * The width of every character the font maps, and the width to draw one it does not.
	 *
	 * @param int   $maxUniChar The highest character the cmap reached, which sizes the width table
	 *
	 * @return array [$charWidths, $defaultWidth]
	 */
	function getHMTX($numberOfHMetrics, $numGlyphs, &$glyphToChar, $scale, $maxUniChar)
	{
		$start = $this->seek_table('hmtx');
		$aw = 0;
		$defaultWidth = 0;
		$charWidths = str_pad('', 256 * 256 * 2, "\x00");

		if ($maxUniChar > 65536) {
			$charWidths .= str_pad('', 256 * 256 * 2, "\x00");
		} // Plane 1 SMP

		if ($maxUniChar > 131072) {
			$charWidths .= str_pad('', 256 * 256 * 2, "\x00");
		} // Plane 2 SMP

		$nCharWidths = 0;
		if (($numberOfHMetrics * 4) < $this->maxStrLenRead) {
			$data = $this->reader->bytesAt($start, $numberOfHMetrics * 4);
			$arr = unpack('n*', $data);
		} else {
			$this->reader->seek($start);
		}

		for ($glyph = 0; $glyph < $numberOfHMetrics; $glyph++) {

			if (($numberOfHMetrics * 4) < $this->maxStrLenRead) {
				$aw = $arr[($glyph * 2) + 1];
			} else {
				$aw = $this->reader->readUInt16();
				$lsb = $this->reader->readUInt16();
			}
			if (isset($glyphToChar[$glyph]) || $glyph == 0) {
				if ($aw >= (1 << 15)) {
					$aw = 0;
				}

				// 1.03 Some (arabic) fonts have -ve values for width
				// although should be unsigned value - comes out as e.g. 65108 (intended -50)
				if ($glyph === 0) {
					$defaultWidth = $scale * $aw;
					continue;
				}

				foreach ($glyphToChar[$glyph] as $char) {
					if ($char != 0 && $char != 65535) {
						$w = (int) round($scale * $aw);
						if ($w === 0) {
							$w = 65535;
						}
						if ($char < 196608) {
							$charWidths[$char * 2] = chr($w >> 8);
							$charWidths[$char * 2 + 1] = chr($w & 0xFF);
							$nCharWidths++;
						}
					}
				}
			}
		}

		$data = $this->reader->bytesAt(($start + $numberOfHMetrics * 4), ($numGlyphs * 2));
		$arr = unpack("n*", $data);
		$diff = $numGlyphs - $numberOfHMetrics;
		$w = (int) round($scale * $aw);
		if ($w === 0) {
			$w = 65535;
		}
		for ($pos = 0; $pos < $diff; $pos++) {
			$glyph = $pos + $numberOfHMetrics;
			if (isset($glyphToChar[$glyph])) {
				foreach ($glyphToChar[$glyph] as $char) {
					if ($char != 0 && $char != 65535) {
						if ($char < 196608) {
							$charWidths[$char * 2] = chr($w >> 8);
							$charWidths[$char * 2 + 1] = chr($w & 0xFF);
							$nCharWidths++;
						}
					}
				}
			}
		}

		// NB 65535 is a set width of 0
		// First bytes define number of chars in font, a two byte field the filter above can outrun
		$nCharWidths = min($nCharWidths, 0xFFFF);

		$charWidths[0] = chr($nCharWidths >> 8);
		$charWidths[1] = chr($nCharWidths & 0xFF);

		return [$charWidths, $defaultWidth];
	}

	/**
	 * CMAP Format 4
	 *
	 * @return int The highest character the subtable covers, which getHMTX sizes its width table from
	 */
	function getCMAP4($unicode_cmap_offset, &$glyphToChar, &$charToGlyph)
	{
		$maxUniChar = 0;
		$this->reader->seek($unicode_cmap_offset + 2);
		$length = $this->reader->readUInt16();
		$limit = $unicode_cmap_offset + $length;
		$this->reader->skip(2); // language

		$segCount = $this->reader->readUInt16() / 2;
		$this->reader->skip(6); // searchRange, entrySelector, rangeShift
		$endCount = [];

		for ($i = 0; $i < $segCount; $i++) {
			$endCount[] = $this->reader->readUInt16();
		}

		$this->reader->skip(2); // reservedPad
		$startCount = [];

		for ($i = 0; $i < $segCount; $i++) {
			$startCount[] = $this->reader->readUInt16();
		}

		$idDelta = [];

		for ($i = 0; $i < $segCount; $i++) {
			$idDelta[] = $this->reader->readInt16();
		}  // ???? was unsigned short

		$idRangeOffset_start = $this->reader->tell();
		$idRangeOffset = [];

		for ($i = 0; $i < $segCount; $i++) {
			$idRangeOffset[] = $this->reader->readUInt16();
		}

		for ($n = 0; $n < $segCount; $n++) {
			$endpoint = ($endCount[$n] + 1);
			for ($unichar = $startCount[$n]; $unichar < $endpoint; $unichar++) {
				if ($idRangeOffset[$n] == 0) {
					$glyph = ($unichar + $idDelta[$n]) & 0xFFFF;
				} else {
					$offset = ($unichar - $startCount[$n]) * 2 + $idRangeOffset[$n];
					$offset = $idRangeOffset_start + 2 * $n + $offset;
					if ($offset >= $limit) {
						$glyph = 0;
					} else {
						$glyph = $this->reader->uint16At($offset);
						if ($glyph != 0) {
							$glyph = ($glyph + $idDelta[$n]) & 0xFFFF;
						}
					}
				}
				$charToGlyph[$unichar] = $glyph;
				if ($unichar < 196608) {
					$maxUniChar = max($unichar, $maxUniChar);
				}
				$glyphToChar[$glyph][] = $unichar;
			}
		}

		return $maxUniChar;
	}

}
