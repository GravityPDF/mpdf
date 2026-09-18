<?php

namespace Mpdf\Fonts;

use Mpdf\TTFontFile;

class TTFontFileAnalysis extends TTFontFile
{

	/**
	 * Read only what a font browser needs of a font: its names, its metrics, and whether it is a
	 * collection.
	 *
	 * Used to list the fonts in a directory, where reading the character map and the layout tables of
	 * every file would cost more than the listing is worth.
	 *
	 * @param string $file      The font file to read
	 * @param int    $TTCfontID Which font of a TrueType Collection, or 0 for a plain font
	 *
	 * @return array The family name, the four style flags, the file type, the collection index,
	 *               and the script flags the font browser sorts on
	 */
	function extractCoreInfo($file, $TTCfontID = 0)
	{
		$this->open($file);

		// Closed however the read ends: see getMetrics. Listing a directory reaches this once per file,
		// so a handle kept by every font it could not read is a lock on each of those files
		try {
			$this->readHeader($TTCfontID);

			return $this->readCoreInfo($file, $TTCfontID);
		} finally {
			$this->reader->close();
		}
	}

	/**
	 * @param string $file      The font file being read, for the messages
	 * @param int    $TTCfontID Which font of a TrueType Collection, handed back in the result
	 *
	 * @return array See extractCoreInfo
	 */
	private function readCoreInfo($file, $TTCfontID)
	{
		/* Included for testing...
		  $cmap_offset = $this->seek_table("cmap");
		  $this->reader->skip(2);
		  $cmapTableCount = $this->reader->readUInt16();
		  $unicode_cmap_offset = 0;
		  for ($i=0;$i<$cmapTableCount;$i++) {
		  $x[$i]['platformId'] = $this->reader->readUInt16();
		  $x[$i]['encodingId'] = $this->reader->readUInt16();
		  $x[$i]['offset'] = $this->reader->readUInt32();
		  $save_pos = $this->reader->tell();
		  $x[$i]['format'] = $this->reader->uint16At($cmap_offset + $x[$i]['offset'] );
		  $this->reader->seek($save_pos );
		  }
		  print_r($x); exit;
		 */
		// name - Naming table

		/* Test purposes - displays table of names
		  $name_offset = $this->seek_table("name");
		  $format = $this->reader->readUInt16();
		  if ($format != 0 && $format != 1)	// mPDF 5.3.73
		  die("Unknown name table format ".$format);
		  $numRecords = $this->reader->readUInt16();
		  $string_data_offset = $name_offset + $this->reader->readUInt16();
		  for ($i=0;$i<$numRecords; $i++) {
		  $x[$i]['platformId'] = $this->reader->readUInt16();
		  $x[$i]['encodingId'] = $this->reader->readUInt16();
		  $x[$i]['languageId'] = $this->reader->readUInt16();
		  $x[$i]['nameId'] = $this->reader->readUInt16();
		  $x[$i]['length'] = $this->reader->readUInt16();
		  $x[$i]['offset'] = $this->reader->readUInt16();

		  $N = '';
		  if ($x[$i]['platformId'] == 1 && $x[$i]['encodingId'] == 0 && $x[$i]['languageId'] == 0) { // Roman
		  $opos = $this->reader->tell();
		  $N = $this->reader->bytesAt($string_data_offset + $x[$i]['offset'] , $x[$i]['length'] );
		  $this->reader->seek($opos);
		  $this->reader->seek($opos);
		  }
		  else { 	// Unicode
		  $opos = $this->reader->tell();
		  $this->reader->seek($string_data_offset + $x[$i]['offset'] );
		  $length = $x[$i]['length'] ;
		  if ($length % 2 != 0)
		  $length -= 1;
		  //		die("PostScript name is UTF-16BE string of odd length");
		  $length /= 2;
		  $N = '';
		  while ($length > 0) {
		  $char = $this->reader->readUInt16();
		  $N .= (chr($char));
		  $length -= 1;
		  }
		  $this->reader->seek($opos);
		  $this->reader->seek($opos);
		  }
		  $x[$i]['names'][$nameId] = $N;
		  }
		  print_r($x); exit;
		 */

		$name_offset = $this->seek_table("name");
		$format = $this->reader->readUInt16();
		if ($format != 0 && $format != 1) { // mPDF 5.3.73
			throw new \Mpdf\MpdfException("ERROR - NOT ADDED as Unknown name table format " . $format . " - " . $file);
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
				// A record of odd length cannot be UTF-16, and the browser lists a font rather than
				// refusing it as the parser does, so the trailing byte is dropped instead of read past
				$N = mb_convert_encoding($this->reader->bytesAt($string_data_offset + $offset, $length - ($length % 2)), 'UTF-8', 'UTF-16BE');
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
			$psName = preg_replace('/ /', '-', $names[6]);
		} elseif ($names[4]) {
			$psName = preg_replace('/ /', '-', $names[4]);
		} elseif ($names[1]) {
			$psName = preg_replace('/ /', '-', $names[1]);
		} else {
			$psName = '';
		}
		if (!$names[1] && !$psName) {
			throw new \Mpdf\MpdfException("ERROR - NOT ADDED as Could not find valid font name - " . $file);
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

		// head - Font header table
		$this->seek_table("head");
		$ver_maj = $this->reader->readUInt16();
		$ver_min = $this->reader->readUInt16();
		if ($ver_maj != 1) {
			throw new \Mpdf\MpdfException('ERROR - NOT ADDED as Unknown head table version ' . $ver_maj . '.' . $ver_min . " - " . $file);
		}
		$this->fontRevision = $this->reader->readUInt16() . $this->reader->readUInt16();
		$this->reader->skip(4);
		$magic = $this->reader->readUInt32();
		if ($magic != 0x5F0F3CF5) {
			throw new \Mpdf\MpdfException('ERROR - NOT ADDED as Invalid head table magic ' . $magic . " - " . $file);
		}
		$this->reader->skip(2);
		$this->unitsPerEm = $unitsPerEm = $this->reader->readUInt16();
		$scale = 1000 / $unitsPerEm;
		$this->reader->skip(24);
		$macStyle = $this->reader->readInt16();
		$this->reader->skip(4);
		$indexLocFormat = $this->reader->readInt16();

		// OS/2 - OS/2 and Windows metrics table
		$sFamily = '';
		$panose = '';

		// Tested with & below, so it has to be an integer: a font with no OS/2 table - an old Mac
		// TrueType, say - left it as a string and the bold and italic tests became "string & int"
		$fsSelection = 0;
		if (isset($this->tables["OS/2"])) {
			$this->seek_table("OS/2");
			$this->reader->skip(30);
			$sF = $this->reader->readInt16();
			$sFamily = ($sF >> 8);
			// PANOSE, 10 bytes, per the OS/2 table
			$panose = $this->reader->read(10);
			$this->panose = [];
			for ($p = 0; $p < strlen($panose); $p++) {
				$this->panose[] = ord($panose[$p]);
			}
			$this->reader->skip(20);
			$fsSelection = $this->reader->readInt16();
		}

		// post - PostScript table
		$this->seek_table("post");
		$this->reader->skip(4);
		$this->italicAngle = $this->reader->readInt16() + $this->reader->readUInt16() / 65536.0;
		$this->reader->skip(4);
		$isFixedPitch = $this->reader->readUInt32();

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
				}
			} elseif ((($platformID == 3 && $encodingID == 10) || $platformID == 0)) { // Microsoft, Unicode Format 12 table HKCS
				$format = $this->reader->uint16At($cmap_offset + $offset);
				if ($format == 12) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			$this->reader->seek($save_pos);
		}

