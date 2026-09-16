<?php

namespace Mpdf;

use Mpdf\Fonts\FileReader;
use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\GlyphString;
use Mpdf\Fonts\Table\Anchor;
use Mpdf\Fonts\Table\MarkArray;
use Mpdf\Fonts\Table\SequenceRule;
use Mpdf\Fonts\Table\ValueRecord;

/**
 * A readable report of the OpenType layout tables in a font, for working on OTL support.
 *
 * Extends the parser the renderer uses, rather than being a second copy of it. That is the whole
 * point: a debugging tool that parses independently is free to disagree with the thing it is meant
 * to explain, and is useless exactly when it is needed. What it overrides here is reporting - the
 * four table readers emit HTML as they go - not reading.
 */
class OtlDump extends TTFontFile
{

	/**
	 * Which report to build: 'summary' lists the scripts, languages and features the font offers,
	 * 'detail' reports one script and language system's lookups in full.
	 *
	 * @var string
	 */
	var $mode;

	/**
	 * The script and language whose lookups detail mode reports on.
	 *
	 * These used to be read as $this->mpdf->OTLscript and ->OTLlang. Mpdf declares neither, and it
	 * uses the Strict trait, so every read threw - which is why detail mode has never run. They are
	 * arguments now, because they are arguments.
	 */
	private $script;

	private $language;

	/**
	 * The detail report as it is built, shared by the writer at both levels so that either can hand
	 * over what has built up so far.
	 *
	 * @var string
	 */
	private $report = '';

	/**
	 * How much report to build up before handing it to WriteHTML, a quarter of what it will accept.
	 *
	 * @var int
	 */
	private $reportChunkBytes = 0;

	/**
	 * What the summary report's links should carry to reach a detail report of the same font.
	 *
	 * The summary lists every script and language system a font offers and links each to its own
	 * detail report. Only the caller knows how it named the font it handed over, so it says here,
	 * and the link gets the script and language appended.
	 *
	 * @var array query terms, e.g. ['family' => 'freeserif', 'style' => '']
	 */
	public $detailReportQuery = [];

	/**
	 * What each of GSUB and GPOS had to say when it did not carry the script or language system asked
	 * for. Two entries means neither table did, which is a mistake in the tag rather than a font that
	 * only positions or only substitutes.
	 *
	 * @var string[]
	 */
	private $notOffered = [];

	private $mpdf;

	/**
	 * @param Mpdf      $mpdf           The document the report is written to
	 * @param FontCache $fontCache      Where a parsed font is kept
	 * @param string    $fontDescriptor Which of the font's three sets of vertical metrics to believe
	 */
	public function __construct(Mpdf $mpdf, FontCache $fontCache, $fontDescriptor = 'win')
	{
		parent::__construct($fontCache, $fontDescriptor);

		$this->mpdf = $mpdf;
	}

	/**
	 * @param string $mode     'summary' lists the scripts, languages and features a font offers;
	 *                         'detail' walks the lookups of one script and language
	 * @param string $script   OpenType script tag, e.g. 'deva'. Required by detail mode
	 * @param string $language OpenType language system tag, e.g. 'DFLT'. Required by detail mode
	 */
	public function getMetrics($file, $fontkey, $TTCfontID = 0, $debug = false, $BMPonly = false, $useOTL = 0, $mode = null, $script = '', $language = '')
	{
		if ($mode === 'detail' && (!$script || !$language)) {
			throw new \Mpdf\MpdfException('Dumping the lookups of a font in detail needs a script and a language system to dump');
		}

		$this->mode = $mode;
		$this->script = $script;
		$this->language = $language;
		$this->notOffered = [];
		$this->reportChunkBytes = max(1, (int) ((int) ini_get('pcre.backtrack_limit') / 4));
		$this->useOTL = $useOTL; // mPDF 5.7.1
		$this->fontkey = $fontkey; // mPDF 5.7.1
		$this->filename = $file;
		$this->reader = new FileReader($file);

		$this->charWidths = '';
		$this->charToGlyph = [];
		$this->tables = [];
		$this->kerninfo = [];
		$this->ascent = 0;
		$this->descent = 0;
		$this->numTTCFonts = 0;
		$this->TTCFonts = [];
		$this->version = $version = $this->reader->readUInt32();
		$this->panose = [];

		if ($version == 0x4F54544F) {
			throw new \Mpdf\Exception\FontException(sprintf('Fonts with postscript outlines are not supported (%s)', $file));
		}

		if ($version == 0x74746366 && !$TTCfontID) {
			throw new \Mpdf\Exception\FontException("TTCfontID for a TrueType Collection has to be defined in ttfontdata configuration key (" . $file . ")");
		}

		if (!in_array($version, [0x00010000, 0x74727565]) && !$TTCfontID) {
			throw new \Mpdf\Exception\FontException("Not a TrueType font: version=" . $version);
		}

		if ($TTCfontID > 0) {
			$this->version = $version = $this->reader->readUInt32(); // TTC Header version now
			if (!in_array($version, [0x00010000, 0x00020000])) {
				throw new \Mpdf\Exception\FontException("Error parsing TrueType Collection: version=" . $version . " - " . $file);
			}
			$this->numTTCFonts = $this->reader->readUInt32();
			for ($i = 1; $i <= $this->numTTCFonts; $i++) {
				$this->TTCFonts[$i]['offset'] = $this->reader->readUInt32();
			}
			$this->reader->seek($this->TTCFonts[$TTCfontID]['offset']);
			$this->version = $version = $this->reader->readUInt32(); // TTFont version again now
		}
		$this->readTableDirectory($debug);
		$this->extractInfo($debug, $BMPonly, $useOTL);
		$this->reader->close();
	}

	/**
	 * Read the font's metrics, character map and layout tables, reporting each table as it is read.
	 *
	 * The parser's own extractInfo does the same reading silently; this is that walk with the report
	 * written alongside it.
	 *
	 * @param bool $debug   Whether to check the font's own tables as they are read
	 * @param bool $BMPonly Whether to stop the character map at the Basic Multilingual Plane
	 * @param int  $useOTL  Which scripts the document asked to be laid out from the font's own tables
	 */
	function extractInfo($debug = false, $BMPonly = false, $useOTL = 0)
	{
		$this->panose = [];
		$this->sFamilyClass = 0;
		$this->sFamilySubClass = 0;
		// name - Naming table
		$name_offset = $this->seek_table("name");
		$format = $this->reader->readUInt16();
		if ($format != 0 && $format != 1) {
			throw new \Mpdf\Exception\FontException("Error loading font: Unknown name table format " . $format);
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
					throw new \Mpdf\Exception\FontException("Error loading font: PostScript name is UTF-16BE string of odd length");
				}
				$length /= 2;
				$N = '';
				while ($length > 0) {
					$char = $this->reader->readUInt16();
					$N .= (chr($char));
					$length -= 1;
				}
				$this->reader->seek($opos);
			} else {
				if ($platformId == 1 && $encodingId == 0 && $languageId == 0) { // Macintosh, Roman, English, PS Name
					$opos = $this->reader->tell();
					$N = $this->reader->bytesAt($string_data_offset + $offset, $length);
					$this->reader->seek($opos);
				}
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
		} else {
			if ($names[4]) {
				$psName = preg_replace('/ /', '-', $names[4]);
			} else {
				if ($names[1]) {
					$psName = preg_replace('/ /', '-', $names[1]);
				} else {
					$psName = '';
				}
			}
		}
		if (!$psName) {
			throw new \Mpdf\Exception\FontException("Error loading font: Could not find PostScript font name: " . $this->filename);
		}
		if ($debug) {
			for ($i = 0; $i < count($psName); $i++) {
				$c = $psName[$i];
				$oc = ord($c);
				if ($oc > 126 || strpos(' [](){}<>/%', $c) !== false) {
					throw new \Mpdf\Exception\FontException("psName=" . $psName . " contains invalid character " . $c . " ie U+" . ord($c));
				}
			}
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

		if ($names[6]) {
			$this->fullName = $names[6];
		}

		// head - Font header table
		$this->seek_table("head");
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			$ver_min = $this->reader->readUInt16();
			if ($ver_maj != 1) {
				throw new \Mpdf\Exception\FontException('Error loading font: Unknown head table version ' . $ver_maj . '.' . $ver_min);
			}
			$this->fontRevision = $this->reader->readUInt16() . $this->reader->readUInt16();

			$this->reader->skip(4);
			$magic = $this->reader->readUInt32();
			if ($magic != 0x5F0F3CF5) {
				throw new \Mpdf\Exception\FontException('Error loading font: Invalid head table magic ' . $magic);
			}
			$this->reader->skip(2);
		} else {
			$this->reader->skip(18);
		}
		$this->unitsPerEm = $unitsPerEm = $this->reader->readUInt16();
		$scale = 1000 / $unitsPerEm;
		$this->reader->skip(16);
		$xMin = $this->reader->readInt16();
		$yMin = $this->reader->readInt16();
		$xMax = $this->reader->readInt16();
		$yMax = $this->reader->readInt16();
		$this->bbox = [($xMin * $scale), ($yMin * $scale), ($xMax * $scale), ($yMax * $scale)];
		$this->reader->skip(3 * 2);
		$indexToLocFormat = $this->reader->readUInt16();
		$glyphDataFormat = $this->reader->readUInt16();
		if ($glyphDataFormat != 0) {
			throw new \Mpdf\Exception\FontException('Error loading font: Unknown glyph data format ' . $glyphDataFormat);
		}

		// hhea metrics table
		// ttf2t1 seems to use this value rather than the one in OS/2 - so put in for compatibility
		if (isset($this->tables["hhea"])) {
			$this->seek_table("hhea");
			$this->reader->skip(4);
			$hheaAscender = $this->reader->readInt16();
			$hheaDescender = $this->reader->readInt16();
			$this->ascent = ($hheaAscender * $scale);
			$this->descent = ($hheaDescender * $scale);
		}

		// OS/2 - OS/2 and Windows metrics table
		if (isset($this->tables["OS/2"])) {
			$this->seek_table("OS/2");
			$version = $this->reader->readUInt16();
			$this->reader->skip(2);
			$usWeightClass = $this->reader->readUInt16();
			$this->reader->skip(2);
			$fsType = $this->reader->readUInt16();
			if ($fsType == 0x0002 || ($fsType & 0x0300) != 0) {
				global $overrideTTFFontRestriction;
				if (!$overrideTTFFontRestriction) {
					throw new \Mpdf\Exception\FontException('Font file ' . $this->filename . ' cannot be embedded due to copyright restrictions.');
				}
				$this->restrictedUse = true;
			}
			$this->reader->skip(20);
			$sF = $this->reader->readInt16();
			$this->sFamilyClass = ($sF >> 8);
			$this->sFamilySubClass = ($sF & 0xFF);
			// PANOSE, 10 bytes, per the OS/2 table
			$panose = $this->reader->read(10);
			$this->panose = [];
			for ($p = 0; $p < strlen($panose); $p++) {
				$this->panose[] = ord($panose[$p]);
			}
			$this->reader->skip(26);
			$sTypoAscender = $this->reader->readInt16();
			$sTypoDescender = $this->reader->readInt16();
			if (!$this->ascent) {
				$this->ascent = ($sTypoAscender * $scale);
			}
			if (!$this->descent) {
				$this->descent = ($sTypoDescender * $scale);
			}
			if ($version > 1) {
				$this->reader->skip(16);
				$sCapHeight = $this->reader->readInt16();
				$this->capHeight = ($sCapHeight * $scale);
			} else {
				$this->capHeight = $this->ascent;
			}
		} else {
			$usWeightClass = 500;
			if (!$this->ascent) {
				$this->ascent = ($yMax * $scale);
			}
			if (!$this->descent) {
				$this->descent = ($yMin * $scale);
			}
			$this->capHeight = $this->ascent;
		}
		$this->stemV = 50 + intval(pow(($usWeightClass / 65.0), 2));

