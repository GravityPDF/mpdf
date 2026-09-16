<?php

namespace Mpdf\Fonts;

use Mpdf\TTFontFile;

// Work out the profile tables - head's bounding box, maxp's point and contour maxima, OS/2's
// character range - from the glyphs the subset actually holds, rather than copying what the original
// font stated. A host can set it before mPDF loads; nothing in mPDF sets it.
if (!defined('_RECALC_PROFILE')) {
	define('_RECALC_PROFILE', false);
}

/**
 * Builds a font program for embedding: a subset of a font, or the whole of one repackaged.
 *
 * The other half of what TTFontFile used to do. It reads a font - the table directory, the metrics,
 * GDEF/GSUB/GPOS - and it built one, and the two have nothing to say to each other: no byte-writing
 * primitive was ever called from the metrics or the OTL path, and nothing here has ever looked at a
 * layout table. #81 moved the reading onto a contract and left this behind.
 *
 * Its only consumer is Writer\FontWriter, which passes the bytes straight into the PDF. Fonts\
 * rather than Writer\, because every Writer\* class serialises PDF objects and takes an Mpdf; this
 * builds a font program, takes a file path, and knows nothing about the document.
 *
 * It takes a TTFontFile rather than being one. What it borrows is the table directory and the two
 * readers built on it that the metrics path also uses - seek_table, get_table_pos, getCMAP4 and
 * getHMTX - so the parser is handed the same open file and asked for those; everything else here is
 * its own. The borrowed readers hand back what they read, so nothing the subsetter does shows up on
 * the parser afterwards.
 */
class FontSubsetter
{

	/**
	 * The parser, for the table directory and the readers shared with the metrics path
	 *
	 * @var TTFontFile
	 */
	private $font;

	/**
	 * The font file being read, which is the parser's reader too
	 *
	 * @var FileReader
	 */
	private $reader;

	/**
	 * The font file being written
	 *
	 * @var TableWriter
	 */
	private $writer;

	/**
	 * Where each glyph starts in the glyf table, by glyph id, with one past the end appended
	 *
	 * @var int[]
	 */
	private $glyphPos;

	/**
	 * What getGlyphs() found in each glyph it walked, by glyph id: the components a compound glyph
	 * is built from, and the contour and point counts a simple one has
	 *
	 * @var array[]
	 */
	private $glyphdata;

	/**
	 * The highest character the subset covers. FontWriter bounds the PDF widths array with it.
	 *
	 * @var int
	 */
	public $maxUni;

	/**
	 * Unicode to the glyph id it has in the subset. FontWriter writes it out as the CIDToGIDMap.
	 *
	 * @var int[]
	 */
	public $codeToGlyph;

	/**
	 * The width a character the subset has no width of its own for is drawn at. FontWriter asks for
	 * it per character.
	 *
	 * @var float
	 */
	public $defaultWidth;

	/**
	 * The highest character the subset covers, counting past the Basic Multilingual Plane.
	 *
	 * Worked out by whichever cmap reader ran - the parser's getCMAP4, or makeSubsetSIP's own format
	 * 12 reader - and then raised by every glyph mapped into the Private Use Area. getHMTX sizes the
	 * width table from it, so there is one of it rather than one per half.
	 *
	 * @var int
	 */
	public $maxUniChar;

	/**
	 * @param TTFontFile $font The parser to borrow the table directory and the shared readers from
	 */
	public function __construct(TTFontFile $font)
	{
		$this->font = $font;
	}

	/**
	 * Start reading one font, and start a font program to write.
	 *
	 * The three builders below opened the file and reset the same state in the same twenty lines
	 * each. The parser is handed the reader so that the table directory it reads, and the two
	 * readers built on it that this borrows, are reading the file this is subsetting.
	 */
	private function open($file, $TTCfontID, $debug)
	{
		$this->reader = $this->font->open($file);
		$this->writer = new TableWriter();
		$this->glyphPos = [];
		$this->glyphdata = [];
		$this->maxUni = 0;
		$this->maxUniChar = 0;
		$this->defaultWidth = 0;
		$this->codeToGlyph = [];

		$this->reader->skip(4); // sfntVersion, which getMetrics checks and this does not
		$this->font->selectFont($TTCfontID);
		$this->font->readTableDirectory($debug);
	}

	/**
	 * Where the font's Unicode cmap subtable starts.
	 *
	 * A cmap holds one subtable per platform and encoding, and the one wanted is format 4 - segmented
	 * coverage of the Basic Multilingual Plane - under Microsoft Unicode or under the Unicode
	 * platform, whose encodings are all Unicode. makeSubsetSIP looks for a different one, and looks
	 * for it itself.
	 *
	 * @return int Absolute, from the start of the file
	 *
	 * @throws \Mpdf\Exception\FontException Where the font carries no such subtable, which means it
	 *                                       cannot be embedded as a subset
	 */
	private function seekUnicodeCmap()
	{
		$cmap_offset = $this->font->seek_table('cmap');
		$this->reader->skip(2); // version
		$cmapTableCount = $this->reader->readUInt16();

		for ($i = 0; $i < $cmapTableCount; $i++) {
			$platformID = $this->reader->readUInt16();
			$encodingID = $this->reader->readUInt16();
			$offset = $this->reader->readUInt32();
			$save_pos = $this->reader->tell();

			if (($platformID == 3 && $encodingID == 1) || $platformID == 0) { // Microsoft, Unicode
				if ($this->reader->uint16At($cmap_offset + $offset) == 4) {
					return $cmap_offset + $offset;
				}
			}

			$this->reader->seek($save_pos);
		}

		throw new \Mpdf\Exception\FontException(sprintf(
			'Font "%s" does not have Unicode cmap (platform 3, encoding 1, format 4, or platform 0 [any encoding] format 4)',
			$this->font->filename
		));
	}