		if (!$unicode_cmap_offset) {
			throw new \Mpdf\MpdfException('ERROR - Font (' . $this->filename . ') NOT ADDED as it is not Unicode encoded, and cannot be used by mPDF');
		}

		$rtl = false;
		$indic = false;
		$cjk = false;
		$sip = false;
		$smp = false;
		$pua = false;
		$puaag = false;
		$glyphToChar = [];
		$unAGlyphs = '';
		// Format 12 CMAP does characters above Unicode BMP i.e. some HKCS characters U+20000 and above
		if ($format == 12) {
			$this->reader->seek($unicode_cmap_offset + 4);
			$length = $this->reader->readUInt32();
			$limit = $unicode_cmap_offset + $length;
			$this->reader->skip(4);
			$nGroups = $this->reader->readUInt32();
			for ($i = 0; $i < $nGroups; $i++) {
				$startCharCode = $this->reader->readUInt32();
				$endCharCode = $this->reader->readUInt32();
				$startGlyphCode = $this->reader->readUInt32();
				if (($endCharCode > 0x20000 && $endCharCode < 0x2A6DF) || ($endCharCode > 0x2F800 && $endCharCode < 0x2FA1F)) {
					$sip = true;
				}
				if ($endCharCode > 0x10000 && $endCharCode < 0x1FFFF) {
					$smp = true;
				}
				if (($endCharCode > 0x0590 && $endCharCode < 0x077F) || ($endCharCode > 0xFE70 && $endCharCode < 0xFEFF) || ($endCharCode > 0xFB50 && $endCharCode < 0xFDFF)) {
					$rtl = true;
				}
				if ($endCharCode > 0x0900 && $endCharCode < 0x0DFF) {
					$indic = true;
				}
				if ($endCharCode > 0xE000 && $endCharCode < 0xF8FF) {
					$pua = true;
					if ($endCharCode > 0xF500 && $endCharCode < 0xF7FF) {
						$puaag = true;
					}
				}
				if (($endCharCode > 0x2E80 && $endCharCode < 0x4DC0) || ($endCharCode > 0x4E00 && $endCharCode < 0xA4CF) || ($endCharCode > 0xAC00 && $endCharCode < 0xD7AF) || ($endCharCode > 0xF900 && $endCharCode < 0xFAFF) || ($endCharCode > 0xFE30 && $endCharCode < 0xFE4F)) {
					$cjk = true;
				}

				$offset = 0;
				// Get each glyphToChar - only point if going to analyse un-mapped Arabic Glyphs
				if (isset($this->tables['post'])) {
					for ($unichar = $startCharCode; $unichar <= $endCharCode; $unichar++) {
						$glyph = $startGlyphCode + $offset;
						$offset++;
						$glyphToChar[$glyph][] = $unichar;
					}
				}
			}
		} else { // Format 4 CMap
			$this->reader->seek($unicode_cmap_offset + 2);
			$length = $this->reader->readUInt16();
			$limit = $unicode_cmap_offset + $length;
			$this->reader->skip(2);

			$segCount = $this->reader->readUInt16() / 2;
			$this->reader->skip(6);
			$endCount = [];
			for ($i = 0; $i < $segCount; $i++) {
				$endCount[] = $this->reader->readUInt16();
			}
			$this->reader->skip(2);
			$startCount = [];
			for ($i = 0; $i < $segCount; $i++) {
				$startCount[] = $this->reader->readUInt16();
			}
			$idDelta = [];
			for ($i = 0; $i < $segCount; $i++) {
				$idDelta[] = $this->reader->readInt16();
			}
			$idRangeOffset_start = $this->reader->tell();
			$idRangeOffset = [];
			for ($i = 0; $i < $segCount; $i++) {
				$idRangeOffset[] = $this->reader->readUInt16();
			}

			for ($n = 0; $n < $segCount; $n++) {
				if (($endCount[$n] > 0x0590 && $endCount[$n] < 0x077F) || ($endCount[$n] > 0xFE70 && $endCount[$n] < 0xFEFF) || ($endCount[$n] > 0xFB50 && $endCount[$n] < 0xFDFF)) {
					$rtl = true;
				}
				if ($endCount[$n] > 0x0900 && $endCount[$n] < 0x0DFF) {
					$indic = true;
				}
				if (($endCount[$n] > 0x2E80 && $endCount[$n] < 0x4DC0) || ($endCount[$n] > 0x4E00 && $endCount[$n] < 0xA4CF) || ($endCount[$n] > 0xAC00 && $endCount[$n] < 0xD7AF) || ($endCount[$n] > 0xF900 && $endCount[$n] < 0xFAFF) || ($endCount[$n] > 0xFE30 && $endCount[$n] < 0xFE4F)) {
					$cjk = true;
				}
				if ($endCount[$n] > 0xE000 && $endCount[$n] < 0xF8FF) {
					$pua = true;
					if ($endCount[$n] > 0xF500 && $endCount[$n] < 0xF7FF) {
						$puaag = true;
					}
				}
				// Get each glyphToChar - only point if going to analyse un-mapped Arabic Glyphs
				if (isset($this->tables['post'])) {
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
						$glyphToChar[$glyph][] = $unichar;
					}
				}
			}
		}

		$bold = false;
		$italic = false;
		$ftype = '';
		if ($macStyle & (1 << 0)) {
			$bold = true;
		} // bit 0 bold
		elseif ($fsSelection & (1 << 5)) {
			$bold = true;
		} // 5 	BOLD 	Characters are emboldened

		if ($macStyle & (1 << 1)) {
			$italic = true;
		} // bit 1 italic
		elseif ($fsSelection & (1 << 0)) {
			$italic = true;
		} // 0 	ITALIC 	Font contains Italic characters, otherwise they are upright
		elseif ($this->italicAngle <> 0) {
			$italic = true;
		}

		if ($isFixedPitch) {
			$ftype = 'mono';
		} elseif ($sFamily > 0 && $sFamily < 8) {
			$ftype = 'serif';
		} elseif ($sFamily == 8) {
			$ftype = 'sans';
		} elseif ($sFamily == 10) {
			$ftype = 'cursive';
		}
		// Use PANOSE
		if ($panose) {
			$bFamilyType = ord($panose[0]);
			if ($bFamilyType == 2) {
				$bSerifStyle = ord($panose[1]);
				if (!$ftype) {
					if ($bSerifStyle > 1 && $bSerifStyle < 11) {
						$ftype = 'serif';
					} elseif ($bSerifStyle > 10) {
						$ftype = 'sans';
					}
				}
				$bProportion = ord($panose[3]);
				if ($bProportion == 9 || $bProportion == 1) {
					$ftype = 'mono';
				} // ==1 i.e. No Fit needed for OCR-a and -b
			} elseif ($bFamilyType == 3) {
				$ftype = 'cursive';
			}
		}

		return [$this->familyName, $bold, $italic, $ftype, $TTCfontID, $rtl, $indic, $cjk, $sip, $smp, $puaag, $pua, $unAGlyphs];
	}

	/**
	 * The browser's own wording for the three header complaints. A caller listing a directory prints
	 * each as a line of the listing, which is why they name the file and say it was not added where
	 * the parser's say only what was wrong. Kept as they were, class and all, for anything outside the
	 * library that already lists fonts with this.
	 */
	protected function collectionWithoutFontId()
	{
		return new \Mpdf\MpdfException("ERROR - Error parsing TrueType Collection - " . $this->filename);
	}

	protected function unreadableCollection($version)
	{
		return new \Mpdf\MpdfException("ERROR - NOT ADDED as Error parsing TrueType Collection: version=" . $version . " - " . $this->filename);
	}

	protected function notATrueTypeFont($version)
	{
		return new \Mpdf\MpdfException("ERROR - NOT ADDED as Not a TrueType font: version=" . $version . " - " . $this->filename);
	}
}