		// post - PostScript table
		$this->seek_table("post");
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			$ver_min = $this->reader->readUInt16();
			if ($ver_maj < 1 || $ver_maj > 4) {
				throw new \Mpdf\Exception\FontException('Error loading font: Unknown post table version ' . $ver_maj);
			}
		} else {
			$this->reader->skip(4);
		}
		$this->italicAngle = $this->reader->readInt16() + $this->reader->readUInt16() / 65536.0;
		$this->underlinePosition = $this->reader->readInt16() * $scale;
		$this->underlineThickness = $this->reader->readInt16() * $scale;
		$isFixedPitch = $this->reader->readUInt32();

		$this->flags = 4;

		if ($this->italicAngle != 0) {
			$this->flags = $this->flags | 64;
		}
		if ($usWeightClass >= 600) {
			$this->flags = $this->flags | 262144;
		}
		if ($isFixedPitch) {
			$this->flags = $this->flags | 1;
		}

		// hhea - Horizontal header table
		$this->seek_table("hhea");
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			$ver_min = $this->reader->readUInt16();
			if ($ver_maj != 1) {
				throw new \Mpdf\Exception\FontException(sprintf('Error loading font: Unknown hhea table version %s', $ver_maj));
			}
			$this->reader->skip(28);
		} else {
			$this->reader->skip(32);
		}
		$metricDataFormat = $this->reader->readUInt16();
		if ($metricDataFormat != 0) {
			throw new \Mpdf\Exception\FontException('Error loading font: Unknown horizontal metric data format ' . $metricDataFormat);
		}
		$numberOfHMetrics = $this->reader->readUInt16();
		if ($numberOfHMetrics == 0) {
			throw new \Mpdf\Exception\FontException('Error loading font: Number of horizontal metrics is 0');
		}

		// maxp - Maximum profile table
		$this->seek_table("maxp");
		if ($debug) {
			$ver_maj = $this->reader->readUInt16();
			$ver_min = $this->reader->readUInt16();
			if ($ver_maj != 1) {
				throw new \Mpdf\Exception\FontException('Error loading font: Unknown maxp table version ' . $ver_maj);
			}
		} else {
			$this->reader->skip(4);
		}
		$numGlyphs = $this->reader->readUInt16();

		// cmap - Character to glyph index mapping table
		$cmap_offset = $this->seek_table("cmap");
		$this->reader->skip(2);
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
			} // Microsoft, Unicode Format 12 table HKCS
			else {
				if ((($platformID == 3 && $encodingID == 10) || $platformID == 0) && !$BMPonly) {
					$format = $this->reader->uint16At($cmap_offset + $offset);
					if ($format == 12) {
						$unicode_cmap_offset = $cmap_offset + $offset;
						break;
					}
				}
			}
			$this->reader->seek($save_pos);
		}

		if (!$unicode_cmap_offset) {
			throw new \Mpdf\Exception\FontException('Font (' . $this->filename . ') does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)');
		}

		$sipset = false;
		$smpset = false;

		// mPDF 5.7.1
		$this->GSUBScriptLang = [];
		$this->rtlPUAstr = '';
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
			$this->reader->skip(4);

			$nGroups = $this->reader->readUInt32();

			$glyphToChar = [];
			$charToGlyph = [];
			for ($i = 0; $i < $nGroups; $i++) {
				$startCharCode = $this->reader->readUInt32();
				$endCharCode = $this->reader->readUInt32();
				$startGlyphCode = $this->reader->readUInt32();
				if ($endCharCode > 0x20000 && $endCharCode < 0x2FFFF) {
					$sipset = true;
				} else {
					if ($endCharCode > 0x10000 && $endCharCode < 0x1FFFF) {
						$smpset = true;
					}
				}
				$offset = 0;
				for ($unichar = $startCharCode; $unichar <= $endCharCode; $unichar++) {
					$glyph = $startGlyphCode + $offset;
					$offset++;
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

		// mPDF 5.7.1
		// Map Unmapped glyphs - from $numGlyphs
		if ($this->useOTL) {
			$bctr = 0xE000;
			for ($gid = 1; $gid < $numGlyphs; $gid++) {
				if (!isset($glyphToChar[$gid])) {
					while (isset($charToGlyph[$bctr])) {
						$bctr++;
					} // Avoid overwriting a glyph already mapped in PUA
					if (($bctr > 0xF8FF) && ($bctr < 0x2CEB0)) {
						if (!$BMPonly) {
							$bctr = 0x2CEB0;  // Use unassigned area 0x2CEB0 to 0x2F7FF (space for 10,000 characters)
							$this->sipset = $sipset = true; // forces subsetting; also ensure charwidths are saved
							while (isset($charToGlyph[$bctr])) {
								$bctr++;
							}
						} else {
							throw new \Mpdf\Exception\FontException(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $this->filename));
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
		$this->charToGlyph = $charToGlyph;
		$this->maxUniChar = $maxUniChar;
		// mPDF 5.7.1	OpenType Layout tables
		$this->GSUBScriptLang = [];
		$this->rtlPUAstr = '';
		if ($useOTL) {
			$this->_getGDEFtables();
			list($this->GSUBScriptLang, $this->GSUBFeatures, $this->GSUBLookups, $this->rtlPUAstr) = $this->_getGSUBtables();
			list($this->GPOSScriptLang, $this->GPOSFeatures, $this->GPOSLookups) = $this->_getGPOStables();
			$this->failIfNeitherTableOffers();
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
		// hmtx - Horizontal metrics table
		list($this->charWidths, $this->defaultWidth) = $this->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale, $maxUniChar);
	}

	/**
	 * Read GDEF and report what it says: which glyphs are marks, bases, ligatures and components,
	 * which marks belong to which attachment class, and which mark glyph sets a lookup can filter to.
	 */
	function _getGDEFtables()
	{
		// GDEF - Glyph Definition
		// https://learn.microsoft.com/en-us/typography/opentype/spec/gdef
		if (isset($this->tables["GDEF"])) {
			if ($this->mode == 'summary') {
				$this->mpdf->WriteHTML('<h1>GDEF table</h1>');
			}
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
			$this->reader->seek($gdef_offset + $GlyphClassDef_offset);
			/*
			  1	Base glyph (single character, spacing glyph)
			  2	Ligature glyph (multiple character, spacing glyph)
			  3	Mark glyph (non-spacing combining glyph)
			  4	Component glyph (part of single character, spacing glyph)
			 */
			$GlyphByClass = $this->_getClassDefinitionTable();

			if ($this->mode == 'summary') {
				$this->mpdf->WriteHTML('<h2>Glyph classes</h2>');
			}

			if (isset($GlyphByClass[1]) && count($GlyphByClass[1]) > 0) {
				$this->GlyphClassBases = $this->formatClassArr($GlyphByClass[1]);
				if ($this->mode == 'summary') {
					$this->mpdf->WriteHTML('<h3>Glyph class 1</h3>');
					$this->mpdf->WriteHTML('<h5>Base glyph (single character, spacing glyph)</h5>');
					$html = '';
					$html .= '<div class="glyphs">';
					foreach ($GlyphByClass[1] as $g) {
						$html .= '&#x' . $g . '; ';
					}
					$html .= '</div>';
					$this->mpdf->WriteHTML($html);
				}
			} else {
				$this->GlyphClassBases = '';
			}
			if (isset($GlyphByClass[2]) && count($GlyphByClass[2]) > 0) {
				$this->GlyphClassLigatures = $this->formatClassArr($GlyphByClass[2]);
				if ($this->mode == 'summary') {
					$this->mpdf->WriteHTML('<h3>Glyph class 2</h3>');
					$this->mpdf->WriteHTML('<h5>Ligature glyph (multiple character, spacing glyph)</h5>');
					$html = '';
					$html .= '<div class="glyphs">';
					foreach ($GlyphByClass[2] as $g) {
						$html .= '&#x' . $g . '; ';
					}
					$html .= '</div>';
					$this->mpdf->WriteHTML($html);
				}
			} else {
				$this->GlyphClassLigatures = '';
			}
			if (isset($GlyphByClass[3]) && count($GlyphByClass[3]) > 0) {
				$this->GlyphClassMarks = $this->formatClassArr($GlyphByClass[3]);
				if ($this->mode == 'summary') {
					$this->mpdf->WriteHTML('<h3>Glyph class 3</h3>');
					$this->mpdf->WriteHTML('<h5>Mark glyph (non-spacing combining glyph)</h5>');
					$html = '';
					$html .= '<div class="glyphs">';
					foreach ($GlyphByClass[3] as $g) {
						$html .= '&#x25cc;&#x' . $g . '; ';
					}
					$html .= '</div>';
					$this->mpdf->WriteHTML($html);
				}
			} else {
				$this->GlyphClassMarks = '';
			}
			if (isset($GlyphByClass[4]) && count($GlyphByClass[4]) > 0) {
				$this->GlyphClassComponents = $this->formatClassArr($GlyphByClass[4]);
				if ($this->mode == 'summary') {
					$this->mpdf->WriteHTML('<h3>Glyph class 4</h3>');
					$this->mpdf->WriteHTML('<h5>Component glyph (part of single character, spacing glyph)</h5>');
					$html = '';
					$html .= '<div class="glyphs">';
					foreach ($GlyphByClass[4] as $g) {
						$html .= '&#x' . $g . '; ';
					}
					$html .= '</div>';
					$this->mpdf->WriteHTML($html);
				}
			} else {
				$this->GlyphClassComponents = '';
			}

			// to use for MarkAttachmentType. A font need not define any mark glyphs, and the parser
			// already allows for that; this copy did not
			$Marks = isset($GlyphByClass[3]) ? $GlyphByClass[3] : [];

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
				if ($this->mode == 'summary') {
					$this->mpdf->WriteHTML('<h1>Mark Attachment Types</h1>');
				}
				$this->reader->seek($gdef_offset + $MarkAttachClassDef_offset);
				$MarkAttachmentTypes = $this->_getClassDefinitionTable();
				foreach ($MarkAttachmentTypes as $class => $glyphs) {
					if (is_array($Marks) && count($Marks)) {
						$mat = array_diff($Marks, $MarkAttachmentTypes[$class]);
						sort($mat, SORT_STRING);
					} else {
						$mat = [];
					}

					$this->MarkAttachmentType[$class] = $this->formatClassArr($mat);

					if ($this->mode == 'summary') {
						$this->mpdf->WriteHTML('<h3>Mark Attachment Type: ' . $class . '</h3>');
						$html = '';
						$html .= '<div class="glyphs">';
						foreach ($glyphs as $g) {
							$html .= '&#x25cc;&#x' . $g . '; ';
						}
						$html .= '</div>';
						$this->mpdf->WriteHTML($html);
					}
				}
			} else {
				$this->MarkAttachmentType = [];
			}

			// MarkGlyphSets in Version 0x00010002 of GDEF and later
			if ($ver_min >= 2 && $MarkGlyphSetsDef_offset) {
				if ($this->mode == 'summary') {
					$this->mpdf->WriteHTML('<h1>Mark Glyph Sets</h1>');
				}
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
					$this->MarkGlyphSets[$i] = $this->formatClassArr($glyphs);
					if ($this->mode == 'summary') {
						$this->mpdf->WriteHTML('<h3>Mark Glyph Set class: ' . $i . '</h3>');
						$html = '';
						$html .= '<div class="glyphs">';
						foreach ($glyphs as $g) {
							$html .= '&#x25cc;&#x' . $g . '; ';
						}
						$html .= '</div>';
						$this->mpdf->WriteHTML($html);
					}
				}
			} else {
				$this->MarkGlyphSets = [];
			}
		} else {
			$this->mpdf->WriteHTML('<div>GDEF table not defined</div>');
		}
	}

	/**
	 * A Sequence of no glyphs is legal - it is how a font deletes the glyph it covers - so the report
	 * shows it substituting nothing rather than passing over it. @see TTFontFile::multipleSubstitutes
	 */
	protected function multipleSubstitutes(array $sequence)
	{
		$substitute = [];
		foreach ($sequence as $sub) {
			$substitute[] = GlyphString::of($this->glyphToChar[$sub][0]);
		}

		return $substitute;
	}

	/**
	 * Every alternate, where the parser keeps only the first: which alternates an `aalt` offers is
	 * most of what makes one worth looking at. @see TTFontFile::alternateSubstitutes
	 */
	protected function alternateSubstitutes(array $alternateSet)
	{
		$substitute = [];
		for ($gl = 0; $gl < $alternateSet['GlyphCount']; $gl++) {
			$gid = $alternateSet['SubstituteGlyphID'][$gl];
			// A glyph the cmap does not reach has no character to report it by
			if (isset($this->glyphToChar[$gid][0])) {
				$substitute[] = GlyphString::of($this->glyphToChar[$gid][0]);
			}
		}

		return $substitute;
	}

	/**
	 * Report the GSUB lookups the script and language asked for, instead of building the shaper's
	 * derived tables from every script the font offers. @see TTFontFile::useGSUBlookups
	 *
	 * @return string Always empty: the report says nothing about the RTL Private Use Area mapping
	 */
	protected function useGSUBlookups(array $Lookup, array $gsub, array $GSLookup, $gsubOffset)
	{
		$this->_getGSUBarray($Lookup, $this->lookupsInTableOrder($gsub, 'GSUB'), $this->script);

		return '';
	}

	/**
	 * Report the substitution rules of a list of GSUB lookups.
	 *
	 * @param array  $Lookup     The GSUB lookup list, with subtable offsets already made absolute
	 * @param array  $lul        The lookups to report, as lookup index => the feature tag that asked
	 *                           for it
	 * @param string $scripttag  The script the report is being written for
	 * @param int    $level      1 for the report itself; 2 for a lookup nested inside a context rule,
	 *                           whose part is returned to the rule rather than written
	 * @param string $coverage   At level 2, the glyphs the nesting position can hold, so that only the
	 *                           rules that could fire there are reported. Empty where it names class 0
	 * @param string $exB        At level 2, the example text that precedes the nested position
	 * @param string $exL        At level 2, the example text that follows it
	 * @param string $class0excl At level 2, every glyph in some class of the nesting rule's Class
	 *                           Definition, so that an empty $coverage reads as class 0
	 *
	 * @return string At level 2, the rules; at level 1, the empty string, the report having been
	 *                written as it was built
	 */
	function _getGSUBarray(array $Lookup, $lul, $scripttag, $level = 1, $coverage = '', $exB = '', $exL = '', $class0excl = '')
	{
		// Process (3) LookupList for specific Script-LangSys
		// Generate preg_replace
		// Level 1 writes the report, level 2 returns its part of it to the rule that nested the
		// lookup. Both append to one buffer so that a nested lookup's thousands of rows can be handed
		// over as they are built, rather than arriving at level 1 as one string too long to write.
		if ($level == 1) {
			$this->report = '';
		}
		$html = &$this->report;
		if ($level == 1) {
			$html .= '<bookmark level="0" content="GSUB features">';
		}
		foreach ($lul as $i => $tag) {
			$html .= '<div class="level' . $level . '">';
			$html .= '<h5 class="level' . $level . '">';
			if ($level == 1) {
				$html .= '<bookmark level="1" content="' . $tag . ' [#' . $i . ']">';
			}
			$html .= 'Lookup #' . $i . ' [tag: <span style="color:#000066;">' . $tag . '</span>]</h5>';
			$ignore = $this->_getGSUBignoreString($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
			if ($ignore) {
				$html .= '<div class="ignore">Ignoring: ' . $ignore . '</div> ';
			}

			$Type = $Lookup[$i]['Type'];
			$Flag = $Lookup[$i]['Flag'];
			if (($Flag & 0x0001) == 1) {
				$dir = 'RTL';
			} else {
				$dir = 'LTR';
			}

			for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
				$html .= '<div class="subtable">Subtable #' . $c;
				if ($level == 1) {
					$html .= '<bookmark level="2" content="Subtable #' . $c . '">';
				}
				$html .= '</div>';

				$SubstFormat = $Lookup[$i]['Subtable'][$c]['Format'];

				// LookupType 1: Single Substitution Subtable
				if ($Lookup[$i]['Type'] == 1) {
					$html .= '<div class="lookuptype">LookupType 1: Single Substitution Subtable</div>';
					for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
						$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
						$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
						if ($level == 2 && !$this->positionHolds($coverage, $class0excl, $inputGlyphs[0])) {
							continue;
						}
						$this->flushReport($html);
						$html .= '<div class="substitution">';
						$html .= '<span class="unicode">' . $this->formatUni($inputGlyphs[0]) . '&nbsp;</span> ';
						if ($level == 2 && $exB) {
							$html .= $exB;
						}
						$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($inputGlyphs[0]) . '</span>';
						if ($level == 2 && $exL) {
							$html .= $exL;
						}
						$html .= '&nbsp; &raquo; &raquo; &nbsp;';
						if ($level == 2 && $exB) {
							$html .= $exB;
						}
						$html .= '<span class="changed">&nbsp;' . $this->formatEntity($substitute) . '</span>';
						if ($level == 2 && $exL) {
							$html .= $exL;
						}
						$html .= '&nbsp; <span class="unicode">' . $this->formatUni($substitute) . '</span> ';
						$html .= '</div>';
					}
				} // LookupType 2: Multiple Substitution Subtable
				else {
					if ($Lookup[$i]['Type'] == 2) {
						$html .= '<div class="lookuptype">LookupType 2: Multiple Substitution Subtable</div>';
						for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
							$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
							$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'];
							if ($level == 2 && !$this->positionHolds($coverage, $class0excl, $inputGlyphs[0])) {
								continue;
							}
							$this->flushReport($html);
							$html .= '<div class="substitution">';
							$html .= '<span class="unicode">' . $this->formatUni($inputGlyphs[0]) . '&nbsp;</span> ';
							if ($level == 2 && $exB) {
								$html .= $exB;
							}
							$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($inputGlyphs[0]) . '</span>';
							if ($level == 2 && $exL) {
								$html .= $exL;
							}
							$html .= '&nbsp; &raquo; &raquo; &nbsp;';
							if ($level == 2 && $exB) {
								$html .= $exB;
							}
							$html .= '<span class="changed">&nbsp;' . $this->formatEntityArr($substitute) . '</span>';
							if ($level == 2 && $exL) {
								$html .= $exL;
							}
							$html .= '&nbsp; <span class="unicode">' . $this->formatUniArr($substitute) . '</span> ';
							$html .= '</div>';
						}
					} // LookupType 3: Alternate Forms
					else {
						if ($Lookup[$i]['Type'] == 3) {
							$html .= '<div class="lookuptype">LookupType 3: Alternate Forms</div>';
							for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
								$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
								$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
								if ($level == 2 && !$this->positionHolds($coverage, $class0excl, $inputGlyphs[0])) {
									continue;
								}
								$this->flushReport($html);
								$html .= '<div class="substitution">';
								$html .= '<span class="unicode">' . $this->formatUni($inputGlyphs[0]) . '&nbsp;</span> ';
								if ($level == 2 && $exB) {
									$html .= $exB;
								}
								$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($inputGlyphs[0]) . '</span>';
								if ($level == 2 && $exL) {
									$html .= $exL;
								}
								$html .= '&nbsp; &raquo; &raquo; &nbsp;';
								if ($level == 2 && $exB) {
									$html .= $exB;
								}
								$html .= '<span class="changed">&nbsp;' . $this->formatEntity($substitute) . '</span>';
								if ($level == 2 && $exL) {
									$html .= $exL;
								}
								$html .= '&nbsp; <span class="unicode">' . $this->formatUni($substitute) . '</span> ';
								if (count($Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute']) > 1) {
									for ($alt = 1; $alt < count($Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute']); $alt++) {
										$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][$alt];
										$html .= '&nbsp; | &nbsp; ALT #' . $alt . ' &nbsp; ';
										$html .= '<span class="changed">&nbsp;' . $this->formatEntity($substitute) . '</span>';
										$html .= '&nbsp; <span class="unicode">' . $this->formatUni($substitute) . '</span> ';
									}
								}
								$html .= '</div>';
							}
						} // LookupType 4: Ligature Substitution Subtable
						else {
							if ($Lookup[$i]['Type'] == 4) {
								$html .= '<div class="lookuptype">LookupType 4: Ligature Substitution Subtable</div>';
								for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
									$inputGlyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
									$substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
									if ($level == 2 && !$this->positionHolds($coverage, $class0excl, $inputGlyphs[0])) {
										continue;
									}
									$this->flushReport($html);
									$html .= '<div class="substitution">';
									$html .= '<span class="unicode">' . $this->formatUniArr($inputGlyphs) . '&nbsp;</span> ';
									if ($level == 2 && $exB) {
										$html .= $exB;
									}
									$html .= '<span class="unchanged">&nbsp;' . $this->formatEntityArr($inputGlyphs) . '</span>';
									if ($level == 2 && $exL) {
										$html .= $exL;
									}
									$html .= '&nbsp; &raquo; &raquo; &nbsp;';
									if ($level == 2 && $exB) {
										$html .= $exB;
									}
									$html .= '<span class="changed">&nbsp;' . $this->formatEntity($substitute) . '</span>';
									if ($level == 2 && $exL) {
										$html .= $exL;
									}
									$html .= '&nbsp; <span class="unicode">' . $this->formatUni($substitute) . '</span> ';
									$html .= '</div>';
								}
							} // LookupType 5: Contextual Substitution Subtable
							else {
								if ($Lookup[$i]['Type'] == 5) {
									$html .= '<div class="lookuptype">LookupType 5: Contextual Substitution Subtable</div>';
									// Format 1: Context Substitution
									if ($SubstFormat == 1) {
										$html .= '<div class="lookuptypesub">Format 1: Context Substitution</div>';
										for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubRuleSetCount']; $s++) {
											// SubRuleSet											$html .= '<div class="rule">Subrule Set: ' . $s . '</div>';
											foreach ($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'] as $rctr => $rule) {
												// SubRule
												$html .= '<div class="rule">SubRule: ' . $rctr . '</div>';
												$inputGlyphs = [];
												if ($rule['GlyphCount'] > 1) {
													$inputGlyphs = $rule['InputGlyphs'];
												}
												$inputGlyphs[0] = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['FirstGlyph'];
												ksort($inputGlyphs);

												$this->reportGSUBrule($Lookup, $this->substLookupRecords($rule), [], $inputGlyphs, [], '', '', '', $tag, $scripttag);
											}
										}
									} // Format 2: Class-based Context Glyph Substitution
									else {
										if ($SubstFormat == 2) {
											$html .= '<div class="lookuptypesub">Format 2: Class-based Context Glyph Substitution</div>';
											foreach ($Lookup[$i]['Subtable'][$c]['SubClassSet'] as $inputClass => $cscs) {
												$html .= '<div class="rule">Input Class: ' . $inputClass . '</div>';
												for ($cscrule = 0; $cscrule < $cscs['SubClassRuleCnt']; $cscrule++) {
													$html .= '<div class="rule">Rule: ' . $cscrule . '</div>';
													$rule = $cscs['SubClassRule'][$cscrule];

													$inputGlyphs = [];

													$inputGlyphs[0] = $this->classGlyphs($Lookup[$i]['Subtable'][$c]['InputClasses'], $inputClass);

													if ($rule['InputGlyphCount'] > 1) {
														//  NB starts at 1
														for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
															$classindex = $rule['Input'][$gcl];
															$inputGlyphs[$gcl] = $this->classGlyphs($Lookup[$i]['Subtable'][$c]['InputClasses'], $classindex);
														}
													}

													// Class 0 contains all the glyphs NOT in the other classes
													$class0excl = implode('|', $Lookup[$i]['Subtable'][$c]['InputClasses']);

													$this->reportGSUBrule($Lookup, $this->substLookupRecords($rule), [], $inputGlyphs, [], $class0excl, '', '', $tag, $scripttag);
												}
											}
										} // Format 3: Coverage-based Context Glyph Substitution  p259
										else {
											if ($SubstFormat == 3) {
												$html .= '<div class="lookuptypesub">Format 3: Coverage-based Context Glyph Substitution  </div>';
												// IgnoreMarks flag set on main Lookup table
												$inputGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];

												$this->reportGSUBrule($Lookup, $this->substLookupRecords($Lookup[$i]['Subtable'][$c]), [], $inputGlyphs, [], '', '', '', $tag, $scripttag);
											}
										}
									}

								} // LookupType 6: Chaining Contextual Substitution Subtable
								else {
									if ($Lookup[$i]['Type'] == 6) {
										$html .= '<div class="lookuptype">LookupType 6: Chaining Contextual Substitution Subtable</div>';
										// Format 1: Simple Chaining Context Glyph Substitution  p255
										if ($SubstFormat == 1) {
											$html .= '<div class="lookuptypesub">Format 1: Simple Chaining Context Glyph Substitution  </div>';
											for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $s++) {
												// ChainSubRuleSet												$html .= '<div class="rule">Subrule Set: ' . $s . '</div>';
												$firstInputGlyph = $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'][$s]; // First input gyyph
												foreach ($Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'] as $rctr => $rule) {
													$html .= '<div class="rule">SubRule: ' . $rctr . '</div>';
													// ChainSubRule
													$inputGlyphs = [];
													if ($rule['InputGlyphCount'] > 1) {
														$inputGlyphs = $rule['InputGlyphs'];
													}
													$inputGlyphs[0] = $firstInputGlyph;
													ksort($inputGlyphs);

													if ($rule['BacktrackGlyphCount']) {
														$backtrackGlyphs = $rule['BacktrackGlyphs'];
													} else {
														$backtrackGlyphs = [];
													}

													if ($rule['LookaheadGlyphCount']) {
														$lookaheadGlyphs = $rule['LookaheadGlyphs'];
													} else {
														$lookaheadGlyphs = [];
													}

													$this->reportGSUBrule($Lookup, $this->substLookupRecords($rule), $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, '', '', '', $tag, $scripttag);
												}
											}
										} // Format 2: Class-based Chaining Context Glyph Substitution  p257
										else {
											if ($SubstFormat == 2) {
												$html .= '<div class="lookuptypesub">Format 2: Class-based Chaining Context Glyph Substitution  </div>';
												foreach ($Lookup[$i]['Subtable'][$c]['ChainSubClassSet'] as $inputClass => $cscs) {
													$html .= '<div class="rule">Input Class: ' . $inputClass . '</div>';
													for ($cscrule = 0; $cscrule < $cscs['ChainSubClassRuleCnt']; $cscrule++) {
														$html .= '<div class="rule">Rule: ' . $cscrule . '</div>';
														$rule = $cscs['ChainSubClassRule'][$cscrule];

														// These contain classes of glyphs as strings
														// $Lookup[$i]['Subtable'][$c]['InputClasses'][(class)] e.g. 02E6|02E7|02E8
														// $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][(class)]
														// $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][(class)]
														// These contain arrays of classIndexes
														// [Backtrack] [Lookahead] and [Input] (Input is from the second position only)

														$inputGlyphs = [];

														$inputGlyphs[0] = $this->classGlyphs($Lookup[$i]['Subtable'][$c]['InputClasses'], $inputClass);
														if ($rule['InputGlyphCount'] > 1) {
															//  NB starts at 1
															for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
																$classindex = $rule['Input'][$gcl];
																$inputGlyphs[$gcl] = $this->classGlyphs($Lookup[$i]['Subtable'][$c]['InputClasses'], $classindex);
															}
														}
														// Class 0 contains all the glyphs NOT in the other classes - of its own ClassDef. A chained
														// context has three of them, so telling the reader a backtrack position is anything but the
														// input classes named the wrong set. The shaper keeps them apart as $bclass0excl and $lclass0excl.
														$class0excl = implode('|', $Lookup[$i]['Subtable'][$c]['InputClasses']);
														$bclass0excl = implode('|', $Lookup[$i]['Subtable'][$c]['BacktrackClasses']);
														$lclass0excl = implode('|', $Lookup[$i]['Subtable'][$c]['LookaheadClasses']);

														// Built fresh per rule: the rules of a set can name fewer positions than the one before,
														// and a kept array would leave the earlier rule's extra positions in the sequence
														$backtrackGlyphs = [];
														for ($gcl = 0; $gcl < $rule['BacktrackGlyphCount']; $gcl++) {
															$backtrackGlyphs[$gcl] = $this->classGlyphs($Lookup[$i]['Subtable'][$c]['BacktrackClasses'], $rule['Backtrack'][$gcl]);
														}

														$lookaheadGlyphs = [];
														for ($gcl = 0; $gcl < $rule['LookaheadGlyphCount']; $gcl++) {
															$lookaheadGlyphs[$gcl] = $this->classGlyphs($Lookup[$i]['Subtable'][$c]['LookaheadClasses'], $rule['Lookahead'][$gcl]);
														}

														$this->reportGSUBrule($Lookup, $this->substLookupRecords($rule), $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag);
													}
												}

											} // Format 3: Coverage-based Chaining Context Glyph Substitution  p259
											else {
												if ($SubstFormat == 3) {
													$html .= '<div class="lookuptypesub">Format 3: Coverage-based Chaining Context Glyph Substitution  </div>';
													// IgnoreMarks flag set on main Lookup table
													$inputGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];

													if ($Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']) {
														$backtrackGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'];
													} else {
														$backtrackGlyphs = [];
													}

													if ($Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']) {
														$lookaheadGlyphs = $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'];
													} else {
														$lookaheadGlyphs = [];
													}

													$this->reportGSUBrule($Lookup, $this->substLookupRecords($Lookup[$i]['Subtable'][$c]), $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, '', '', '', $tag, $scripttag);
												}
											}
										}
									} else {
										// LookupType 8: Reverse Chaining Contextual Single Substitution Subtable
										if ($Lookup[$i]['Type'] == 8 && !empty($Lookup[$i]['Subtable'][$c]['subs'])) {
											$html .= '<div class="lookuptype">LookupType 8: Reverse Chaining Contextual Single Substitution Subtable</div>';
											foreach ($Lookup[$i]['Subtable'][$c]['subs'] as $luss) {
												$inputGlyphs = $luss['Replace'];
												$substitute = $luss['substitute'][0];
												if ($level == 2 && !$this->positionHolds($coverage, $class0excl, $inputGlyphs[0])) {
													continue;
												}
												$this->flushReport($html);
												$html .= '<div class="substitution">';
												$html .= '<span class="unicode">' . $this->formatUni($inputGlyphs[0]) . '&nbsp;</span> ';
												$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($inputGlyphs[0]) . '</span>';
												$html .= '&nbsp; &raquo; &raquo; &nbsp;';
												$html .= '<span class="changed">&nbsp;' . $this->formatEntity($substitute) . '</span>';
												$html .= '&nbsp; <span class="unicode">' . $this->formatUni($substitute) . '</span> ';
												$html .= '</div>';
											}
										}
									}
								}
							}
						}
					}
				}
			}
			$html .= '</div>';
			$this->flushReport($html);
		}
		if ($level == 1 && $html !== '') {
			$this->mpdf->WriteHTML($html);
			$html = '';
		}

		return '';
	}

	/**
	 * What a lookup's flags say to skip, in words, for the report to show above its rules.
	 *
	 * The parser's copy returns the glyphs themselves, as a pattern; here the classes are named
	 * instead, a list of every mark in the font being no use to a reader. A font whose
	 * MarkFilteringSet GDEF never defined still fails here rather than being reported as if it were
	 * well formed.
	 *
	 * @param int $flag             The lookup's flags
	 * @param int $MarkFilteringSet The mark glyph set the flags name, where they name one
	 *
	 * @return string The classes skipped, named and "|"-separated, or "" where the flags skip nothing
	 */
	function _getGSUBignoreString($flag, $MarkFilteringSet)
	{
		// If ignoreFlag set, combine all ignore glyphs into -> "((?:(?: FBA1| FBA2| FBA3))*)"
		// else "()"
		// for Input - set on secondary Lookup table if in Context, and set Backtrack and Lookahead on Context Lookup
		$str = "";
		$ignoreflag = 0;

		// Flag & 0xFF?? = MarkAttachmentType
		if ($flag & 0xFF00) {
			$MarkAttachmentType = $flag >> 8;
			$ignoreflag = $flag;
			//$str = $this->MarkAttachmentType[$MarkAttachmentType];
			$str = "MarkAttachmentType[" . $MarkAttachmentType . "] ";
		}

		// Flag & 0x0010 = UseMarkFilteringSet
		if ($flag & 0x0010) {
			// Fail here rather than dump a lookup whose filtering set GDEF never defined
			$this->markGlyphSet($MarkFilteringSet);
			$ignoreflag = $flag;
			$str = "Marks outside Mark Glyph Set[" . $MarkFilteringSet . "] ";
		}

		// If Ignore Marks set, supercedes any above
		// Flag & 0x0008 = Ignore Marks
		if (($flag & 0x0008) == 0x0008) {
			$ignoreflag = 8;
			//$str = $this->GlyphClassMarks;
			$str = "Mark Glyphs ";
		}

		// Flag & 0x0004 = Ignore Ligatures
		if (($flag & 0x0004) == 0x0004) {
			$ignoreflag += 4;
			if ($str) {
				$str .= "|";
			}
			//$str .= $this->GlyphClassLigatures;
			$str .= "Ligature Glyphs ";
		}
		// Flag & 0x0002 = Ignore BaseGlyphs
		if (($flag & 0x0002) == 0x0002) {
			$ignoreflag += 2;
			if ($str) {
				$str .= "|";
			}
			//$str .= $this->GlyphClassBases;
			$str .= "Base Glyphs ";
		}
		if ($str) {
			return $str;
		} else {
			return "";
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
	 * Says what the nested lookup matches within the context, which is what its part of the report is
	 * filtered by.
	 *
	 * @param array  $inputGlyphs  The input sequence, one pipe-joined glyph string per position
	 * @param string $ignore       The glyphs the lookup's flags say to skip, between positions
	 * @param array  $lookupGlyphs The nested lookup's own input sequence
	 * @param int    $seqIndex     Which position of the input sequence the nested lookup applies at
	 *
	 * @return string The sequence, position by position
	 */
	function _makeGSUBcontextInputMatch($inputGlyphs, $ignore, $lookupGlyphs, $seqIndex)
	{
		// $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
		// Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
		// $inputGlyphs = array of glyphs(glyphstrings) making up Input sequence in Context
		// $lookupGlyphs = array of glyphs (single Glyphs) making up Lookup Input sequence
		$mLen = count($lookupGlyphs);  // nGlyphs in the secondary Lookup match
		$nInput = count($inputGlyphs); // nGlyphs in the Primary Input sequence
		$str = "";
		for ($i = 0; $i < $nInput; $i++) {
			if ($i > 0) {
				$str .= $ignore . " ";
			}
			if ($i >= $seqIndex && $i < ($seqIndex + $mLen)) {
				$str .= "" . $lookupGlyphs[($i - $seqIndex)] . "";
			} else {
				$str .= "" . $inputGlyphs[($i)] . "";
			}
		}

		return $str;
	}

	/**
	 * The input sequence of a context rule, with the skipped glyphs allowed for between positions.
	 *
	 * @param array  $inputGlyphs The input sequence, one pipe-joined glyph string per position
	 * @param string $ignore The glyphs the lookup's flags say to skip, as a group that matches a run
	 *                       of them, or "()" where nothing is skipped
	 *
	 * @return string The sequence, position by position
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
			$str .= "" . $inputGlyphs[($i - 1)] . "";
		}

		return $str;
	}

	/**
	 * The backtrack sequence of a chained context rule, read back into writing order.
	 *
	 * A backtrack is stored nearest-first - position 0 is the glyph immediately before the input - so
	 * it is walked backwards to come out in the order the text is written in.
	 *
	 * @param array  $backtrackGlyphs The backtrack sequence, one pipe-joined glyph string per position
	 * @param string $ignore The glyphs the lookup's flags say to skip, as a group that matches a run
	 *                       of them, or "()" where nothing is skipped
	 *
	 * @return string The sequence, position by position
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
			$str .= "" . $backtrackGlyphs[$i] . " " . $ignore . " ";
		}

		return $str;
	}

	/**
	 * The lookahead sequence of a chained context rule, which is already in writing order.
	 *
	 * @param array  $lookaheadGlyphs The lookahead sequence, one pipe-joined glyph string per position
	 * @param string $ignore The glyphs the lookup's flags say to skip, as a group that matches a run
	 *                       of them, or "()" where nothing is skipped
	 *
	 * @return string The sequence, position by position
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
			$str .= $ignore . " " . $lookaheadGlyphs[$i] . "";
		}

		return $str;
	}

	/**
	 * Summary mode stops at the scripts and languages a font offers, which is the whole of what that
	 * page reports. Detail mode goes on to walk the lookups of the one script it was asked for.
	 */
	protected function wantsLookups()
	{
		return $this->mode !== 'summary';
	}

	/**
	 * @param string $tag The layout table about to be reported, 'GSUB' or 'GPOS'
	 */
	protected function reportTableRead($tag)
	{
		$this->mpdf->WriteHTML('<h1>' . $tag . ' Tables</h1>');
	}

	/**
	 * @param string $tag The layout table this font does not have, 'GSUB' or 'GPOS'
	 */
	protected function reportTableMissing($tag)
	{
		$this->mpdf->WriteHTML('<div>' . $tag . ' table not defined</div>');
	}

	/**
	 * The scripts a table speaks for, each language system under them linked to its detail page, and
	 * the feature tags that language system asks for.
	 */
	protected function reportScriptList($tag, array $features)
	{
		// The summary page is this list; the detail page reports the lookups of one entry in it
		if ($this->wantsLookups()) {
			return;
		}

		$this->mpdf->WriteHTML('<h3>' . $tag . ' Scripts &amp; Languages</h3>');
		$this->mpdf->WriteHTML('<div class="glyphs">');

		$html = '';
		if (count($features)) {
			foreach ($features as $script => $languages) {
				$html .= '<h5>' . $script . '</h5>';
				foreach ($languages as $language => $tags) {
					$html .= '<div><a href="' . $this->detailLink($script, $language) . '">' . $language . '</a></b>: ';
					foreach ($tags as $featureTag => $lookupListIndices) {
						$html .= $featureTag . ' ';
					}
					$html .= '</div>';
				}
			}
		} else {
			$html .= '<div>No entries in ' . $tag . ' table.</div>';
		}

		$this->mpdf->WriteHTML($html);
		$this->mpdf->WriteHTML('</div>');
	}

	/**
	 * Every lookup the script and language system asked for, in the order the table lists them.
	 *
	 * A feature names the lookups it wants, but the order they run in is the Lookup table's rather
	 * than the feature list's, so they are keyed by lookup index and sorted. The tag is kept against
	 * each because the report names the feature that asked for it.
	 *
	 * @return array LookupListIndex => the feature tag that asked for it, in run order
	 */
	private function lookupsInTableOrder(array $features, $table)
	{
		$lul = [];
		foreach ($this->langSys($features, $table) as $tag => $lookupListIndices) {
			foreach ($lookupListIndices as $lookupListIndex) {
				$lul[$lookupListIndex] = $tag;
			}
		}

		ksort($lul);

		return $lul;
	}

	/**
	 * Report the GPOS lookups the script and language asked for, instead of caching their coverage.
	 *
	 * The parser's version of this walks every subtable to work out which glyph each one could match
	 * on, and writes that out for the shaper. The dump has no shaper to feed, so it goes the other
	 * way: the lookups this one script and language system actually run, in the order they run in,
	 * each written out rule by rule.
	 */
	protected function useGPOSlookups(array $Lookup, $gposOffset, array $features)
	{
		$this->_getGPOSarray(
			$this->absoluteSubtables($Lookup, $gposOffset),
			$this->lookupsInTableOrder($features, 'GPOS'),
			$this->script
		);
	}

	/**
	 * Report the positioning rules of a list of GPOS lookups.
	 *
	 * @param array  $Lookup     The GPOS lookup list, with subtable offsets already made absolute
	 * @param array  $lul        The lookups to report, as lookup index => the feature tag that asked
	 *                           for it
	 * @param string $scripttag  The script the report is being written for
	 * @param int    $level      1 for the report itself; 2 for a lookup nested inside a context rule,
	 *                           whose part is returned to the rule rather than written
	 * @param string $lcoverage  At level 2, the glyphs the nesting position can hold, so that only the
	 *                           rules that could fire there are reported. Empty where it names class 0
	 * @param string $exB        At level 2, the example text that precedes the nested position
	 * @param string $exL        At level 2, the example text that follows it
	 * @param string $class0excl At level 2, every glyph in some class of the nesting rule's Class
	 *                           Definition, so that an empty $lcoverage reads as class 0
	 *
	 * @return string At level 2, the rules; at level 1, the empty string, the report having been
	 *                written as it was built
	 */
	function _getGPOSarray(array $Lookup, $lul, $scripttag, $level = 1, $lcoverage = '', $exB = '', $exL = '', $class0excl = '')
	{
		// Process (3) LookupList for specific Script-LangSys
		// Level 1 writes the report, level 2 returns its part of it to the rule that nested the
		// lookup. Both append to one buffer so that a nested lookup's thousands of rows can be handed
		// over as they are built, rather than arriving at level 1 as one string too long to write.
		if ($level == 1) {
			$this->report = '';
		}
		$html = &$this->report;
		if ($level == 1) {
			$html .= '<bookmark level="0" content="GPOS features">';
		}
		foreach ($lul as $luli => $tag) {
			$html .= '<div class="level' . $level . '">';
			$html .= '<h5 class="level' . $level . '">';
			if ($level == 1) {
				$html .= '<bookmark level="1" content="' . $tag . ' [#' . $luli . ']">';
			}
			$html .= 'Lookup #' . $luli . ' [tag: <span style="color:#000066;">' . $tag . '</span>]</h5>';
			$ignore = $this->_getGSUBignoreString($Lookup[$luli]['Flag'], $Lookup[$luli]['MarkFilteringSet']);
			if ($ignore) {
				$html .= '<div class="ignore">Ignoring: ' . $ignore . '</div> ';
			}

			$Type = $Lookup[$luli]['Type'];
			$Flag = $Lookup[$luli]['Flag'];
			if (($Flag & 0x0001) == 1) {
				$dir = 'RTL';
			} else {
				$dir = 'LTR';
			}

			for ($c = 0; $c < $Lookup[$luli]['SubtableCount']; $c++) {
				$html .= '<div class="subtable">Subtable #' . $c;
				if ($level == 1) {
					$html .= '<bookmark level="2" content="Subtable #' . $c . '">';
				}
				$html .= '</div>';

				// Lets start
				$subtable_offset = $Lookup[$luli]['Subtables'][$c];
				$this->reader->seek($subtable_offset);
				$PosFormat = $this->reader->readUInt16();

				// LookupType 1: Single adjustment 	Adjust position of a single glyph (e.g. SmallCaps/Sups/Subs)
				if ($Lookup[$luli]['Type'] == 1) {
					$html .= '<div class="lookuptype">LookupType 1: Single adjustment [Format ' . $PosFormat . ']</div>';
					// Format 1:
					if ($PosFormat == 1) {
						$Coverage = $subtable_offset + $this->reader->readUInt16();
						$ValueFormat = $this->reader->readUInt16();
						$Value = $this->valueRecord($ValueFormat);

						$this->reader->seek($Coverage);
						$glyphs = $this->_getCoverage(); // Array of Hex Glyphs
						for ($g = 0; $g < count($glyphs); $g++) {
							if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $glyphs[$g])) {
								continue;
							}

							$this->flushReport($html);
							$html .= '<div class="substitution">';
							$html .= '<span class="unicode">' . $this->formatUni($glyphs[$g]) . '&nbsp;</span> ';
							if ($level == 2 && $exB) {
								$html .= $exB;
							}
							$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
							if ($level == 2 && $exL) {
								$html .= $exL;
							}
							$html .= '&nbsp; &raquo; &raquo; &nbsp;';
							if ($level == 2 && $exB) {
								$html .= $exB;
							}
							$html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
							if ($level == 2 && $exL) {
								$html .= $exL;
							}
							$html .= ' <span class="unicode">';
							if ($Value['XPlacement']) {
								$html .= ' Xpl: ' . $Value['XPlacement'] . ';';
							}
							if ($Value['YPlacement']) {
								$html .= ' YPl: ' . $Value['YPlacement'] . ';';
							}
							if ($Value['XAdvance']) {
								$html .= ' Xadv: ' . $Value['XAdvance'];
							}
							$html .= '</span>';
							$html .= '</div>';
						}
					}
					// Format 2:
					else {
						if ($PosFormat == 2) {
							$Coverage = $subtable_offset + $this->reader->readUInt16();
							$ValueFormat = $this->reader->readUInt16();
							$ValueCount = $this->reader->readUInt16();
							$Values = [];
							for ($v = 0; $v < $ValueCount; $v++) {
								$Values[] = $this->valueRecord($ValueFormat);
							}

							$this->reader->seek($Coverage);
							$glyphs = $this->_getCoverage(); // Array of Hex Glyphs

							for ($g = 0; $g < count($glyphs); $g++) {
								if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $glyphs[$g])) {
									continue;
								}
								$Value = $Values[$g];

								$this->flushReport($html);
								$html .= '<div class="substitution">';
								$html .= '<span class="unicode">' . $this->formatUni($glyphs[$g]) . '&nbsp;</span> ';
								if ($level == 2 && $exB) {
									$html .= $exB;
								}
								$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
								if ($level == 2 && $exL) {
									$html .= $exL;
								}
								$html .= '&nbsp; &raquo; &raquo; &nbsp;';
								if ($level == 2 && $exB) {
									$html .= $exB;
								}
								$html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
								if ($level == 2 && $exL) {
									$html .= $exL;
								}
								$html .= ' <span class="unicode">';
								if ($Value['XPlacement']) {
									$html .= ' Xpl: ' . $Value['XPlacement'] . ';';
								}
								if ($Value['YPlacement']) {
									$html .= ' YPl: ' . $Value['YPlacement'] . ';';
								}
								if ($Value['XAdvance']) {
									$html .= ' Xadv: ' . $Value['XAdvance'];
								}
								$html .= '</span>';
								$html .= '</div>';
							}
						}
					}
				}
				// LookupType 2: Pair adjustment 	Adjust position of a pair of glyphs (Kerning)
				else {
					if ($Lookup[$luli]['Type'] == 2) {
						$html .= '<div class="lookuptype">LookupType 2: Pair adjustment e.g. Kerning [Format ' . $PosFormat . ']</div>';
						$Coverage = $subtable_offset + $this->reader->readUInt16();
						$ValueFormat1 = $this->reader->readUInt16();
						$ValueFormat2 = $this->reader->readUInt16();
						// Format 1:
						if ($PosFormat == 1) {
							$PairSetCount = $this->reader->readUInt16();
							$PairSetOffset = [];
							for ($p = 0; $p < $PairSetCount; $p++) {
								$PairSetOffset[] = $subtable_offset + $this->reader->readUInt16();
							}
							$this->reader->seek($Coverage);
							$glyphs = $this->_getCoverage(); // Array of Hex Glyphs
							for ($p = 0; $p < $PairSetCount; $p++) {
								if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $glyphs[$p])) {
									continue;
								}
								$this->reader->seek($PairSetOffset[$p]);
								// First Glyph = $glyphs[$p]
// Takes too long e.g. Calibri font - just list kerning pairs with this:
								$html .= '<div class="glyphs">';
								$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($glyphs[$p]) . ' </span>';

								//PairSet table
								$PairValueCount = $this->reader->readUInt16();
								for ($pv = 0; $pv < $PairValueCount; $pv++) {
									//PairValueRecord
									$gid = $this->reader->readUInt16();
									$SecondGlyph = GlyphString::of($this->glyphToChar[$gid][0]);
									$Value1 = $this->valueRecord($ValueFormat1);
									$Value2 = $this->valueRecord($ValueFormat2);

									// If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180 to take
									// account of direction. mPDF does not need the XPlacement adjustment
									if ($dir == 'RTL' && $Value1['XPlacement']) {
										$Value1['XPlacement'] -= $Value1['XAdvance'];
									}

									if ($ValueFormat2) {
										// If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180 to take
										// account of direction. mPDF does not need the XPlacement adjustment
										if ($dir == 'RTL' && $Value2['XPlacement'] && $Value2['XAdvance']) {
											$Value2['XPlacement'] -= $Value2['XAdvance'];
										}
									}

									$html .= ' ' . $this->formatEntity($SecondGlyph) . ' ';

									/*
									  $html .= '<div class="substitution">';
									  $html .= '<span class="unicode">'.$this->formatUni($glyphs[$p]).'&nbsp;</span> ';
									  if ($level==2 && $exB) { $html .= $exB; }
									  $html .= '<span class="unchanged">&nbsp;'.$this->formatEntity($glyphs[$p]).$this->formatEntity($SecondGlyph).'</span>';
									  if ($level==2 && $exL) { $html .= $exL; }
									  $html .= '&nbsp; &raquo; &raquo; &nbsp;';
									  if ($level==2 && $exB) { $html .= $exB; }
									  $html .= '<span class="changed" style="font-feature-settings:\''.$tag.'\' 1;">&nbsp;'.$this->formatEntity($glyphs[$p]).$this->formatEntity($SecondGlyph).'</span>';
									  if ($level==2 && $exL) { $html .= $exL; }
									  $html .= ' <span class="unicode">';
									  if ($Value1['XPlacement']) { $html .= ' Xpl[1]: '.$Value1['XPlacement'].';'; }
									  if ($Value1['YPlacement']) { $html .= ' YPl[1]: '.$Value1['YPlacement'].';'; }
									  if ($Value1['XAdvance']) { $html .= ' Xadv[1]: '.$Value1['XAdvance']; }
									  if ($Value2['XPlacement']) { $html .= ' Xpl[2]: '.$Value2['XPlacement'].';'; }
									  if ($Value2['YPlacement']) { $html .= ' YPl[2]: '.$Value2['YPlacement'].';'; }
									  if ($Value2['XAdvance']) { $html .= ' Xadv[2]: '.$Value2['XAdvance']; }
									  $html .= '</span>';
									  $html .= '</div>';
									 */
								}
								$html .= '</div>';
							}
						}
						// Format 2:
						else {
							if ($PosFormat == 2) {
								$ClassDef1 = $subtable_offset + $this->reader->readUInt16();
								$ClassDef2 = $subtable_offset + $this->reader->readUInt16();
								$Class1Count = $this->reader->readUInt16();
								$Class2Count = $this->reader->readUInt16();

								$sizeOfPair = ValueRecord::size($ValueFormat1) + ValueRecord::size($ValueFormat2);
								$sizeOfValueRecords = $Class1Count * $Class2Count * $sizeOfPair;

								// NB Class1Count includes Class 0 even though it is not defined by $ClassDef1
								// i.e. Class1Count = 5; Class1 will contain array(indices 1-4);
								$Class1 = $this->_getClassDefinitionTable($ClassDef1);
								$Class2 = $this->_getClassDefinitionTable($ClassDef2);

								$this->reader->seek($subtable_offset + 16);

								for ($i = 0; $i < $Class1Count; $i++) {
									for ($j = 0; $j < $Class2Count; $j++) {
										$Value1 = $this->valueRecord($ValueFormat1);
										$Value2 = $this->valueRecord($ValueFormat2);

										// If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180
										// of direction. mPDF does not need the XPlacement adjustment
										if ($dir == 'RTL' && $Value1['XPlacement'] && $Value1['XAdvance']) {
											$Value1['XPlacement'] -= $Value1['XAdvance'];
										}
										if ($ValueFormat2) {
											if ($dir == 'RTL' && $Value2['XPlacement'] && $Value2['XAdvance']) {
												$Value2['XPlacement'] -= $Value2['XAdvance'];
											}
										}

										// Class1Count counts class 0, which ClassDef1 does not define, and a font may
										// leave any other class empty too. Otl guards both the same way; this copy
										// indexed straight in and killed the dump on the first font with a gap.
										if (!isset($Class1[$i]) || !isset($Class2[$j])) {
											continue;
										}

										for ($c1 = 0; $c1 < count($Class1[$i]); $c1++) {
											$FirstGlyph = $Class1[$i][$c1];
											if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $FirstGlyph)) {
												continue;
											}

											for ($c2 = 0; $c2 < count($Class2[$j]); $c2++) {
												$SecondGlyph = $Class2[$j][$c2];

												if (!$Value1['XPlacement'] && !$Value1['YPlacement'] && !$Value1['XAdvance'] && !$Value2['XPlacement'] && !$Value2['YPlacement'] && !$Value2['XAdvance']) {
													continue;
												}

												$this->flushReport($html);
												$html .= '<div class="substitution">';
												$html .= '<span class="unicode">' . $this->formatUni($FirstGlyph) . '&nbsp;</span> ';
												if ($level == 2 && $exB) {
													$html .= $exB;
												}
												$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($FirstGlyph) . $this->formatEntity($SecondGlyph) . '</span>';
												if ($level == 2 && $exL) {
													$html .= $exL;
												}
												$html .= '&nbsp; &raquo; &raquo; &nbsp;';
												if ($level == 2 && $exB) {
													$html .= $exB;
												}
												$html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->formatEntity($FirstGlyph) . $this->formatEntity($SecondGlyph) . '</span>';
												if ($level == 2 && $exL) {
													$html .= $exL;
												}
												$html .= ' <span class="unicode">';
												if ($Value1['XPlacement']) {
													$html .= ' Xpl[1]: ' . $Value1['XPlacement'] . ';';
												}
												if ($Value1['YPlacement']) {
													$html .= ' YPl[1]: ' . $Value1['YPlacement'] . ';';
												}
												if ($Value1['XAdvance']) {
													$html .= ' Xadv[1]: ' . $Value1['XAdvance'];
												}
												if ($Value2['XPlacement']) {
													$html .= ' Xpl[2]: ' . $Value2['XPlacement'] . ';';
												}
												if ($Value2['YPlacement']) {
													$html .= ' YPl[2]: ' . $Value2['YPlacement'] . ';';
												}
												if ($Value2['XAdvance']) {
													$html .= ' Xadv[2]: ' . $Value2['XAdvance'];
												}
												$html .= '</span>';
												$html .= '</div>';
											}
										}
									}
								}
							}
						}
					}
					// LookupType 3: Cursive attachment 	Attach cursive glyphs
					else {
						if ($Lookup[$luli]['Type'] == 3) {
							$html .= '<div class="lookuptype">LookupType 3: Cursive attachment </div>';
							$Coverage = $subtable_offset + $this->reader->readUInt16();
							$EntryExitCount = $this->reader->readUInt16();
							$EntryAnchors = [];
							$ExitAnchors = [];
							for ($i = 0; $i < $EntryExitCount; $i++) {
								$EntryAnchors[$i] = $this->reader->readUInt16();
								$ExitAnchors[$i] = $this->reader->readUInt16();
							}

							$this->reader->seek($Coverage);
							$Glyphs = $this->_getCoverage();
							for ($i = 0; $i < $EntryExitCount; $i++) {
								// Need default XAdvance for glyph
								$pdfWidth = $this->mpdf->_getCharWidth($this->mpdf->fonts[$this->fontkey]['cw'], hexdec($Glyphs[$i]));
								$EntryAnchor = $EntryAnchors[$i];
								$ExitAnchor = $ExitAnchors[$i];
								$html .= '<div class="glyphs">';
								$html .= '<span class="unchanged">' . $this->formatEntity($Glyphs[$i]) . ' </span> ';
								$html .= '<span class="unicode"> ' . $this->formatUni($Glyphs[$i]) . ' => ';

								if ($EntryAnchor != 0) {
									$EntryAnchor += $subtable_offset;
									list($x, $y) = Anchor::coordinates($this->reader, $EntryAnchor);
									if ($dir == 'RTL') {
										if (round($pdfWidth) == round($x * 1000 / $this->unitsPerEm)) {
											$x = 0;
										} else {
											$x = $x - ($pdfWidth * $this->unitsPerEm / 1000);
										}
									}
									$html .= " Entry X: " . $x . " Y: " . $y . "; ";
								}
								if ($ExitAnchor != 0) {
									$ExitAnchor += $subtable_offset;
									list($x, $y) = Anchor::coordinates($this->reader, $ExitAnchor);
									if ($dir == 'LTR') {
										if (round($pdfWidth) == round($x * 1000 / $this->unitsPerEm)) {
											$x = 0;
										} else {
											$x = $x - ($pdfWidth * $this->unitsPerEm / 1000);
										}
									}
									$html .= " Exit X: " . $x . " Y: " . $y . "; ";
								}

								$html .= '</span></div>';
							}
						}
						// LookupType 4: MarkToBase attachment 	Attach a combining mark to a base glyph
						else {
							if ($Lookup[$luli]['Type'] == 4) {
								$html .= '<div class="lookuptype">LookupType 4: MarkToBase attachment </div>';
								$MarkCoverage = $subtable_offset + $this->reader->readUInt16();
								$BaseCoverage = $subtable_offset + $this->reader->readUInt16();

								$this->reader->seek($MarkCoverage);
								$MarkGlyphs = $this->_getCoverage();

								$this->reader->seek($BaseCoverage);
								$BaseGlyphs = $this->_getCoverage();

								$firstMark = '';
								$html .= '<div class="glyphs">Marks: ';
								for ($i = 0; $i < count($MarkGlyphs); $i++) {
									if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $MarkGlyphs[$i])) {
										continue;
									} else {
										if (!$firstMark) {
											$firstMark = $MarkGlyphs[$i];
										}
									}
									$html .= ' ' . $this->formatEntity($MarkGlyphs[$i]) . ' ';
								}
								$html .= '</div>';
								if (!$firstMark) {
									return;
								}

								$html .= '<div class="glyphs">Bases: ';
								for ($j = 0; $j < count($BaseGlyphs); $j++) {
									$html .= ' ' . $this->formatEntity($BaseGlyphs[$j]) . ' ';
								}
								$html .= '</div>';

								// Example
								$html .= '<div class="glyphs" style="font-feature-settings:\'' . $tag . '\' 1;">Example(s): ';
								for ($j = 0; $j < min(count($BaseGlyphs), 20); $j++) {
									$html .= ' ' . $this->formatEntity($BaseGlyphs[$j]) . $this->formatEntity($firstMark, true) . ' &nbsp; ';
								}
								$html .= '</div>';
							}
							// LookupType 5: MarkToLigature attachment 	Attach a combining mark to a ligature
							else {
								if ($Lookup[$luli]['Type'] == 5) {
									$html .= '<div class="lookuptype">LookupType 5: MarkToLigature attachment </div>';
									$MarkCoverage = $subtable_offset + $this->reader->readUInt16();
									//$MarkCoverage is already set in $lcoverage 00065|00073 etc
									$LigatureCoverage = $subtable_offset + $this->reader->readUInt16();
									$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
									$MarkArray = $subtable_offset + $this->reader->readUInt16(); // Offset to MarkArray table
									$LigatureArray = $subtable_offset + $this->reader->readUInt16(); // Offset to LigatureArray table

									$this->reader->seek($MarkCoverage);
									$MarkGlyphs = $this->_getCoverage();
									$this->reader->seek($LigatureCoverage);
									$LigatureGlyphs = $this->_getCoverage();

									$firstMark = '';
									$html .= '<div class="glyphs">Marks: <span class="unchanged">';
									$MarkRecord = [];
									for ($i = 0; $i < count($MarkGlyphs); $i++) {
										if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $MarkGlyphs[$i])) {
											continue;
										} else {
											if (!$firstMark) {
												$firstMark = $MarkGlyphs[$i];
											}
										}
										// Get the relevant MarkRecord
										$MarkRecord[$i] = MarkArray::record($this->reader, $MarkArray, $i);
										//Mark Class is = $MarkRecord[$i]['Class']
										$html .= ' ' . $this->formatEntity($MarkGlyphs[$i]) . ' ';
									}
									$html .= '</span></div>';
									if (!$firstMark) {
										return;
									}

									$this->reader->seek($LigatureArray);
									$LigatureCount = $this->reader->readUInt16();
									$LigatureAttach = [];
									$html .= '<div class="glyphs">Ligatures: <span class="unchanged">';
									for ($j = 0; $j < count($LigatureGlyphs); $j++) {
										// Get the relevant LigatureRecord
										$LigatureAttach[$j] = $LigatureArray + $this->reader->readUInt16();
										$html .= ' ' . $this->formatEntity($LigatureGlyphs[$j]) . ' ';
									}
									$html .= '</span></div>';

									/*
									  for ($i=0;$i<count($MarkGlyphs);$i++) {
									  $html .= '<div class="glyphs">';
									  $html .= '<span class="unchanged">'.$this->formatEntity($MarkGlyphs[$i]).'</span>';

									  for ($j=0;$j<count($LigatureGlyphs);$j++) {
									  $this->reader->seek($LigatureAttach[$j]);
									  $ComponentCount = $this->reader->readUInt16();
									  $html .= '<span class="unchanged">'.$this->formatEntity($LigatureGlyphs[$j]).'</span>';
									  $offsets = array();
									  for ($comp=0;$comp<$ComponentCount;$comp++) {
									  // ComponentRecords
									  for ($class=0;$class<$ClassCount;$class++) {
									  $offset = $this->reader->readUInt16();
									  if ($offset!= 0 && $class == $MarkRecord[$i]['Class']) {

									  $html .= ' ['.$comp.'] ';

									  }
									  }
									  }
									  }
									  $html .= '</span></div>';
									  }
									 */
								}
								// LookupType 6: MarkToMark attachment 	Attach a combining mark to another mark
								else {
									if ($Lookup[$luli]['Type'] == 6) {
										$html .= '<div class="lookuptype">LookupType 6: MarkToMark attachment </div>';
										$Mark1Coverage = $subtable_offset + $this->reader->readUInt16(); // Combining Mark
										//$Mark1Coverage is already set in $LuCoverage 0065|0073 etc
										$Mark2Coverage = $subtable_offset + $this->reader->readUInt16(); // Base Mark
										$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = No. of Combining mark1 glyphs in the MarkCoverage table
										$this->reader->seek($Mark1Coverage);
										$Mark1Glyphs = $this->_getCoverage();
										$this->reader->seek($Mark2Coverage);
										$Mark2Glyphs = $this->_getCoverage();

										$firstMark = '';
										$html .= '<div class="glyphs">Marks: <span class="unchanged">';
										for ($i = 0; $i < count($Mark1Glyphs); $i++) {
											if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $Mark1Glyphs[$i])) {
												continue;
											} else {
												if (!$firstMark) {
													$firstMark = $Mark1Glyphs[$i];
												}
											}
											$html .= ' ' . $this->formatEntity($Mark1Glyphs[$i]) . ' ';
										}
										$html .= '</span></div>';

										if ($firstMark) {
											$html .= '<div class="glyphs">Bases: <span class="unchanged">';
											for ($j = 0; $j < count($Mark2Glyphs); $j++) {
												$html .= ' ' . $this->formatEntity($Mark2Glyphs[$j]) . ' ';
											}
											$html .= '</span></div>';

											// Example
											$html .= '<div class="glyphs" style="font-feature-settings:\'' . $tag . '\' 1;">Example(s): <span class="changed">';
											for ($j = 0; $j < min(count($Mark2Glyphs), 20); $j++) {
												$html .= ' ' . $this->formatEntity($Mark2Glyphs[$j]) . $this->formatEntity($firstMark, true) . ' &nbsp; ';
											}
											$html .= '</span></div>';
										}
									} else {
										if ($Lookup[$luli]['Type'] == 7) {
											$html .= '<div class="lookuptype">LookupType 7: Context positioning [Format ' . $PosFormat . ']</div>';
											$this->reportGPOScontextPos($Lookup, $subtable_offset, $PosFormat, $tag, $scripttag);
										} elseif ($Lookup[$luli]['Type'] == 8) {
											$html .= '<div class="lookuptype">LookupType 8: Chained Context positioning [Format ' . $PosFormat . ']</div>';
											$this->reportGPOSchainContextPos($Lookup, $subtable_offset, $PosFormat, $tag, $scripttag);
										}
									}
								}
							}
						}
					}
				}
			}
			$html .= '</div>';
			$this->flushReport($html);
		}
		if ($level == 1 && $html !== '') {
			$this->mpdf->WriteHTML($html);
			$html = '';
		}

		return '';
	}

	/**
	 * LookupType 7: Context positioning - position one or more glyphs in context.
	 *
	 * The GPOS counterpart of GSUB's Type 5, and read the same way: a rule matches a run of glyphs
	 * and then hands named positions within it to other lookups, which do the positioning. All three
	 * formats are reported by walking every rule and reporting the nested lookup under the context it
	 * fires in - which is what makes the report worth reading, because the lookup on its own says
	 * nothing about when it applies.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#contextual-positioning-subtables
	 *
	 * Every one of these appends to $this->report rather than returning a string, because a nested
	 * lookup reported at level 2 writes into that same buffer itself: a caller that built its own
	 * string and appended it afterwards would put the rule and the lookup it runs in the wrong order.
	 */
	private function reportGPOScontextPos(array $Lookup, $subtable_offset, $PosFormat, $tag, $scripttag)
	{
		if ($PosFormat == 1) {
			$this->reportGPOScontextPosFormat1($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 2) {
			$this->reportGPOScontextPosFormat2($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 3) {
			$this->reportGPOScontextPosFormat3($Lookup, $subtable_offset, $tag, $scripttag);
		} else {
			throw new \Mpdf\Exception\FontException(sprintf('GPOS Lookup Type 7, Format "%s" not supported.', $PosFormat));
		}
	}

	/**
	 * Format 1: the rules list the glyphs they match one by one.
	 *
	 * Rules are grouped into a PosRuleSet per first glyph, and which set is which is given by the
	 * position of that glyph in the subtable's Coverage table.
	 */
	private function reportGPOScontextPosFormat1(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 1: Context Positioning</div>';

		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$PosRuleSetCount = $this->reader->readUInt16();
		$PosRuleSetOffset = [];
		for ($s = 0; $s < $PosRuleSetCount; $s++) {
			$PosRuleSetOffset[$s] = $this->reader->readUInt16();
		}

		$this->reader->seek($CoverageTableOffset);
		$CoverageGlyphs = $this->_getCoverage();

		for ($s = 0; $s < $PosRuleSetCount; $s++) {
			// A PosRuleSet offset of 0 means no context begins with that glyph
			if (!$PosRuleSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Pos Rule Set: ' . $s . '</div>';

			$PosRuleSet = $subtable_offset + $PosRuleSetOffset[$s];
			$this->reader->seek($PosRuleSet);
			$PosRuleCount = $this->reader->readUInt16();
			$PosRule = [];
			for ($b = 0; $b < $PosRuleCount; $b++) {
				$PosRule[$b] = $PosRuleSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $PosRuleCount; $b++) {
				$this->report .= '<div class="rule">PosRule: ' . $b . '</div>';
				$this->reader->seek($PosRule[$b]);
				list($inputGlyphIDs, $PosCount) = SequenceRule::plain($this->reader);

				// Position 0 is the glyph the Coverage table selected this rule set by, so the rule
				// itself lists one fewer than it counts
				$inputGlyphs = array_merge(
					[isset($CoverageGlyphs[$s]) ? $CoverageGlyphs[$s] : ''],
					$this->glyphNames($inputGlyphIDs)
				);

				$records = SequenceRule::lookupRecords($this->reader, $PosCount);

				$this->reportGPOSrule($Lookup, $records, [], $inputGlyphs, [], '', '', '', $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 2: the rules match classes of glyphs rather than glyphs.
	 *
	 * The rule set array is indexed by the class of the first input glyph, so the loop index over it
	 * is that class.
	 */
	private function reportGPOScontextPosFormat2(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 2: Class-based Context Positioning</div>';

		$this->reader->readUInt16(); // coverageOffset, which class 0 stands in for below
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$PosClassSetCnt = $this->reader->readUInt16();
		$PosClassSetOffset = [];
		for ($b = 0; $b < $PosClassSetCnt; $b++) {
			$PosClassSetOffset[$b] = $this->reader->readUInt16();
		}

		$InputClasses = $this->_getClasses($InputClassDefOffset);

		// Class 0 is every glyph that is in none of the other classes, so it is reported as what it
		// excludes rather than as a list
		$class0excl = implode('|', $InputClasses);

		for ($s = 0; $s < $PosClassSetCnt; $s++) {
			// A PosClassSet offset of 0 means no context begins with a glyph of that class
			if (!$PosClassSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Input Class: ' . $s . '</div>';

			$PosClassSet = $subtable_offset + $PosClassSetOffset[$s];
			$this->reader->seek($PosClassSet);
			$PosClassRuleCnt = $this->reader->readUInt16();
			$PosClassRule = [];
			for ($b = 0; $b < $PosClassRuleCnt; $b++) {
				$PosClassRule[$b] = $PosClassSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $PosClassRuleCnt; $b++) {
				$this->report .= '<div class="rule">Rule: ' . $b . '</div>';
				$this->reader->seek($PosClassRule[$b]);
				list($inputClassIndices, $PosCount) = SequenceRule::plain($this->reader);

				$inputGlyphs = array_merge(
					[$this->classGlyphs($InputClasses, $s)],
					$this->classNames($InputClasses, $inputClassIndices)
				);

				$records = SequenceRule::lookupRecords($this->reader, $PosCount);

				$this->reportGPOSrule($Lookup, $records, [], $inputGlyphs, [], $class0excl, '', '', $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 3: one Coverage table per input position, and one rule.
	 *
	 * Unlike Type 8 Format 3, the count of positionings precedes the Coverage table offsets.
	 */
	private function reportGPOScontextPosFormat3(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 3: Coverage-based Context Positioning</div>';

		$InputGlyphCount = $this->reader->readUInt16();
		$PosCount = $this->reader->readUInt16();
		$inputOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $InputGlyphCount);
		$records = SequenceRule::lookupRecords($this->reader, $PosCount);

		$this->reportGPOSrule($Lookup, $records, [], $this->coverageGlyphs($inputOffsets), [], '', '', '', $tag, $scripttag);
	}

	/**
	 * LookupType 8: Chained context positioning - Type 7 with a backtrack and a lookahead sequence
	 * either side of the input.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#chained-contexts-positioning-subtable
	 */
	private function reportGPOSchainContextPos(array $Lookup, $subtable_offset, $PosFormat, $tag, $scripttag)
	{
		if ($PosFormat == 1) {
			$this->reportGPOSchainContextPosFormat1($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 2) {
			$this->reportGPOSchainContextPosFormat2($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 3) {
			$this->reportGPOSchainContextPosFormat3($Lookup, $subtable_offset, $tag, $scripttag);
		} else {
			throw new \Mpdf\Exception\FontException(sprintf('GPOS Lookup Type 8, Format "%s" not supported.', $PosFormat));
		}
	}

	/**
	 * Format 1: the rules list the glyphs of all three sequences one by one. @see reportGPOScontextPosFormat1
	 */
	private function reportGPOSchainContextPosFormat1(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 1: Simple Chaining Context Positioning</div>';

		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainPosRuleSetCount = $this->reader->readUInt16();
		$ChainPosRuleSetOffset = [];
		for ($s = 0; $s < $ChainPosRuleSetCount; $s++) {
			$ChainPosRuleSetOffset[$s] = $this->reader->readUInt16();
		}

		$this->reader->seek($CoverageTableOffset);
		$CoverageGlyphs = $this->_getCoverage();

		for ($s = 0; $s < $ChainPosRuleSetCount; $s++) {
			if (!$ChainPosRuleSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Chain Pos Rule Set: ' . $s . '</div>';

			$ChainPosRuleSet = $subtable_offset + $ChainPosRuleSetOffset[$s];
			$this->reader->seek($ChainPosRuleSet);
			$ChainPosRuleCount = $this->reader->readUInt16();
			$ChainPosRule = [];
			for ($b = 0; $b < $ChainPosRuleCount; $b++) {
				$ChainPosRule[$b] = $ChainPosRuleSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $ChainPosRuleCount; $b++) {
				$this->report .= '<div class="rule">ChainPosRule: ' . $b . '</div>';
				$this->reader->seek($ChainPosRule[$b]);

				list($backtrackGlyphIDs, $inputGlyphIDs, $lookaheadGlyphIDs) = SequenceRule::chained($this->reader);

				$backtrackGlyphs = $this->glyphNames($backtrackGlyphIDs);
				$inputGlyphs = array_merge(
					[isset($CoverageGlyphs[$s]) ? $CoverageGlyphs[$s] : ''],
					$this->glyphNames($inputGlyphIDs)
				);
				$lookaheadGlyphs = $this->glyphNames($lookaheadGlyphIDs);

				$records = SequenceRule::lookupRecords($this->reader, $this->reader->readUInt16());

				$this->reportGPOSrule($Lookup, $records, $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, '', '', '', $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 2: the rules match classes, with a class definition of its own for each of the three
	 * sequences. @see reportGPOScontextPosFormat2
	 */
	private function reportGPOSchainContextPosFormat2(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 2: Class-based Chaining Context Positioning</div>';

		$this->reader->readUInt16(); // coverageOffset, which class 0 stands in for below
		$BacktrackClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$LookaheadClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainPosClassSetCnt = $this->reader->readUInt16();
		$ChainPosClassSetOffset = [];
		for ($b = 0; $b < $ChainPosClassSetCnt; $b++) {
			$ChainPosClassSetOffset[$b] = $this->reader->readUInt16();
		}

		$BacktrackClasses = $this->_getClasses($BacktrackClassDefOffset);
		$InputClasses = $this->_getClasses($InputClassDefOffset);
		$LookaheadClasses = $this->_getClasses($LookaheadClassDefOffset);

		// Class 0 is every glyph in none of the other classes of its own ClassDef, and a chained
		// context has three of them, so each sequence is told what its own class 0 excludes
		$class0excl = implode('|', $InputClasses);
		$bclass0excl = implode('|', $BacktrackClasses);
		$lclass0excl = implode('|', $LookaheadClasses);

		for ($s = 0; $s < $ChainPosClassSetCnt; $s++) {
			if (!$ChainPosClassSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Input Class: ' . $s . '</div>';

			$ChainPosClassSet = $subtable_offset + $ChainPosClassSetOffset[$s];
			$this->reader->seek($ChainPosClassSet);
			$ChainPosClassRuleCnt = $this->reader->readUInt16();
			$ChainPosClassRule = [];
			for ($b = 0; $b < $ChainPosClassRuleCnt; $b++) {
				$ChainPosClassRule[$b] = $ChainPosClassSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $ChainPosClassRuleCnt; $b++) {
				$this->report .= '<div class="rule">Rule: ' . $b . '</div>';
				$this->reader->seek($ChainPosClassRule[$b]);

				list($backtrackClassIndices, $inputClassIndices, $lookaheadClassIndices) = SequenceRule::chained($this->reader);

				$backtrackGlyphs = $this->classNames($BacktrackClasses, $backtrackClassIndices);
				$inputGlyphs = array_merge(
					[$this->classGlyphs($InputClasses, $s)],
					$this->classNames($InputClasses, $inputClassIndices)
				);
				$lookaheadGlyphs = $this->classNames($LookaheadClasses, $lookaheadClassIndices);

				$records = SequenceRule::lookupRecords($this->reader, $this->reader->readUInt16());

				$this->reportGPOSrule($Lookup, $records, $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 3: one Coverage table per position of all three sequences, and one rule.
	 */
	private function reportGPOSchainContextPosFormat3(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 3: Coverage-based Chaining Context Positioning</div>';

		$backtrackOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$inputOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$lookaheadOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$records = SequenceRule::lookupRecords($this->reader, $this->reader->readUInt16());

		$this->reportGPOSrule(
			$Lookup,
			$records,
			$this->coverageGlyphs($backtrackOffsets),
			$this->coverageGlyphs($inputOffsets),
			$this->coverageGlyphs($lookaheadOffsets),
			'',
			'',
			'',
			$tag,
			$scripttag
		);
	}

	/**
	 * The CONTEXT block of one rule: the sequences it matches, a line per position.
	 *
	 * Every contextual and chaining format of both tables renders this, and there were seven copies
	 * of it - six written for GSUB, and the seventh added for GPOS by #90 to the shape the six
	 * already had.
	 *
	 * A plain context has no backtrack or lookahead and passes empty arrays for them. A rule that
	 * names glyphs rather than classes passes no exclusions, and every position then reads as the
	 * glyphs it holds.
	 *
	 * Backtrack is held in glyph sequence order, which runs away from the input rather than towards
	 * it, so it is reported last position first - the order the text reads in.
	 *
	 * @param string $class0excl  Every glyph in some class of the input sequence's Class Definition,
	 *                            so that a position naming class 0 reads as what it excludes rather
	 *                            than as a list. Empty where the rule names glyphs.
	 * @param string $bclass0excl Likewise for the backtrack sequence, $lclass0excl for lookahead
	 *
	 * @return array [$exampleB, $exampleI, $exampleL]: one fragment per position of each sequence,
	 *               which is what the nested lookups are then shown against
	 */
	private function reportContext(array $backtrackGlyphs, array $inputGlyphs, array $lookaheadGlyphs, $class0excl = '', $bclass0excl = '', $lclass0excl = '')
	{
		$exampleB = [];
		$exampleI = [];
		$exampleL = [];

		$this->report .= '<div class="context">CONTEXT: ';

		for ($ff = count($backtrackGlyphs) - 1; $ff >= 0; $ff--) {
			if ($bclass0excl !== '' && !$backtrackGlyphs[$ff]) {
				$this->report .= '<div>Backtrack #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->formatEntityStr($bclass0excl) . ']&nbsp;</span></div>';
				$exampleB[] = '[NOT ' . $this->formatEntityFirst($bclass0excl) . ']';
			} else {
				$this->report .= '<div>Backtrack #' . $ff . ': <span class="unicode">' . $this->formatUniStr($backtrackGlyphs[$ff]) . '</span></div>';
				$exampleB[] = $this->formatEntityFirst($backtrackGlyphs[$ff]);
			}
		}

		for ($ff = 0; $ff < count($inputGlyphs); $ff++) {
			if ($class0excl !== '' && !$inputGlyphs[$ff]) {
				$this->report .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->formatEntityStr($class0excl) . ']&nbsp;</span></div>';
				$exampleI[] = '[NOT ' . $this->formatEntityFirst($class0excl) . ']';
			} else {
				$this->report .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->formatEntityStr($inputGlyphs[$ff]) . '&nbsp;</span></div>';
				$exampleI[] = $this->formatEntityFirst($inputGlyphs[$ff]);
			}
		}

		for ($ff = 0; $ff < count($lookaheadGlyphs); $ff++) {
			if ($lclass0excl !== '' && !$lookaheadGlyphs[$ff]) {
				$this->report .= '<div>Lookahead #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->formatEntityStr($lclass0excl) . ']&nbsp;</span></div>';
				$exampleL[] = '[NOT ' . $this->formatEntityFirst($lclass0excl) . ']';
			} else {
				$this->report .= '<div>Lookahead #' . $ff . ': <span class="unicode">' . $this->formatUniStr($lookaheadGlyphs[$ff]) . '</span></div>';
				$exampleL[] = $this->formatEntityFirst($lookaheadGlyphs[$ff]);
			}
		}

		$this->report .= '</div>';

		return [$exampleB, $exampleI, $exampleL];
	}

	/**
	 * The example a nested lookup's own output is rendered between: everything the rule matches
	 * before the position it is handed, and everything it matches after it.
	 *
	 * The zero-width joiner separates a fragment from that output, so it trails every fragment
	 * before the position and leads every fragment after it.
	 *
	 * @param array $exampleB One fragment per backtrack position, as reportContext() returned them;
	 *                        likewise $exampleI for the input sequence and $exampleL for lookahead
	 * @param int   $seqIndex The input position the nested lookup is handed
	 *
	 * @return array [$exB, $exL]
	 */
	private function contextExample(array $exampleB, array $exampleI, array $exampleL, $seqIndex)
	{
		$exB = '';
		$exL = '';

		if (count($exampleB)) {
			$exB .= '<span class="backtrack">' . implode('&#x200d;', $exampleB) . '</span>';
		}

		if ($seqIndex > 0) {
			$exB .= '<span class="inputother">' . implode('&#x200d;', array_slice($exampleI, 0, $seqIndex)) . '&#x200d;</span>';
		}

		if (count($exampleI) > ($seqIndex + 1)) {
			$exL .= '<span class="inputother">&#x200d;' . implode('&#x200d;', array_slice($exampleI, $seqIndex + 1)) . '</span>';
		}

		if (count($exampleL)) {
			$exL .= '<span class="lookahead">' . implode('&#x200d;', $exampleL) . '</span>';
		}

		return [$exB, $exL];
	}

	/**
	 * Whether the position a nested lookup was handed can hold the glyph one of its rules reads.
	 *
	 * A position naming class 0 holds every glyph its Class Definition leaves unnamed, and the dump
	 * has no list of those - _getClasses() only walks the pairs the table names. So the test there is
	 * against the complement of $class0excl, the same set reportContext() renders the position as.
	 *
	 * @param string $coverage   The glyphs that position holds, empty where it names class 0
	 * @param string $class0excl Every glyph in some class of that sequence's Class Definition, or
	 *                           empty where the rule names glyphs rather than classes
	 */
	private function positionHolds($coverage, $class0excl, $glyph)
	{
		if ($coverage === '' && $class0excl !== '') {
			return strpos($class0excl, $glyph) === false;
		}

		return strpos($coverage, $glyph) !== false;
	}

	/**
	 * One context rule: the sequences it matches, and every lookup it hands a position within them.
	 *
	 * @param array  $PosLookupRecord Each a SequenceIndex and a LookupListIndex, already read: where
	 *                                they sit relative to a rule's own sequences differs by format
	 * @param array  $backtrackGlyphs In glyph sequence order, so reported last first
	 * @param string $class0excl      Every glyph that is in some class of the input sequence's Class
	 *                                Definition, for a class-based rule, so that class 0 reads as what
	 *                                it excludes. Empty for a glyph list, as are the other two.
	 * @param string $bclass0excl     Likewise for the backtrack sequence, $lclass0excl for lookahead
	 */
	private function reportGPOSrule(array $Lookup, array $PosLookupRecord, array $backtrackGlyphs, array $inputGlyphs, array $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag)
	{
		list($exampleB, $exampleI, $exampleL) = $this->reportContext($backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl);

		foreach ($PosLookupRecord as $record) {
			$seqIndex = $record['SequenceIndex'];

			list($exB, $exL) = $this->contextExample($exampleB, $exampleI, $exampleL, $seqIndex);

			$this->report .= '<div class="sequenceIndex">Substitution Position: ' . $seqIndex . '</div>';

			$this->_getGPOSarray($Lookup, [$record['LookupListIndex'] => $tag], $scripttag, 2, $inputGlyphs[$seqIndex], $exB, $exL, $class0excl);
		}
	}

	/**
	 * One context rule: the sequences it matches, and every lookup it hands a position within them.
	 *
	 * @param array $SubstLookupRecord As substLookupRecords() normalised them
	 *
	 * @see reportGPOSrule() for the rest of the parameters, which are the same ones
	 */
	private function reportGSUBrule(array $Lookup, array $SubstLookupRecord, array $backtrackGlyphs, array $inputGlyphs, array $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag)
	{
		list($exampleB, $exampleI, $exampleL) = $this->reportContext($backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl);

		foreach ($SubstLookupRecord as $record) {
			$seqIndex = $record['SequenceIndex'];

			list($exB, $exL) = $this->contextExample($exampleB, $exampleI, $exampleL, $seqIndex);

			$this->report .= '<div class="sequenceIndex">Substitution Position: ' . $seqIndex . '</div>';

			// The position's own glyphs, e.g. 00636|00645|00656, are what level 2 filters its rules on
			$this->_getGSUBarray($Lookup, [$record['LookupListIndex'] => $tag], $scripttag, 2, $inputGlyphs[$seqIndex], $exB, $exL, $class0excl);
		}
	}

	/**
	 * The lookup records of one context rule, whichever of the two shapes the parser wrote them in:
	 * an array of records, or a SequenceIndex array and a LookupListIndex array side by side.
	 *
	 * @param array $rule The rule, or the subtable itself for a format whose subtable is its own
	 *                    only rule and keeps the records
	 *
	 * @see \Mpdf\Fonts\Table\SequenceRule::lookupRecords() for the shape both are read as
	 */
	private function substLookupRecords(array $rule)
	{
		if (isset($rule['SubstLookupRecord'])) {
			return $rule['SubstLookupRecord'];
		}

		$records = [];
		for ($b = 0; $b < $rule['SubstCount']; $b++) {
			$records[] = [
				'SequenceIndex' => $rule['SequenceIndex'][$b],
				'LookupListIndex' => $rule['LookupListIndex'][$b],
			];
		}

		return $records;
	}

	/**
	 * @return string[] One "hex|hex|hex" string of the alternatives each Coverage table holds
	 */
	private function coverageGlyphs(array $offsets)
	{
		$glyphs = [];
		foreach ($offsets as $b => $offset) {
			$this->reader->seek($offset);
			$glyphs[$b] = implode('|', $this->_getCoverage());
		}

		return $glyphs;
	}

	/**
	 * What SequenceRule read as glyph ids, as the report names glyphs.
	 *
	 * @param int[] $glyphIDs In glyph sequence order
	 *
	 * @return string[] One hex character per position
	 */
	private function glyphNames(array $glyphIDs)
	{
		$names = [];
		foreach ($glyphIDs as $glyphID) {
			$names[] = $this->glyphHex($glyphID);
		}

		return $names;
	}

	/**
	 * What SequenceRule read as class numbers, as the report names the glyphs of a class.
	 *
	 * @param array $classes      class => "hex|hex|hex", as _getClasses returns it
	 * @param int[] $classIndices The class each position names, in glyph sequence order
	 *
	 * @return string[] One "hex|hex|hex" string per position, empty where the class is 0 or unnamed
	 */
	private function classNames(array $classes, array $classIndices)
	{
		$names = [];
		foreach ($classIndices as $class) {
			$names[] = $this->classGlyphs($classes, $class);
		}

		return $names;
	}

	/**
	 * @return string The character a glyph id stands for, as hex, or '' where the cmap does not reach it
	 */
	private function glyphHex($glyphID)
	{
		return isset($this->glyphToChar[$glyphID][0]) ? GlyphString::of($this->glyphToChar[$glyphID][0]) : '';
	}

	/**
	 * Hand over the report so far if it has built up more than WriteHTML will take.
	 *
	 * AdjustHTML refuses HTML longer than pcre.backtrack_limit, and one lookup can report tens of
	 * thousands of rules - a Latin font's GPOS kern lookup runs to ten megabytes on its own, so
	 * writing per lookup is not enough. Call this only where the report is between rows, so that
	 * every piece is whole elements; the enclosing div stays open across the calls, which the rest
	 * of the report does too - the summary opens a div in one call and closes it in another.
	 *
	 * @param string $html The report so far, emptied if it was handed over
	 */
	private function flushReport(&$html)
	{
		if (strlen($html) < $this->reportChunkBytes) {
			return;
		}

		$this->mpdf->WriteHTML($html);
		$html = '';
	}

	/**
	 * A link from the summary report to the detail report of one script and language system.
	 *
	 * These named font_dump_OTL.php, a spelling the file has never had, so following one 404s on any
	 * case-sensitive server; and they carried the script and language alone, losing the font the
	 * summary was of, so the detail report came back for whatever font the tool defaults to.
	 *
	 * @return string An href, with its ampersands escaped for HTML
	 */
	private function detailLink($script, $language)
	{
		$query = $this->detailReportQuery;
		$query['script'] = trim($script);
		$query['lang'] = trim($language);

		return 'font_dump_otl.php?' . htmlspecialchars(http_build_query($query), ENT_QUOTES);
	}

	/**
	 * The glyphs one class of a ClassDef holds, as the "|" separated string the report prints.
	 *
	 * Class 0 is every glyph the ClassDef does not mention, so a ClassDef never lists it and
	 * _getClasses never returns a key for it. A rule may still name it, and the report already
	 * renders an empty class as "[NOT <the other classes>]" - so that is what an unlisted class
	 * returns. Reading the key straight raised a warning per rule and then rendered the same thing
	 * from null.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2#class-definition-table
	 *
	 * @param array $classes class => glyphs, as _getClasses returns it
	 * @param int   $class   the class a rule names
	 *
	 * @return string
	 */
	private function classGlyphs($classes, $class)
	{
		return isset($classes[$class]) ? $classes[$class] : '';
	}

	/**
	 * The features one script and language system offers in GSUB or GPOS.
	 *
	 * A table with nothing for the script, or nothing for that language system within it, is
	 * reported and skipped rather than fatal. Fonts routinely substitute for a script without
	 * positioning it, or list a language system in one table only - 96 of the 245 script and
	 * language systems in the shipped fonts are in one table and not the other - and the half the
	 * reader asked for is in the other table. Only a script or language system that neither table
	 * carries is a mistake in the tag, and failIfNeitherTableOffers raises that once both have been
	 * asked. Before either, a missing script read straight through to a null a few lines later.
	 *
	 * @return array feature tag => list of lookup list indexes, empty if this table offers none
	 */
	private function langSys($features, $table)
	{
		if (!isset($features[$this->script])) {
			return $this->noteNotOffered(sprintf(
				'This font\'s %s table offers no script "%s". It has: %s',
				$table,
				trim($this->script),
				$features ? implode(', ', array_map('trim', array_keys($features))) : 'none'
			));
		}

		if (!isset($features[$this->script][$this->language])) {
			return $this->noteNotOffered(sprintf(
				'This font\'s %s script "%s" offers no language system "%s". It has: %s',
				$table,
				trim($this->script),
				trim($this->language),
				implode(', ', array_map('trim', array_keys($features[$this->script])))
			));
		}

		return $features[$this->script][$this->language];
	}

	/**
	 * Record, and show in the report, that one table has nothing for the script and language asked.
	 *
	 * @return array Always empty, so that the caller reports no lookups for this table
	 */
	private function noteNotOffered($message)
	{
		$this->notOffered[] = $message;
		$this->mpdf->WriteHTML('<div class="notoffered">' . $message . '</div>');

		return [];
	}

	/**
	 * Fail when neither GSUB nor GPOS carries the script and language system detail mode was asked
	 * for, naming what each table does carry. Called once both have been asked.
	 */
	private function failIfNeitherTableOffers()
	{
		if ($this->mode === 'detail' && count($this->notOffered) === 2) {
			throw new \Mpdf\MpdfException(implode("\n", $this->notOffered));
		}
	}

	/**
	 * A value record with all three of the fields mPDF uses present, zero where the format leaves
	 * one out.
	 *
	 * The shaper tells an absent field from a zero one, so ValueRecord::read() leaves it absent. The
	 * report reads all six of a pair unconditionally to decide what to print, and asking for absent
	 * keys raised tens of thousands of warnings on a single font.
	 */
	private function valueRecord($ValueFormat)
	{
		return array_merge(['XPlacement' => 0, 'YPlacement' => 0, 'XAdvance' => 0], ValueRecord::read($this->reader, $ValueFormat));
	}

	/**
	 * @param string $char A character as hex
	 *
	 * @return string It as "U+0041", or "M+E000" where it is in a Private Use Area and so stands for
	 *                a glyph the font reached only through a substitution
	 */
	function formatUni($char)
	{
		$x = preg_replace('/^[0]*/', '', $char);
		$x = str_pad($x, 4, '0', STR_PAD_LEFT);
		$d = hexdec($x);
		if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
			$id = 'M';
		} // E000 - F8FF, 1E000-1F000
		else {
			$id = 'U';
		}

		return $id . '+' . $x;
	}

	/**
	 * @param string $char         A character as hex
	 * @param bool   $allowjoining Whether a mark may be shown on its own. A mark otherwise gets a
	 *                             dotted circle to sit on, so that it renders where a base would be.
	 *
	 * @return string It as an HTML entity
	 */
	function formatEntity($char, $allowjoining = false)
	{
		$char = preg_replace('/^[0]/', '', $char);
		$x = '&#x' . $char . ';';
		if (strpos($this->GlyphClassMarks, $char) !== false) {
			if (!$allowjoining) {
				$x = '&#x25cc;' . $x;
			}
		}

		return $x;
	}

	/**
	 * @param array $arr Characters as hex
	 *
	 * @return string Them as comma-separated "U+0041" codes
	 */
	function formatUniArr($arr)
	{
		$s = [];
		foreach ($arr as $c) {
			$x = preg_replace('/^[0]*/', '', $c);
			$d = hexdec($x);
			if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
				$id = 'M';
			} // E000 - F8FF, 1E000-1F000
			else {
				$id = 'U';
			}
			$s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
		}

		return implode(', ', $s);
	}

	/**
	 * @param array $arr Characters as hex
	 *
	 * @return string Them as space-separated HTML entities, marks on dotted circles
	 */
	function formatEntityArr($arr)
	{
		$s = [];
		foreach ($arr as $c) {
			$c = preg_replace('/^[0]/', '', $c);
			$x = '&#x' . $c . ';';
			if (strpos($this->GlyphClassMarks, $c) !== false) {
				$x = '&#x25cc;' . $x;
			}
			$s[] = $x;
		}

		return implode(' ', $s); // ZWNJ? &#x200d;
	}

	/**
	 * @param array $arr The characters of one class, as hex
	 *
	 * @return string Them as comma-separated "U+0041" codes
	 */
	function formatClassArr($arr)
	{
		$s = [];
		foreach ($arr as $c) {
			$x = preg_replace('/^[0]*/', '', $c);
			$d = hexdec($x);
			if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
				$id = 'M';
			} // E000 - F8FF, 1E000-1F000
			else {
				$id = 'U';
			}
			$s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
		}

		return implode(', ', $s);
	}

	/**
	 * @param string $str A pipe-joined run of characters as hex, as the class and coverage readers
	 *                    hand them back
	 *
	 * @return string Them as comma-separated "U+0041" codes
	 */
	function formatUniStr($str)
	{
		$s = [];
		$arr = explode('|', $str);
		foreach ($arr as $c) {
			$x = preg_replace('/^[0]*/', '', $c);
			$d = hexdec($x);
			if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
				$id = 'M';
			} // E000 - F8FF, 1E000-1F000
			else {
				$id = 'U';
			}
			$s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
		}

		return implode(', ', $s);
	}

	/**
	 * @param string $str A pipe-joined run of characters as hex
	 *
	 * @return string Them as space-separated HTML entities, marks on dotted circles
	 */
	function formatEntityStr($str)
	{
		$s = [];
		$arr = explode('|', $str);
		foreach ($arr as $c) {
			$c = preg_replace('/^[0]/', '', $c);
			$x = '&#x' . $c . ';';
			if (strpos($this->GlyphClassMarks, $c) !== false) {
				$x = '&#x25cc;' . $x;
			}
			$s[] = $x;
		}

		return implode(' ', $s); // ZWNJ? &#x200d;
	}

	/**
	 * @param string $str A pipe-joined run of characters as hex
	 *
	 * @return string The first of them as an HTML entity. A position that can hold any of a set is
	 *                shown as one of them, so that the example reads as a word.
	 */
	function formatEntityFirst($str)
	{
		$arr = explode('|', $str);
		$char = preg_replace('/^[0]/', '', $arr[0]);
		$x = '&#x' . $char . ';';
		if (strpos($this->GlyphClassMarks, $char) !== false) {
			$x = '&#x25cc;' . $x;
		}

		return $x;
	}

}