	/**
	 * Build a subset holding only the characters the document used, for a font within the Basic
	 * Multilingual Plane.
	 *
	 * The glyphs named are closed over their components first, so a compound glyph keeps the simple
	 * ones it is drawn from, and the tables that index by glyph id - glyf, loca, hmtx, cmap - are
	 * rewritten against the new numbering.
	 *
	 * @param string $file      The font file to read
	 * @param array  $subset    The characters the document used, as Unicode code points
	 * @param int    $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 * @param bool   $debug     Whether to check the font's own tables as they are read
	 * @param bool   $useOTL    Whether the document laid the font out with its OTL tables, which
	 *                          brings the glyphs only a substitution reaches into the subset
	 *
	 * @return string The font program to embed
	 *
	 * @throws \Mpdf\Exception\FontException Where the font will not fit a format 4 cmap: more glyphs
	 *                                       unmapped than the Private Use Area holds, or more
	 *                                       segments than the subtable's length field can state
	 */
	public function makeSubset($file, array $subset, $TTCfontID = 0, $debug = false, $useOTL = false)
	{
		$this->open($file, $TTCfontID, $debug);

		// head - Font header table
		$this->font->seek_table('head');
		$this->reader->skip(50);
		$indexToLocFormat = $this->reader->readUInt16();
		$glyphDataFormat = $this->reader->readUInt16();

		// hhea - Horizontal header table
		$this->font->seek_table('hhea');
		$this->reader->skip(32);
		$metricDataFormat = $this->reader->readUInt16();
		$orignHmetrics = $numberOfHMetrics = $this->reader->readUInt16();

		// maxp - Maximum profile table
		$this->font->seek_table('maxp');
		$this->reader->skip(4);
		$numGlyphs = $this->reader->readUInt16();

		// cmap - Character to glyph index mapping table
		$unicode_cmap_offset = $this->seekUnicodeCmap();

		$glyphToChar = [];
		$charToGlyph = [];
		$this->maxUniChar = $this->font->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph);

		// Map Unmapped glyphs - from $numGlyphs
		if ($useOTL) {
			$bctr = 0xE000;
			for ($gid = 1; $gid < $numGlyphs; $gid++) {
				if (!isset($glyphToChar[$gid])) {
					while (isset($charToGlyph[$bctr])) {
						$bctr++;
					} // Avoid overwriting a glyph already mapped in PUA
					if ($bctr > 0xF8FF) {
						throw new \Mpdf\Exception\FontException($file . " : WARNING - Font cannot map all included glyphs into Private Use Area U+E000 - U+F8FF; cannot use useOTL on this font");
					}
					$glyphToChar[$gid][] = $bctr;
					$charToGlyph[$bctr] = $gid;
					$bctr++;
				}
			}
		}

		// hmtx - Horizontal metrics table. What the subset wants out of it is the default width -
		// glyph 0's advance - which is worked out alongside the per-character widths the metrics
		// path is after and this one has no use for.
		$scale = 1; // not used
		list(, $this->defaultWidth) = $this->font->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale, $this->maxUniChar);

		// loca - Index to location
		$this->getLOCA($indexToLocFormat, $numGlyphs);

		$subsetglyphs = [0 => 0, 1 => 1, 2 => 2];
		$subsetCharToGlyph = [];
		foreach ($subset as $code) {
			if (isset($charToGlyph[$code])) {
				$subsetglyphs[$charToGlyph[$code]] = $code; // Old Glyph ID => Unicode
				$subsetCharToGlyph[$code] = $charToGlyph[$code]; // Unicode to old GlyphID
			}
			$this->maxUni = max($this->maxUni, $code);
		}

		list($start, $dummy) = $this->font->get_table_pos('glyf');

		$glyphSet = [];
		ksort($subsetglyphs);
		$n = 0;
		$fsLastCharIndex = 0; // maximum Unicode index (character code) in this font, according to the cmap subtable for platform ID 3 and platform- specific encoding ID 0 or 1.
		foreach ($subsetglyphs as $originalGlyphIdx => $uni) {
			$fsLastCharIndex = max($fsLastCharIndex, $uni);
			$glyphSet[$originalGlyphIdx] = $n; // old glyphID to new glyphID
			$n++;
		}

		$codeToGlyph = [];
		ksort($subsetCharToGlyph);
		foreach ($subsetCharToGlyph as $uni => $originalGlyphIdx) {
			$codeToGlyph[$uni] = $glyphSet[$originalGlyphIdx];
		}
		$this->codeToGlyph = $codeToGlyph;

		ksort($subsetglyphs);
		foreach ($subsetglyphs as $originalGlyphIdx => $uni) {
			$this->getGlyphs($originalGlyphIdx, $start, $glyphSet, $subsetglyphs);
		}

		$numGlyphs = $numberOfHMetrics = count($subsetglyphs);

		// name - table copied from the original
		// MS spec says that "Platform and encoding ID's in the name table should be consistent with those in the cmap table.
		// If they are not, the font will not load in Windows"
		// Doesn't seem to be a problem?
		$this->writer->add('name', $this->get_table('name'));

		// tables copied from the original
		$tags = ['cvt ', 'fpgm', 'prep', 'gasp'];
		foreach ($tags as $tag) {
			if (isset($this->font->tables[$tag])) {
				$this->writer->add($tag, $this->get_table($tag));
			}
		}

		// post - PostScript
		if (isset($this->font->tables['post'])) {
			$opost = $this->get_table('post');
			$post = "\x00\x03\x00\x00" . substr($opost, 4, 12) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
			$this->writer->add('post', $post);
		}

		// cmap - Character to glyph mapping
		ksort($codeToGlyph);
		unset($codeToGlyph[0]);

		$this->writer->add('cmap', $this->unicodeCmap($codeToGlyph));

		// glyf - Glyph data
		list($glyfOffset, $glyfLength) = $this->font->get_table_pos('glyf');
		if ($glyfLength < $this->font->maxStrLenRead) {
			$glyphData = $this->get_table('glyf');
		}

		$offsets = [];
		$glyf = '';
		$pos = 0;
		$hmtxstr = '';
		$xMinT = 0;
		$yMinT = 0;
		$xMaxT = 0;
		$yMaxT = 0;
		$advanceWidthMax = 0;
		$minLeftSideBearing = 0;
		$minRightSideBearing = 0;
		$xMaxExtent = 0;
		$maxPoints = 0; // points in non-compound glyph
		$maxContours = 0; // contours in non-compound glyph
		$maxComponentPoints = 0; // points in compound glyph
		$maxComponentContours = 0; // contours in compound glyph
		$maxComponentElements = 0; // number of glyphs referenced at top level
		$maxComponentDepth = 0; // levels of recursion, set to 0 if font has only simple glyphs
		$this->glyphdata = [];

		foreach ($subsetglyphs as $originalGlyphIdx => $uni) {
			// hmtx - Horizontal Metrics
			$hm = $this->getHMetric($orignHmetrics, $originalGlyphIdx);
			$hmtxstr .= $hm;

			$offsets[] = $pos;
			$glyphPos = $this->glyphPos[$originalGlyphIdx];
			$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;
			if ($glyfLength < $this->font->maxStrLenRead) {
				$data = substr($glyphData, $glyphPos, $glyphLen);
			} else {
				if ($glyphLen > 0) {
					$data = $this->reader->bytesAt($glyfOffset + $glyphPos, $glyphLen);
				} else {
					$data = '';
				}
			}

			if ($glyphLen > 0) {
				if (_RECALC_PROFILE) {
					$xMin = FontReader::int16(substr($data, 2, 2));
					$yMin = FontReader::int16(substr($data, 4, 2));
					$xMax = FontReader::int16(substr($data, 6, 2));
					$yMax = FontReader::int16(substr($data, 8, 2));
					$xMinT = min($xMinT, $xMin);
					$yMinT = min($yMinT, $yMin);
					$xMaxT = max($xMaxT, $xMax);
					$yMaxT = max($yMaxT, $yMax);
					$aw = FontReader::int16(substr($hm, 0, 2));
					$lsb = FontReader::int16(substr($hm, 2, 2));
					$advanceWidthMax = max($advanceWidthMax, $aw);
					$minLeftSideBearing = min($minLeftSideBearing, $lsb);
					$minRightSideBearing = min($minRightSideBearing, ($aw - $lsb - ($xMax - $xMin)));
					$xMaxExtent = max($xMaxExtent, ($lsb + ($xMax - $xMin)));
				}
				$up = unpack("n", substr($data, 0, 2));
			}
			if ($glyphLen > 2 && ($up[1] & (1 << 15))) { // If number of contours <= -1 i.e. composiste glyph
				$pos_in_glyph = 10;
				$flags = GlyphOperator::MORE;
				$nComponentElements = 0;
				while ($flags & GlyphOperator::MORE) {
					$nComponentElements += 1; // number of glyphs referenced at top level
					$up = unpack("n", substr($data, $pos_in_glyph, 2));
					$flags = $up[1];
					$up = unpack("n", substr($data, $pos_in_glyph + 2, 2));
					$glyphIdx = $up[1];
					$this->glyphdata[$originalGlyphIdx]['compGlyphs'][] = $glyphIdx;
					$data = TableWriter::setUInt16($data, $pos_in_glyph + 2, $glyphSet[$glyphIdx]);
					$pos_in_glyph += 4;
					if ($flags & GlyphOperator::WORDS) {
						$pos_in_glyph += 4;
					} else {
						$pos_in_glyph += 2;
					}
					if ($flags & GlyphOperator::SCALE) {
						$pos_in_glyph += 2;
					} elseif ($flags & GlyphOperator::XYSCALE) {
						$pos_in_glyph += 4;
					} elseif ($flags & GlyphOperator::TWOBYTWO) {
						$pos_in_glyph += 8;
					}
				}
				$maxComponentElements = max($maxComponentElements, $nComponentElements);

			} // Simple Glyph
			elseif (_RECALC_PROFILE && $glyphLen > 2 && $up[1] < (1 << 15) && $up[1] > 0) {  // Number of contours > 0 simple glyph
				$nContours = $up[1];
				$this->glyphdata[$originalGlyphIdx]['nContours'] = $nContours;
				$maxContours = max($maxContours, $nContours);

				// Count number of points in simple glyph
				$pos_in_glyph = 10 + ($nContours * 2) - 2; // Last endContourPoint
				$up = unpack("n", substr($data, $pos_in_glyph, 2));
				$points = $up[1] + 1;
				$this->glyphdata[$originalGlyphIdx]['nPoints'] = $points;
				$maxPoints = max($maxPoints, $points);
			}

			$glyf .= $data;
			$pos += $glyphLen;
			if ($pos % 4 != 0) {
				$padding = 4 - ($pos % 4);
				$glyf .= str_repeat("\0", $padding);
				$pos += $padding;
			}
		}

		if (_RECALC_PROFILE) {
			foreach ($this->glyphdata as $originalGlyphIdx => $val) {
				$maxdepth = $depth = -1;
				$points = 0;
				$contours = 0;
				$this->getGlyphData($originalGlyphIdx, $maxdepth, $depth, $points, $contours);
				$maxComponentDepth = max($maxComponentDepth, $maxdepth);
				$maxComponentPoints = max($maxComponentPoints, $points);
				$maxComponentContours = max($maxComponentContours, $contours);
			}
		}

		$offsets[] = $pos;
		$this->writer->add('glyf', $glyf);

		// hmtx - Horizontal Metrics
		$this->writer->add('hmtx', $hmtxstr);

		// loca - Index to location
		$locastr = '';
		if ((($pos + 1) >> 1) > 0xFFFF) {
			$indexToLocFormat = 1; // long format
			foreach ($offsets as $offset) {
				$locastr .= TableWriter::uint32($offset);
			}
		} else {
			$indexToLocFormat = 0; // short format
			foreach ($offsets as $offset) {
				$locastr .= TableWriter::uint16($offset / 2);
			}
		}
		$this->writer->add('loca', $locastr);

		// head - Font header
		$head = $this->get_table('head');
		$head = TableWriter::setUInt16($head, 50, $indexToLocFormat);

		if (_RECALC_PROFILE) {
			$head = TableWriter::setInt16($head, 36, $xMinT); // for all glyph bounding boxes
			$head = TableWriter::setInt16($head, 38, $yMinT); // for all glyph bounding boxes
			$head = TableWriter::setInt16($head, 40, $xMaxT); // for all glyph bounding boxes
			$head = TableWriter::setInt16($head, 42, $yMaxT); // for all glyph bounding boxes
			// Unset flags bit 4, which says the font has hdmx and LTSH tables, because this does not.
			// Written through ord(): the & was applied to the one-character string, which is a TypeError
			// from PHP 8 - this whole branch is off unless the host defines _RECALC_PROFILE.
			$head[17] = chr(ord($head[17]) & ~(1 << 4));
		}

		$this->writer->add('head', $head);

		// hhea - Horizontal Header
		$hhea = $this->get_table('hhea');
		$hhea = TableWriter::setUInt16($hhea, 34, $numberOfHMetrics);
		if (_RECALC_PROFILE) {
			$hhea = TableWriter::setUInt16($hhea, 10, $advanceWidthMax);
			$hhea = TableWriter::setInt16($hhea, 12, $minLeftSideBearing);
			$hhea = TableWriter::setInt16($hhea, 14, $minRightSideBearing);
			$hhea = TableWriter::setInt16($hhea, 16, $xMaxExtent);
		}
		$this->writer->add('hhea', $hhea);

		// maxp - Maximum Profile
		$maxp = $this->get_table('maxp');
		$maxp = TableWriter::setUInt16($maxp, 4, $numGlyphs);
		if (_RECALC_PROFILE) {
			$maxp = TableWriter::setUInt16($maxp, 6, $maxPoints); // points in non-compound glyph
			$maxp = TableWriter::setUInt16($maxp, 8, $maxContours); // contours in non-compound glyph
			$maxp = TableWriter::setUInt16($maxp, 10, $maxComponentPoints); // points in compound glyph
			$maxp = TableWriter::setUInt16($maxp, 12, $maxComponentContours); // contours in compound glyph
			$maxp = TableWriter::setUInt16($maxp, 28, $maxComponentElements); // number of glyphs referenced at top level
			$maxp = TableWriter::setUInt16($maxp, 30, $maxComponentDepth); // levels of recursion, set to 0 if font has only simple glyphs
		}
		$this->writer->add('maxp', $maxp);

		// OS/2 - OS/2
		if (isset($this->font->tables['OS/2'])) {
			$os2_offset = $this->font->seek_table("OS/2");
			if (_RECALC_PROFILE) {
				$fsSelection = $this->reader->uint16At($os2_offset + 62);
				$fsSelection = ($fsSelection & ~(1 << 6)); // 2-byte bit field containing information concerning the nature of the font patterns
				// bit#0 = Italic; bit#5=Bold
				// Match name table's font subfamily string
				// Clear bit#6 used for 'Regular' and optional
			}

			// NB Currently this method never subsets characters above BMP
			// Could set nonBMP bit according to $this->maxUni
			$nonBMP = $this->reader->uint16At($os2_offset + 46);
			$nonBMP = ($nonBMP & ~(1 << 9)); // Unset Bit 57 (indicates non-BMP) - for interactive forms

			$os2 = $this->get_table('OS/2');
			if (_RECALC_PROFILE) {
				$os2 = TableWriter::setUInt16($os2, 62, $fsSelection);
				$os2 = TableWriter::setUInt16($os2, 66, $fsLastCharIndex);
				$os2 = TableWriter::setUInt16($os2, 42, 0x0000); // ulCharRange (ulUnicodeRange) bits 24-31 | 16-23
				$os2 = TableWriter::setUInt16($os2, 44, 0x0000); // ulCharRange (Unicode ranges) bits  8-15 |  0-7
				$os2 = TableWriter::setUInt16($os2, 46, $nonBMP); // ulCharRange (Unicode ranges) bits 56-63 | 48-55
				$os2 = TableWriter::setUInt16($os2, 48, 0x0000); // ulCharRange (Unicode ranges) bits 40-47 | 32-39
				$os2 = TableWriter::setUInt16($os2, 50, 0x0000); // ulCharRange (Unicode ranges) bits  88-95 | 80-87
				$os2 = TableWriter::setUInt16($os2, 52, 0x0000); // ulCharRange (Unicode ranges) bits  72-79 | 64-71
				$os2 = TableWriter::setUInt16($os2, 54, 0x0000); // ulCharRange (Unicode ranges) bits  120-127 | 112-119
				$os2 = TableWriter::setUInt16($os2, 56, 0x0000); // ulCharRange (Unicode ranges) bits  104-111 | 96-103
			}
			$os2 = TableWriter::setUInt16($os2, 46, $nonBMP); // Unset Bit 57 (indicates non-BMP) - for interactive forms

			$this->writer->add('OS/2', $os2);
		}

		$this->reader->close();

		// Put the TTF file together
		return $this->writer->program();
	}

	/**
	 * Build a subset for a font whose characters run past the Basic Multilingual Plane.
	 *
	 * The same job as makeSubset, against a format 12 cmap rather than a format 4 one, and reading
	 * that cmap itself rather than through the parser - the parser's reader answers only format 4.
	 *
	 * @param string $file      The font file to read
	 * @param array  $subset    The characters the document used, as Unicode code points
	 * @param int    $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 * @param bool   $debug     Whether to check the font's own tables as they are read
	 * @param int    $useOTL    Whether the document laid the font out with its OTL tables
	 *
	 * @return string The font program to embed
	 *
	 * @throws \Mpdf\Exception\FontException Where the font carries no Unicode cmap to read, or subsets
	 *                                       into more segments than the format 4 subtable's length
	 *                                       field can state
	 */
	public function makeSubsetSIP($file, array $subset, $TTCfontID = 0, $debug = false, $useOTL = 0)
	{
		$this->open($file, $TTCfontID, $debug);

		// head - Font header table
		$this->font->seek_table('head');
		$this->reader->skip(50);
		$indexToLocFormat = $this->reader->readUInt16();
		$glyphDataFormat = $this->reader->readUInt16();

		// hhea - Horizontal header table
		$this->font->seek_table('hhea');
		$this->reader->skip(32);
		$metricDataFormat = $this->reader->readUInt16();
		$orignHmetrics = $numberOfHMetrics = $this->reader->readUInt16();

		// maxp - Maximum profile table
		$this->font->seek_table('maxp');
		$this->reader->skip(4);
		$numGlyphs = $this->reader->readUInt16();

		// cmap - Character to glyph index mapping table
		$cmap_offset = $this->font->seek_table('cmap');
		$this->reader->skip(2);
		$cmapTableCount = $this->reader->readUInt16();
		$unicode_cmap_offset = 0;
		for ($i = 0; $i < $cmapTableCount; $i++) {

			$platformID = $this->reader->readUInt16();
			$encodingID = $this->reader->readUInt16();
			$offset = $this->reader->readUInt32();
			$save_pos = $this->reader->tell();

			if (($platformID == 3 && $encodingID == 10) || $platformID == 0) { // Microsoft, Unicode Format 12 table HKCS
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 12) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}

			if (($platformID == 3 && $encodingID == 1) || $platformID == 0) { // Microsoft, Unicode
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 4) {
					$unicode_cmap_offset = $cmap_offset + $offset;
				}
			}

			$this->reader->seek($save_pos);
		}

		if (!$unicode_cmap_offset) {
			throw new \Mpdf\Exception\FontException(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $file));
		}

		// Format 12 CMAP does characters above Unicode BMP i.e. some HKCS characters U+20000 and above
		if ($format == 12) {
			$this->maxUniChar = 0;
			$this->reader->seek($unicode_cmap_offset + 4);
			$length = $this->reader->readUInt32();
			$limit = $unicode_cmap_offset + $length;
			$this->reader->skip(4);

			$nGroups = $this->reader->readUInt32();

			$glyphToChar = [];
			$charToGlyph = [];
			for ($i = 0; $i < $nGroups; $i++) {
				$startCharCode = $this->reader->readUInt32();
				$endCharCode = $this->reader->readUInt32();
				$startGlyphCode = $this->reader->readUInt32();
				$offset = 0;
				for ($unichar = $startCharCode; $unichar <= $endCharCode; $unichar++) {
					$glyph = $startGlyphCode + $offset;
					$offset++;
					// ZZZ98
					if ($unichar < 0x30000) {
						$charToGlyph[$unichar] = $glyph;
						$this->maxUniChar = max($unichar, $this->maxUniChar);
						$glyphToChar[$glyph][] = $unichar;
					}
				}
			}
		} else {
			$glyphToChar = [];
			$charToGlyph = [];
			$this->maxUniChar = $this->font->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph);
		}

		// Map Unmapped glyphs - from $numGlyphs
		if ($useOTL) {
			$bctr = 0xE000;
			for ($gid = 1; $gid < $numGlyphs; $gid++) {
				if (!isset($glyphToChar[$gid])) {
					while (isset($charToGlyph[$bctr])) {
						$bctr++;
					} // Avoid overwriting a glyph already mapped in PUA
					// ZZZ98
					if ($bctr > 0xF8FF && $bctr < 0x2CEB0) {
						$bctr = 0x2CEB0;
						while (isset($charToGlyph[$bctr])) {
							$bctr++;
						}
					}
					$glyphToChar[$gid][] = $bctr;
					$charToGlyph[$bctr] = $gid;
					$this->maxUniChar = max($bctr, $this->maxUniChar);
					$bctr++;
				}
			}
		}

		// hmtx - Horizontal metrics table, for the default width; @see makeSubset
		$scale = 1; // not used here
		list(, $this->defaultWidth) = $this->font->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale, $this->maxUniChar);

		// loca - Index to location
		$this->getLOCA($indexToLocFormat, $numGlyphs);

		$glyphMap = [0 => 0];
		$glyphSet = [0 => 0];
		$codeToGlyph = [];

		// Set a substitute if ASCII characters do not have glyphs
		if (isset($charToGlyph[0x3F])) {
			$subs = $charToGlyph[0x3F];
		} else { // Question mark
			$subs = $charToGlyph[32];
		}

		foreach ($subset as $code) {
			if (isset($charToGlyph[$code])) {
				$originalGlyphIdx = $charToGlyph[$code];
			} elseif ($code < 128) {
				$originalGlyphIdx = $subs;
			} else {
				$originalGlyphIdx = 0;
			}
			if (!isset($glyphSet[$originalGlyphIdx])) {
				$glyphSet[$originalGlyphIdx] = count($glyphMap);
				$glyphMap[] = $originalGlyphIdx;
			}
			$codeToGlyph[$code] = $glyphSet[$originalGlyphIdx];
		}

		list($start, $dummy) = $this->font->get_table_pos('glyf');

		$n = 0;
		while ($n < count($glyphMap)) {
			$originalGlyphIdx = $glyphMap[$n];
			$glyphPos = $this->glyphPos[$originalGlyphIdx];
			$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;
			++$n;
			if (!$glyphLen) {
				continue;
			}
			$this->reader->seek($start + $glyphPos);
			$numberOfContours = $this->reader->readInt16();
			if ($numberOfContours < 0) {
				$this->reader->skip(8);
				$flags = GlyphOperator::MORE;
				while ($flags & GlyphOperator::MORE) {
					$flags = $this->reader->readUInt16();
					$glyphIdx = $this->reader->readUInt16();
					if (!isset($glyphSet[$glyphIdx])) {
						$glyphSet[$glyphIdx] = count($glyphMap);
						$glyphMap[] = $glyphIdx;
					}
					if ($flags & GlyphOperator::WORDS) {
						$this->reader->skip(4);
					} else {
						$this->reader->skip(2);
					}
					if ($flags & GlyphOperator::SCALE) {
						$this->reader->skip(2);
					} elseif ($flags & GlyphOperator::XYSCALE) {
						$this->reader->skip(4);
					} elseif ($flags & GlyphOperator::TWOBYTWO) {
						$this->reader->skip(8);
					}
				}
			}
		}

		$numGlyphs = $n = count($glyphMap);
		$numberOfHMetrics = $n;

		// MS spec says that "Platform and encoding ID's in the name table should be consistent with those in the cmap table.
		// If they are not, the font will not load in Windows"
		// Doesn't seem to be a problem?
		// Needs to have a name entry in 3,0 (e.g. symbol) - original font will be 3,1 (i.e. Unicode)
		$name = $this->get_table('name');
		$name_offset = $this->font->seek_table("name");
		$format = $this->reader->readUInt16();
		$numRecords = $this->reader->readUInt16();
		$string_data_offset = $name_offset + $this->reader->readUInt16();
		for ($i = 0; $i < $numRecords; $i++) {
			$platformId = $this->reader->readUInt16();
			$encodingId = $this->reader->readUInt16();
			if ($platformId == 3 && $encodingId == 1) {
				$pos = 6 + ($i * 12) + 2;
				$name = TableWriter::setUInt16($name, $pos, 0x00); // Change encoding to 3,0 rather than 3,1
			}
			$this->reader->skip(8);
		}
		$this->writer->add('name', $name);

		// OS/2
		if (isset($this->font->tables['OS/2'])) {
			$os2 = $this->get_table('OS/2');
			$os2 = TableWriter::setUInt16($os2, 42, 0x00); // ulCharRange (Unicode ranges)
			$os2 = TableWriter::setUInt16($os2, 44, 0x00); // ulCharRange (Unicode ranges)
			$os2 = TableWriter::setUInt16($os2, 46, 0x00); // ulCharRange (Unicode ranges)
			$os2 = TableWriter::setUInt16($os2, 48, 0x00); // ulCharRange (Unicode ranges)

			$os2 = TableWriter::setUInt16($os2, 50, 0x00); // ulCharRange (Unicode ranges)
			$os2 = TableWriter::setUInt16($os2, 52, 0x00); // ulCharRange (Unicode ranges)
			$os2 = TableWriter::setUInt16($os2, 54, 0x00); // ulCharRange (Unicode ranges)
			$os2 = TableWriter::setUInt16($os2, 56, 0x00); // ulCharRange (Unicode ranges)
			// Set Symbol character only in ulCodePageRange
			$os2 = TableWriter::setUInt16($os2, 78, 0x8000); // ulCodePageRange = Bit #31 Symbol ****  78 = Bit 16-31
			$os2 = TableWriter::setUInt16($os2, 80, 0x0000); // ulCodePageRange = Bit #31 Symbol ****  80 = Bit 0-15
			$os2 = TableWriter::setUInt16($os2, 82, 0x0000); // ulCodePageRange = Bit #32- Symbol **** 82 = Bits 48-63
			$os2 = TableWriter::setUInt16($os2, 84, 0x0000); // ulCodePageRange = Bit #32- Symbol **** 84 = Bits 32-47

			$os2 = TableWriter::setUInt16($os2, 64, 0x01); // FirstCharIndex
			$os2 = TableWriter::setUInt16($os2, 66, count($subset)); // LastCharIndex
			// Set PANOSE first bit to 5 for Symbol
			$os2 = TableWriter::replace($os2, 32, chr(5) . chr(0) . chr(1) . chr(0) . chr(1) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0));
			$this->writer->add('OS/2', $os2);
		}

		//tables copied from the original
		$tags = ['cvt ', 'fpgm', 'prep', 'gasp'];
		foreach ($tags as $tag) {  // 1.02
			if (isset($this->font->tables[$tag])) {
				$this->writer->add($tag, $this->get_table($tag));
			}
		}

		// post - PostScript. Written only where the font has one to rewrite, as makeSubset does: a
		// font without one used to get a post table built out of an undefined variable.
		if (isset($this->font->tables['post'])) {
			$opost = $this->get_table('post');
			$post = "\x00\x03\x00\x00" . substr($opost, 4, 12) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
			$this->writer->add('post', $post);
		}

		// hhea - Horizontal Header
		$hhea = $this->get_table('hhea');
		$hhea = TableWriter::setUInt16($hhea, 34, $numberOfHMetrics);
		$this->writer->add('hhea', $hhea);

		// maxp - Maximum Profile
		$maxp = $this->get_table('maxp');
		$maxp = TableWriter::setUInt16($maxp, 4, $numGlyphs);
		$this->writer->add('maxp', $maxp);

		// CMap table Formats [1,0,]6 and [3,0,]4
		$cidToGlyph = [];
		foreach ($subset as $cid => $code) {
			$cidToGlyph[$cid] = $codeToGlyph[$code];
		}

		$cmapstr4 = $this->format4Subtable($cidToGlyph);

		// cmap - Character to glyph mapping
		$format6Length = 10 + 2 * count($subset);

		if ($format6Length > 0xFFFF) {
			// A format 6 subtable states its length in a uint16, so past 32,762 characters it cannot be
			// written. It maps the same codes to the same glyphs as the format 4 subtable, and a reader
			// looks a symbolic TrueType font's codes up in (3,0) before (1,0) (ISO 32000-1, 9.6.6.4),
			// so leave (1,0) out rather than refuse a font the format 4 subtable maps whole.
			$cmapstr = TableWriter::uint16s([0, 1, 3, 0]) . TableWriter::uint32(12) . $cmapstr4;
		} else {
			$cmapstr = TableWriter::uint16s([0, 2, 1, 0]) . TableWriter::uint32(20)
				. TableWriter::uint16s([3, 0]) . TableWriter::uint32(20 + $format6Length)
				. TableWriter::uint16s([6, $format6Length, 0, 1, count($subset)]) // format, length, language, firstCode, entryCount
				. TableWriter::uint16s($cidToGlyph)
				. $cmapstr4;
		}

		$this->writer->add('cmap', $cmapstr);

		// hmtx - Horizontal Metrics
		$hmtxstr = '';
		for ($n = 0; $n < $numGlyphs; $n++) {
			$originalGlyphIdx = $glyphMap[$n];
			$hm = $this->getHMetric($orignHmetrics, $originalGlyphIdx);
			$hmtxstr .= $hm;
		}
		$this->writer->add('hmtx', $hmtxstr);

		// glyf - Glyph data
		list($glyfOffset, $glyfLength) = $this->font->get_table_pos('glyf');
		if ($glyfLength < $this->font->maxStrLenRead) {
			$glyphData = $this->get_table('glyf');
		}

		$offsets = [];
		$glyf = '';
		$pos = 0;
		for ($n = 0; $n < $numGlyphs; $n++) {

			$offsets[] = $pos;
			$originalGlyphIdx = $glyphMap[$n];
			$glyphPos = $this->glyphPos[$originalGlyphIdx];
			$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;

			if ($glyfLength < $this->font->maxStrLenRead) {
				$data = substr($glyphData, $glyphPos, $glyphLen);
			} else {
				if ($glyphLen > 0) {
					$data = $this->reader->bytesAt($glyfOffset + $glyphPos, $glyphLen);
				} else {
					$data = '';
				}
			}

			if ($glyphLen > 0) {
				$up = unpack('n', substr($data, 0, 2));
			}

			if ($glyphLen > 2 && ($up[1] & (1 << 15))) {

				$pos_in_glyph = 10;
				$flags = GlyphOperator::MORE;

				while ($flags & GlyphOperator::MORE) {
					$up = unpack('n', substr($data, $pos_in_glyph, 2));
					$flags = $up[1];
					$up = unpack('n', substr($data, $pos_in_glyph + 2, 2));
					$glyphIdx = $up[1];
					$data = TableWriter::setUInt16($data, $pos_in_glyph + 2, $glyphSet[$glyphIdx]);
					$pos_in_glyph += 4;

					if ($flags & GlyphOperator::WORDS) {
						$pos_in_glyph += 4;
					} else {
						$pos_in_glyph += 2;
					}

					if ($flags & GlyphOperator::SCALE) {
						$pos_in_glyph += 2;
					} elseif ($flags & GlyphOperator::XYSCALE) {
						$pos_in_glyph += 4;
					} elseif ($flags & GlyphOperator::TWOBYTWO) {
						$pos_in_glyph += 8;
					}
				}
			}

			$glyf .= $data;
			$pos += $glyphLen;

			if ($pos % 4 != 0) {
				$padding = 4 - ($pos % 4);
				$glyf .= str_repeat("\0", $padding);
				$pos += $padding;
			}
		}

		$offsets[] = $pos;
		$this->writer->add('glyf', $glyf);

		// loca - Index to location
		$locastr = '';
		if ((($pos + 1) >> 1) > 0xFFFF) {
			$indexToLocFormat = 1;        // long format
			foreach ($offsets as $offset) {
				$locastr .= TableWriter::uint32($offset);
			}
		} else {
			$indexToLocFormat = 0;        // short format
			foreach ($offsets as $offset) {
				$locastr .= TableWriter::uint16($offset / 2);
			}
		}

		$this->writer->add('loca', $locastr);

		// head - Font header
		$head = $this->get_table('head');
		$head = TableWriter::setUInt16($head, 50, $indexToLocFormat);
		$this->writer->add('head', $head);

		$this->reader->close();

		return $this->writer->program();
	}

	/**
	 * Rewrite a whole font, keeping every glyph.
	 *
	 * For the fonts mPDF embeds unsubsetted. Nothing is renumbered, so this is a table directory
	 * rebuilt round the tables a PDF needs, plus a cmap written from the parser's character map.
	 *
	 * @param string $file      The font file to read
	 * @param int    $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 * @param bool   $debug     Whether to check the font's own tables as they are read
	 * @param bool   $useOTL    Whether the document laid the font out with its OTL tables
	 *
	 * @return string The font program to embed
	 *
	 * @throws \Mpdf\Exception\FontException Where the font will not fit a format 4 cmap: more glyphs
	 *                                       unmapped than the Private Use Area holds, or more
	 *                                       segments than the subtable's length field can state
	 */
	public function repackageTTF($file, $TTCfontID = 0, $debug = false, $useOTL = false)
	{
		$this->open($file, $TTCfontID, $debug);
		$tags = ['OS/2', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'name', 'post', 'cvt ', 'fpgm', 'gasp', 'prep'];

		foreach ($tags as $tag) {
			if (isset($this->font->tables[$tag])) {
				$this->writer->add($tag, $this->get_table($tag));
			}
		}

		if ($useOTL) {

			// maxp - Maximum profile table
			$this->font->seek_table('maxp');
			$this->reader->skip(4);
			$numGlyphs = $this->reader->readUInt16();

			// cmap - Character to glyph index mapping table
			$unicode_cmap_offset = $this->seekUnicodeCmap();

			$glyphToChar = [];
			$charToGlyph = [];
			$this->font->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph);

			// Map Unmapped glyphs - from $numGlyphs
			$bctr = 0xE000;
			for ($gid = 1; $gid < $numGlyphs; $gid++) {
				if (!isset($glyphToChar[$gid])) {
					while (isset($charToGlyph[$bctr])) {
						$bctr++;
					} // Avoid overwriting a glyph already mapped in PUA (6,400)
					if ($bctr > 0xF8FF) {
						throw new \Mpdf\Exception\FontException("Problem. Trying to repackage TF file; not enough space for unmapped glyphs");
					}
					$glyphToChar[$gid][] = $bctr;
					$charToGlyph[$bctr] = $gid;
					$bctr++;
				}
			}

			// Sort CID2GID map into segments of contiguous codes
			unset($charToGlyph[65535]);
			unset($charToGlyph[0]);

			ksort($charToGlyph);
			$this->writer->add('cmap', $this->unicodeCmap($charToGlyph));
		} else {
			$this->writer->add('cmap', $this->get_table('cmap'));
		}

		$this->reader->close();
		return $this->writer->program();
	}

	/**
	 * A cmap holding one format 4 subtable, listed under both Unicode encodings and Microsoft's.
	 *
	 * @param int[] $codeToGlyph Glyph id by character code, sorted by code
	 *
	 * @return string The packed cmap table
	 */
	private function unicodeCmap(array $codeToGlyph)
	{
		return TableWriter::uint16s([
			0, 3, // version, number of encoding records
			0, 0, 0, 28, // platform (Unicode), encoding 0, offset
			0, 3, 0, 28, // platform (Unicode), encoding 3, offset
			3, 1, 0, 28, // platform (Microsoft), encoding 1, offset
		]) . $this->format4Subtable($codeToGlyph);
	}

	/**
	 * A format 4 cmap subtable mapping each code to its glyph, with its length field set.
	 *
	 * Codes are segmented in the order given, so the caller sorts them. A run of codes whose glyphs
	 * run on with them is one segment, resolved through idDelta alone: every segment states an
	 * idRangeOffset of 0, so no glyphIdArray follows the segment arrays - nothing could reach one.
	 *
	 * @param int[] $codeToGlyph Glyph id by character code
	 *
	 * @return string The packed subtable
	 *
	 * @throws \Mpdf\Exception\FontException Where the segments take more bytes than the subtable's
	 *                                       uint16 length field can state
	 */
	private function format4Subtable(array $codeToGlyph)
	{
		$endCodes = [];
		$idDeltas = [];
		$start = 0;
		$prevCode = -2;
		$prevGlyph = -1;

		foreach ($codeToGlyph as $code => $glyph) {
			if ($code != ($prevCode + 1) || $glyph != ($prevGlyph + 1)) {
				$start = $code;
				$idDeltas[$start] = $glyph - $code;
			}
			$endCodes[$start] = $code;
			$prevCode = $code;
			$prevGlyph = $glyph;
		}

		$segCount = count($endCodes) + 1; // + 1 for the segment at 0xFFFF the subtable must end with
		$searchRange = 1;
		$entrySelector = 0;

		while ($searchRange * 2 <= $segCount) {
			$searchRange *= 2;
			++$entrySelector;
		}

		$searchRange *= 2;

		// Packed a section at a time, since uint16s copies its argument and the sections of a wide cmap
		// merged into one list would be held several times over
		$subtable = TableWriter::uint16s([4, 0, 0]) // format, length (set below), language
			. TableWriter::uint16s([$segCount * 2, $searchRange, $entrySelector, $segCount * 2 - $searchRange]) // segCountX2, searchRange, entrySelector, rangeShift
			. TableWriter::uint16s($endCodes) . TableWriter::uint16s([0xFFFF, 0]) // endCode, then reservedPad
			. TableWriter::uint16s(array_keys($endCodes)) . TableWriter::uint16s([0xFFFF]) // startCode
			. TableWriter::uint16s($idDeltas) . TableWriter::uint16s([1]) // idDelta
			. str_repeat("\x00\x00", $segCount); // idRangeOffset

		$length = strlen($subtable);

		if ($length > 0xFFFF) {
			throw new \Mpdf\Exception\FontException(sprintf(
				'Font "%s" needs a format 4 cmap subtable of %d bytes, more than its length field can state',
				$this->font->filename,
				$length
			));
		}

		return TableWriter::setUInt16($subtable, 2, $length);
	}

	/**
	 * Read the loca table into $this->glyphPos, so a glyph's bytes can be found in glyf by id.
	 *
	 * loca holds one more entry than there are glyphs: the last says where the final glyph ends.
	 *
	 * @param int $indexToLocFormat 0 for the short format, whose offsets are halved; 1 for the long
	 * @param int $numGlyphs        maxp's glyph count
	 *
	 * @throws \Mpdf\Exception\FontException If head names a format that is neither
	 */
	private function getLOCA($indexToLocFormat, $numGlyphs)
	{
		$start = $this->font->seek_table('loca');
		$this->glyphPos = [];
		if ($indexToLocFormat == 0) {
			$data = $this->reader->bytesAt($start, ($numGlyphs * 2) + 2);
			$arr = unpack("n*", $data);
			for ($n = 0; $n <= $numGlyphs; $n++) {
				$this->glyphPos[] = ($arr[$n + 1] * 2);
			}
		} elseif ($indexToLocFormat == 1) {
			$data = $this->reader->bytesAt($start, ($numGlyphs * 4) + 4);
			$arr = unpack("N*", $data);
			for ($n = 0; $n <= $numGlyphs; $n++) {
				$this->glyphPos[] = ($arr[$n + 1]);
			}
		} else {
			throw new \Mpdf\Exception\FontException('Unknown location table format ' . $indexToLocFormat);
		}
	}

	/**
	 * Add every glyph one glyph is drawn from to the subset, following components all the way down.
	 *
	 * A compound glyph that lost a component would draw as a hole, so the set the caller named is
	 * closed over composition before anything is numbered.
	 *
	 * @param int   $originalGlyphIdx The glyph to walk, by its id in the original font
	 * @param int   $start            Where glyf begins in the file
	 * @param array $glyphSet         Original glyph id to its id in the subset, added to
	 * @param array $subsetglyphs     The original ids the subset holds, added to
	 */
	private function getGlyphs($originalGlyphIdx, &$start, &$glyphSet, &$subsetglyphs)
	{
		$glyphPos = $this->glyphPos[$originalGlyphIdx];
		$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;

		if (!$glyphLen) {
			return;
		}

		$this->reader->seek($start + $glyphPos);
		$numberOfContours = $this->reader->readInt16();

		if ($numberOfContours < 0) {
			$this->reader->skip(8);
			$flags = GlyphOperator::MORE;
			while ($flags & GlyphOperator::MORE) {
				$flags = $this->reader->readUInt16();
				$glyphIdx = $this->reader->readUInt16();
				if (!isset($glyphSet[$glyphIdx])) {
					$glyphSet[$glyphIdx] = count($subsetglyphs); // old glyphID to new glyphID
					$subsetglyphs[$glyphIdx] = true;
				}
				$savepos = $this->reader->tell();
				$this->getGlyphs($glyphIdx, $start, $glyphSet, $subsetglyphs);
				$this->reader->seek($savepos);
				if ($flags & GlyphOperator::WORDS) {
					$this->reader->skip(4);
				} else {
					$this->reader->skip(2);
				}
				if ($flags & GlyphOperator::SCALE) {
					$this->reader->skip(2);
				} elseif ($flags & GlyphOperator::XYSCALE) {
					$this->reader->skip(4);
				} elseif ($flags & GlyphOperator::TWOBYTWO) {
					$this->reader->skip(8);
				}
			}
		}
	}

	/**
	 * Total the points and contours one glyph costs, and how deeply it nests, for maxp's maxima.
	 *
	 * Reads what getGlyphs() recorded rather than the font again, and is called only when
	 * _RECALC_PROFILE asks for the profile to be worked out from the subset.
	 *
	 * @param int $originalGlyphIdx The glyph to total, by its id in the original font
	 * @param int $maxdepth         The deepest composition seen, raised
	 * @param int $depth            How deep this call is, kept across the recursion
	 * @param int $points           Points totalled so far, added to
	 * @param int $contours         Contours totalled so far, added to
	 */
	private function getGlyphData($originalGlyphIdx, &$maxdepth, &$depth, &$points, &$contours)
	{
		$depth++;
		$maxdepth = max($maxdepth, $depth);

		if (count($this->glyphdata[$originalGlyphIdx]['compGlyphs'])) {
			foreach ($this->glyphdata[$originalGlyphIdx]['compGlyphs'] as $glyphIdx) {
				$this->getGlyphData($glyphIdx, $maxdepth, $depth, $points, $contours);
			}
		} elseif (($this->glyphdata[$originalGlyphIdx]['nContours'] > 0) && $depth > 0) { // simple
			$contours += $this->glyphdata[$originalGlyphIdx]['nContours'];
			$points += $this->glyphdata[$originalGlyphIdx]['nPoints'];
		}

		$depth--;
	}

	/**
	 * One glyph's horizontal metrics, as the four bytes hmtx stores them.
	 *
	 * hmtx stops giving advance widths after numberOfHMetrics entries and lists only left side
	 * bearings past that, so a glyph beyond it takes the last advance width stated and its own
	 * bearing.
	 *
	 * @param int $numberOfHMetrics hhea's count of full metric records
	 * @param int $gid              The glyph, by its id in the original font
	 *
	 * @return string Four bytes: advance width then left side bearing
	 */
	private function getHMetric($numberOfHMetrics, $gid)
	{
		$start = $this->font->seek_table("hmtx");
		if ($gid < $numberOfHMetrics) {
			$this->reader->seek($start + ($gid * 4));
			$hm = $this->reader->read(4);
		} else {
			$this->reader->seek($start + (($numberOfHMetrics - 1) * 4));
			$hm = $this->reader->read(2);
			$this->reader->seek($start + ($numberOfHMetrics * 2) + ($gid * 2));
			$hm .= $this->reader->read(2);
		}

		return $hm;
	}

	/**
	 * @return string One whole table from the font's table directory, or '' if it has none
	 */
	private function get_table($tag)
	{
		list($pos, $length) = $this->font->get_table_pos($tag);

		if ($length == 0) {
			return '';
		}

		return $this->reader->bytesAt($pos, $length);
	}

}
