<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Conversion\DecToAlpha;
use Mpdf\Fonts\Color\ColorFormats;
use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\FontSubsetter;
use Mpdf\Mpdf;
use Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;
use Mpdf\TTFontFile;
use Mpdf\Utils\UtfString;
use Psr\Log\LoggerInterface;

class FontWriter implements \Psr\Log\LoggerAwareInterface
{

	use Strict;
	use PsrLogAwareTrait;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	/**
	 * @var \Mpdf\Fonts\FontCache
	 */
	private $fontCache;

	/**
	 * @var string
	 */
	private $fontDescriptor;

	/**
	 * @var Type3FontWriter|null
	 */
	private $type3FontWriter;

	/**
	 * @param Mpdf            $mpdf           The document being written
	 * @param BaseWriter      $writer         Where the PDF objects go
	 * @param FontCache       $fontCache      Where a parsed font and its width tables are kept
	 * @param string          $fontDescriptor Which of the font's three sets of vertical metrics to
	 *                                        believe: 'winTypo', 'mac' or 'win'
	 * @param LoggerInterface $logger         Told of a colour glyph that cannot be drawn
	 */
	public function __construct(Mpdf $mpdf, BaseWriter $writer, FontCache $fontCache, $fontDescriptor, LoggerInterface $logger)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
		$this->fontCache = $fontCache;
		$this->fontDescriptor = $fontDescriptor;
		$this->logger = $logger;
	}

	/**
	 * Write every font the document used into the PDF, as a font file object and a descriptor.
	 *
	 * A TrueType font is subsetted or carried whole according to `percentSubset` and `maxTTFFilesize`,
	 * and a font mPDF loaded but nothing drew with is skipped.
	 */
	public function writeFonts()
	{
		foreach ($this->mpdf->FontFiles as $fontkey => $info) {
			// TrueType embedded
			if (isset($info['type']) && $info['type'] === 'TTF' && !$info['sip'] && !$info['smp']) {
				$used = true;
				$asSubset = false;
				foreach ($this->mpdf->fonts as $k => $f) {
					if (isset($f['fontkey']) && $f['fontkey'] === $fontkey && $f['type'] === 'TTF') {
						$used = $f['used'];
						if ($used) {
							$nChars = (ord($f['cw'][0]) << 8) + ord($f['cw'][1]);
							$usage = (int) ($this->charactersDrawn($f) * 100 / $nChars);
							// At most percentSubset, not less than, so that the default of 100 subsets
							// even a font every glyph of which was drawn
							$asSubset = $info['length1'] > ($this->mpdf->maxTTFFilesize * 1024) || $usage <= $this->mpdf->percentSubset;
						}
						$this->mpdf->fonts[$k]['asSubset'] = $asSubset;
						break;
					}
				}
				if ($used && !$asSubset) {
					// Font file embedding
					$this->writer->object();
					$this->mpdf->FontFiles[$fontkey]['n'] = $this->mpdf->n;
					$originalsize = $info['length1'];
					if ($this->mpdf->repackageTTF || $this->mpdf->fonts[$fontkey]['TTCfontID'] > 0 || $this->mpdf->fonts[$fontkey]['useOTL'] > 0) { // mPDF 5.7.1
						// Both entries or neither: the stream and the length it declares are kept in
						// separate files, and a miss on either leaves nothing to embed. The length is
						// read first because it is one integer, where the stream it vouches for is the
						// whole compressed font
						$repackagedsize = $this->fontCache->jsonLoadIfPresent($fontkey . '.ps.json');
						$font = null === $repackagedsize ? null : $this->fontCache->loadIfPresent($fontkey . '.ps.z');

						if (null !== $font) {
							$originalsize = $repackagedsize; // of the repackaged font, not of the file
						} else {
							$subsetter = $this->subsetter();
							$font = $subsetter->repackageTTF($this->mpdf->FontFiles[$fontkey]['ttffile'], $this->mpdf->fonts[$fontkey]['TTCfontID'], $this->mpdf->debugfonts, $this->mpdf->fonts[$fontkey]['useOTL']); // mPDF 5.7.1

							$originalsize = strlen($font);
							$font = gzcompress($font);
							unset($subsetter);

							$this->fontCache->binaryWrite($fontkey . '.ps.z', $font);
							$this->fontCache->jsonWrite($fontkey . '.ps.json', $originalsize);
						}
					} else {
						$font = $this->fontCache->loadIfPresent($fontkey . '.z');

						if (null === $font) {
							$font = gzcompress(file_get_contents($this->mpdf->FontFiles[$fontkey]['ttffile']));
							$this->fontCache->binaryWrite($fontkey . '.z', $font);
						}
					}

					$this->writer->write('<</Length ' . $this->writer->streamLength($font));
					$this->writer->write('/Filter /FlateDecode');
					$this->writer->write('/Length1 ' . $originalsize);
					$this->writer->write('>>');
					$this->writer->stream($font);
					$this->writer->write('endobj');
				}
			}
		}

		foreach ($this->mpdf->fonts as $k => $font) {

			// Font objects
			$type = $font['type'];
			$name = $font['name'];

			if ($type === 'TTF' && (!isset($font['used']) || !$font['used'])) {
				continue;
			}

			// @log Writing fonts

			if (isset($font['asSubset'])) {
				$asSubset = $font['asSubset'];
			} else {
				$asSubset = '';
			}

			if ($type === 'Type0') {  // Adobe CJK Fonts

				$this->mpdf->fonts[$k]['n'] = $this->mpdf->n + 1;
				$this->writer->object();
				$this->writer->write('<</Type /Font');
				$this->writeType0($font);

			} elseif ($type === 'core') {

				// Standard font
				$this->mpdf->fonts[$k]['n'] = $this->mpdf->n + 1;

				if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
					throw new \Mpdf\MpdfException('Core fonts are not allowed in PDF/A1-b or PDFX/1-a files (Times, Helvetica, Courier etc.)');
				}

				$this->writer->object();
				$this->writer->write('<</Type /Font');
				$this->writer->write('/BaseFont /' . $name);
				$this->writer->write('/Subtype /Type1');

				if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
					$this->writer->write('/Encoding /WinAnsiEncoding');
				}

				$this->writer->write('>>');
				$this->writer->write('endobj');

			} elseif ($type === 'TTF' && ($font['sip'] || $font['smp'])) {

				// TrueType embedded SUBSETS for SIP (CJK extB containing Supplementary Ideographic Plane 2)
				// Or Unicode Plane 1 - Supplementary Multilingual Plane

				if (!$font['used']) {
					continue;
				}

				if (ColorFormats::drawable($font['colorFormats'])) {
					$this->type3FontWriter()->writeFont($k, $font);
					continue;
				}

				$subsetter = $this->subsetter();
				$subsetCount = count($font['subsetfontids']);
				// AA, AB, ... AZ, BA: what incrementing the string 'AA' gave, counted from an integer since
				// PHP 8.3 deprecates the string increment. The letter counter reaches AA at 27
				$tags = new DecToAlpha();

				for ($sfid = 0; $sfid < $subsetCount; $sfid++) {
					$this->mpdf->fonts[$k]['n'][$sfid] = $this->mpdf->n + 1;  // NB an array for subset
					$subsetname = 'MPDF' . $tags->convert($sfid + 27) . '+' . $font['name'];

					/* For some strange reason a subset ($sfid > 0) containing less than 97 characters causes an error
					  so fill up the array */
					for ($j = count($font['subsets'][$sfid]); $j < 98; $j++) {
						$font['subsets'][$sfid][$j] = 0;
					}

					$subset = $font['subsets'][$sfid];
					unset($subset[0]);
					$ttfontstream = $subsetter->makeSubsetSIP($font['ttffile'], $subset, $font['TTCfontID'], $this->mpdf->debugfonts, $font['useOTL']); // mPDF 5.7.1
					$ttfontsize = strlen($ttfontstream);
					$fontstream = gzcompress($ttfontstream);
					$widthstring = '';
					$toUnistring = '';

					foreach ($font['subsets'][$sfid] as $cp => $u) {
						$w = $this->mpdf->_getCharWidth($font['cw'], $u);
						if ($w !== false) {
							$widthstring .= $w . ' ';
						} else {
							$widthstring .= round($subsetter->defaultWidth) . ' ';
						}
						if ($u > 65535) {
							$utf8 = chr(($u >> 18) + 240) . chr((($u >> 12) & 63) + 128) . chr((($u >> 6) & 63) + 128) . chr(($u & 63) + 128);
							$utf16 = mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8');
							$l1 = ord($utf16[0]);
							$h1 = ord($utf16[1]);
							$l2 = ord($utf16[2]);
							$h2 = ord($utf16[3]);
							$toUnistring .= sprintf("<%02s> <%02s%02s%02s%02s>\n", strtoupper(dechex($cp)), strtoupper(dechex($l1)), strtoupper(dechex($h1)), strtoupper(dechex($l2)), strtoupper(dechex($h2)));
						} else {
							$toUnistring .= sprintf("<%02s> <%04s>\n", strtoupper(dechex($cp)), strtoupper(dechex($u)));
						}
					}

					// Additional Type1 or TrueType font
					$this->writer->object();
					$this->writer->write('<</Type /Font');
					$this->writer->write('/BaseFont /' . $subsetname);
					$this->writer->write('/Subtype /TrueType');
					$this->writer->write('/FirstChar 0 /LastChar ' . (count($font['subsets'][$sfid]) - 1));
					$this->writer->write('/Widths ' . ($this->mpdf->n + 1) . ' 0 R');
					$this->writer->write('/FontDescriptor ' . ($this->mpdf->n + 2) . ' 0 R');
					$this->writer->write('/ToUnicode ' . ($this->mpdf->n + 3) . ' 0 R');
					$this->writer->write('>>');
					$this->writer->write('endobj');

					// Widths
					$this->writer->object();
					$this->writer->write('[' . $widthstring . ']');
					$this->writer->write('endobj');

					// Descriptor
					$this->writer->object();
					$s = '<</Type /FontDescriptor /FontName /' . $subsetname . "\n";
					foreach ($font['desc'] as $kd => $v) {
						if ($kd === 'Flags') {
							$v |= 4;
							$v &= ~32;
						} // SYMBOLIC font flag
						$s .= ' /' . $kd . ' ' . $v . "\n";
					}
					$s .= '/FontFile2 ' . ($this->mpdf->n + 2) . ' 0 R';
					$this->writer->write($s . '>>');
					$this->writer->write('endobj');

					// ToUnicode
					$this->writer->object();
					$toUni = "/CIDInit /ProcSet findresource begin\n";
					$toUni .= "12 dict begin\n";
					$toUni .= "begincmap\n";
					$toUni .= "/CIDSystemInfo\n";
					$toUni .= "<</Registry (Adobe)\n";
					$toUni .= "/Ordering (UCS)\n";
					$toUni .= "/Supplement 0\n";
					$toUni .= ">> def\n";
					$toUni .= "/CMapName /Adobe-Identity-UCS def\n";
					$toUni .= "/CMapType 2 def\n";
					$toUni .= "1 begincodespacerange\n";
					$toUni .= "<00> <FF>\n";
					// $toUni .= sprintf("<00> <%02s>\n", strtoupper(dechex(count($font['subsets'][$sfid])-1)));
					$toUni .= "endcodespacerange\n";
					$toUni .= count($font['subsets'][$sfid]) . " beginbfchar\n";
					$toUni .= $toUnistring;
					$toUni .= "endbfchar\n";
					$toUni .= "endcmap\n";
					$toUni .= "CMapName currentdict /CMap defineresource pop\n";
					$toUni .= "end\n";
					$toUni .= "end\n";
					$this->writer->write('<</Length ' . $this->writer->streamLength($toUni) . '>>');
					$this->writer->stream($toUni);
					$this->writer->write('endobj');

					// Font file
					$this->writer->object();
					$this->writer->write('<</Length ' . $this->writer->streamLength($fontstream));
					$this->writer->write('/Filter /FlateDecode');
					$this->writer->write('/Length1 ' . $ttfontsize);
					$this->writer->write('>>');
					$this->writer->stream($fontstream);
					$this->writer->write('endobj');
				} // foreach subset
				unset($subsetter);

			} elseif ($type === 'TTF') {  // TrueType embedded SUBSETS or FULL

				$this->mpdf->fonts[$k]['n'] = $this->mpdf->n + 1;

				if ($asSubset) {
					$ssfaid = 'A';
					$subsetter = $this->subsetter();
					$fontname = 'MPDFA' . $ssfaid . '+' . $font['name'];
					$ascii = $this->asciiRange();
					$subset = array_combine($ascii, $ascii) + $font['subset'];
					unset($subset[0]);
					$ttfontstream = $subsetter->makeSubset($font['ttffile'], $subset, $font['TTCfontID'], $this->mpdf->debugfonts, $font['useOTL']);
					$ttfontsize = strlen($ttfontstream);
					$fontstream = gzcompress($ttfontstream);
					$codeToGlyph = $subsetter->codeToGlyph;
					unset($codeToGlyph[0]);
				} else {
					$fontname = $font['name'];
				}

				// Type0 Font
				// A composite font - a font composed of other fonts, organized hierarchically
				$this->writer->object();
				$this->writer->write('<</Type /Font');
				$this->writer->write('/Subtype /Type0');
				$this->writer->write('/BaseFont /' . $fontname . '');
				$this->writer->write('/Encoding /Identity-H');
				$this->writer->write('/DescendantFonts [' . ($this->mpdf->n + 1) . ' 0 R]');
				$this->writer->write('/ToUnicode ' . ($this->mpdf->n + 2) . ' 0 R');
				$this->writer->write('>>');
				$this->writer->write('endobj');

				// CIDFontType2
				// A CIDFont whose glyph descriptions are based on TrueType font technology
				$this->writer->object();
				$this->writer->write('<</Type /Font');
				$this->writer->write('/Subtype /CIDFontType2');
				$this->writer->write('/BaseFont /' . $fontname . '');
				$this->writer->write('/CIDSystemInfo ' . ($this->mpdf->n + 2) . ' 0 R');
				$this->writer->write('/FontDescriptor ' . ($this->mpdf->n + 3) . ' 0 R');

				if (isset($font['desc']['MissingWidth'])) {
					$this->writer->write('/DW ' . $font['desc']['MissingWidth'] . '');
				}

				$w = null;
				if (!$asSubset) {
					$w = $this->fontCache->loadIfPresent($font['fontkey'] . '.cw');
				}

				if (null === $w) {
					$this->writeTTFontWidths($font, $asSubset, ($asSubset ? $subsetter->maxUni : 0));
				} else {
					$this->writer->write($w);
				}

				$this->writer->write('/CIDToGIDMap ' . ($this->mpdf->n + 4) . ' 0 R');
				$this->writer->write('>>');
				$this->writer->write('endobj');

				// ToUnicode
				$this->writer->object();
				$toUni = "/CIDInit /ProcSet findresource begin\n";
				$toUni .= "12 dict begin\n";
				$toUni .= "begincmap\n";
				$toUni .= "/CIDSystemInfo\n";
				$toUni .= "<</Registry (Adobe)\n";
				$toUni .= "/Ordering (UCS)\n";
				$toUni .= "/Supplement 0\n";
				$toUni .= ">> def\n";
				$toUni .= "/CMapName /Adobe-Identity-UCS def\n";
				$toUni .= "/CMapType 2 def\n";
				$toUni .= "1 begincodespacerange\n";
				$toUni .= "<0000> <FFFF>\n";
				$toUni .= "endcodespacerange\n";
				$toUni .= $this->identityRanges($font);
				$toUni .= "endcmap\n";
				$toUni .= "CMapName currentdict /CMap defineresource pop\n";
				$toUni .= "end\n";
				$toUni .= "end\n";

				$this->writer->write('<</Length ' . $this->writer->streamLength($toUni) . '>>');
				$this->writer->stream($toUni);
				$this->writer->write('endobj');

				// CIDSystemInfo dictionary
				$this->writer->object();
				$this->writer->write('<</Registry ' . $this->writer->string('Adobe'));
				$this->writer->write('/Ordering ' . $this->writer->string('UCS'));
				$this->writer->write('/Supplement 0');
				$this->writer->write('>>');
				$this->writer->write('endobj');

				// Font descriptor
				$this->writer->object();
				$this->writer->write('<</Type /FontDescriptor');
				$this->writer->write('/FontName /' . $fontname);

				foreach ($font['desc'] as $kd => $v) {
					if ($asSubset && $kd === 'Flags') {
						$v |= 4;
						$v &= ~32;
					} // SYMBOLIC font flag
					$this->writer->write(' /' . $kd . ' ' . $v);
				}

				if ($font['panose']) {
					$this->writer->write(' /Style << /Panose ' . $this->writer->hexString($font['panose']) . ' >>');
				}

				if ($asSubset) {
					$this->writer->write('/FontFile2 ' . ($this->mpdf->n + 2) . ' 0 R');
				} elseif ($font['fontkey']) {
					// obj ID of a stream containing a TrueType font program
					$this->writer->write('/FontFile2 ' . $this->mpdf->FontFiles[$font['fontkey']]['n'] . ' 0 R');
				}

				$this->writer->write('>>');
				$this->writer->write('endobj');

				// Embed CIDToGIDMap
				// A specification of the mapping from CIDs to glyph indices
				if ($asSubset) {
					$cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
					foreach ($codeToGlyph as $cc => $glyph) {
						$cidtogidmap[$cc * 2] = chr($glyph >> 8);
						$cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xFF);
					}
					$cidtogidmap = gzcompress($cidtogidmap);
				} else {
					// First see if there is a cached CIDToGIDMapfile
					$cidtogidmap = $this->fontCache->loadIfPresent($font['fontkey'] . '.cgm');

					if (null === $cidtogidmap) {
						$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
						$charToGlyph = $ttf->getCTG($font['ttffile'], $font['TTCfontID'], $this->mpdf->debugfonts, $font['useOTL']);
						$cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
						foreach ($charToGlyph as $cc => $glyph) {
							$cidtogidmap[$cc * 2] = chr($glyph >> 8);
							$cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xFF);
						}
						unset($ttf);
						$cidtogidmap = gzcompress($cidtogidmap);
						$this->fontCache->binaryWrite($font['fontkey'] . '.cgm', $cidtogidmap);
					}
				}
				$this->writer->object();
				$this->writer->write('<</Length ' . $this->writer->streamLength($cidtogidmap) . '');
				$this->writer->write('/Filter /FlateDecode');
				$this->writer->write('>>');
				$this->writer->stream($cidtogidmap);
				$this->writer->write('endobj');

				// Font file
				if ($asSubset) {
					$this->writer->object();
					$this->writer->write('<</Length ' . $this->writer->streamLength($fontstream));
					$this->writer->write('/Filter /FlateDecode');
					$this->writer->write('/Length1 ' . $ttfontsize);
					$this->writer->write('>>');
					$this->writer->stream($fontstream);
					$this->writer->write('endobj');
					unset($subsetter);
				}
			} else {
				throw new \Mpdf\MpdfException(sprintf('Unsupported font type: %s (%s)', $type, $name));
			}
		}
	}

	/**
	 * How much of a font the document drew, counted the way the font's own character count is.
	 *
	 * The subset records every codepoint of the text whether the font has a glyph for it or not, so
	 * the two only divide into a share of the font if this makes the same test.
	 *
	 * @return int
	 */
	private function charactersDrawn(array $font)
	{
		$drawn = 0;

		foreach ($font['subset'] as $u) {
			if ($this->mpdf->_charDefined($font['cw'], $u)) {
				$drawn++;
			}
		}

		return $drawn;
	}

	/**
	 * The bfrange entries of an Identity-H font's ToUnicode CMap: every code the document drew in the
	 * font, and the 32-127 range each subset carries, mapped to itself.
	 *
	 * An Identity-H font takes its codes from the text rather than from the font's own encoding, so a
	 * code is the Unicode value it stands for and each range maps to where it starts. A range may run
	 * no further than the end of the high byte it begins in: ISO 32000-1 9.10.3 increments the last
	 * byte of the destination for each code, and asks that the byte is no more than
	 * 255 - (srcCode2 - srcCode1), which mapping the whole two-byte space in one range cannot meet.
	 *
	 * @param array $font The font as the document holds it, whose subset is the codes drawn
	 *
	 * @return string The bfrange blocks, of at most the hundred entries a block may hold
	 */
	private function identityRanges(array $font)
	{
		$codes = array_fill_keys($this->asciiRange(), true);

		foreach ($font['subset'] as $u) {
			if ($u > 0) {
				// The code units the character is drawn as: one, or a surrogate pair above the basic plane
				foreach (unpack('n*', mb_convert_encoding(UtfString::code2utf($u), 'UTF-16BE', 'UTF-8')) as $unit) {
					$codes[$unit] = true;
				}
			}
		}

		ksort($codes);

		// Codes that follow each other inside one high byte make a range
		$ranges = [];
		foreach (array_keys($codes) as $code) {
			$last = count($ranges) - 1;

			if ($last >= 0 && $code === $ranges[$last][1] + 1 && ($code >> 8) === ($ranges[$last][0] >> 8)) {
				$ranges[$last][1] = $code;
				continue;
			}

			$ranges[] = [$code, $code];
		}

		$cmap = '';
		foreach (array_chunk($ranges, 100) as $block) {
			$cmap .= count($block) . " beginbfrange\n";
			foreach ($block as $range) {
				$cmap .= sprintf("<%04X> <%04X> <%04X>\n", $range[0], $range[1], $range[0]);
			}
			$cmap .= "endbfrange\n";
		}

		return $cmap;
	}

	/**
	 * The 32-127 range every subset font carries, whether the document drew it or not.
	 *
	 * @return int[]
	 */
	private function asciiRange()
	{
		return range(32, 127);
	}

	/**
	 * A font program builder, with a parser behind it for the table directory it reads.
	 *
	 * @return FontSubsetter
	 */
	private function subsetter()
	{
		return new FontSubsetter(new TTFontFile($this->fontCache, $this->fontDescriptor));
	}

	/**
	 * Writes the resource dictionaries of the fonts written as Type3, which name their images by object
	 * number, so this follows ImageWriter::writeImages()
	 */
	public function writeType3Resources()
	{
		if ($this->type3FontWriter) {
			$this->type3FontWriter->writeResources();
		}
	}

	/**
	 * @return Type3FontWriter What writes a font drawn in colour: one for the document, which keeps the
	 *                         resource dictionaries of the fonts it wrote until writeType3Resources()
	 */
	private function type3FontWriter()
	{
		if (!$this->type3FontWriter) {
			$this->type3FontWriter = new Type3FontWriter($this->mpdf, $this->writer, $this->fontCache, $this->fontDescriptor, $this->logger);
		}

		return $this->type3FontWriter;
	}

	/**
	 * Write the /W array of a TrueType font: what each character in it is drawn at.
	 *
	 * The first 128 characters are the same in every document that uses the font, so their run is
	 * cached per font and reused rather than measured again.
	 *
	 * @param array $font     The font, whose 'cw' holds the widths
	 * @param bool  $asSubset Whether only the characters the document used are embedded
	 * @param int   $maxUni   The highest character the font covers, which bounds the array
	 */
	private function writeTTFontWidths(&$font, $asSubset, $maxUni) // _putTTfontwidths
	{
		$character = [
			'startcid' => 1,
			'rangeid' => 0,
			'prevcid' => -2,
			'prevwidth' => -1,
			'interval' => false,
			'range' => [],
		];

		$fontCacheFilename = $font['fontkey'] . '.cw127.json';
		$cached = null;
		if ($asSubset) {
			$cached = $this->fontCache->jsonLoadIfPresent($fontCacheFilename);
		}

		if (null !== $cached) {
			$character = $cached;
			$character['startcid'] = 128;
		}

		// for each character
		$cwlen = ($asSubset) ? $maxUni + 1 : (strlen($font['cw']) / 2);
		for ($cid = $character['startcid']; $cid < $cwlen; $cid++) {
			if ($cid == 128 && $asSubset && null === $cached) {
				$character = [
					'rangeid' => $character['rangeid'],
					'prevcid' => $character['prevcid'],
					'prevwidth' => $character['prevwidth'],
					'interval' => $character['interval'],
					'range' => $character['range'],
				];

				$this->fontCache->jsonWrite($fontCacheFilename, $character);
			}

			$character1 = isset($font['cw'][$cid * 2]) ? $font['cw'][$cid * 2] : '';
			$character2 = isset($font['cw'][$cid * 2 + 1]) ? $font['cw'][$cid * 2 + 1] : '';

			if ($character1 === "\00" && $character2 === "\00") {
				continue;
			}

			$w1 = $character1 === '' ? 0 : ord($character1);
			$w2 = $character2 === '' ? 0 : ord($character2);

			$width = ($w1 << 8) + $w2;

			if ($width === 65535) {
				$width = 0;
			}

			if ($asSubset && $cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
				continue;
			}

			if ($asSubset && $cid > 0xFFFF) {
				continue;
			} // mPDF 6

			if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
				if ($cid === ($character['prevcid'] + 1)) {
					// consecutive CID
					if ($width === $character['prevwidth']) {
						if (isset($character['range'][$character['rangeid']][0]) && $width === $character['range'][$character['rangeid']][0]) {
							$character['range'][$character['rangeid']][] = $width;
						} else {
							array_pop($character['range'][$character['rangeid']]);
							// new range
							$character['rangeid'] = $character['prevcid'];
							$character['range'][$character['rangeid']] = [];
							$character['range'][$character['rangeid']][] = $character['prevwidth'];
							$character['range'][$character['rangeid']][] = $width;
						}
						$character['interval'] = true;
						$character['range'][$character['rangeid']]['interval'] = true;
					} else {
						if ($character['interval']) {
							// new range
							$character['rangeid'] = $cid;
							$character['range'][$character['rangeid']] = [];
							$character['range'][$character['rangeid']][] = $width;
						} else {
							$character['range'][$character['rangeid']][] = $width;
						}
						$character['interval'] = false;
					}
				} else {
					// new range
					$character['rangeid'] = $cid;
					$character['range'][$character['rangeid']] = [];
					$character['range'][$character['rangeid']][] = $width;
					$character['interval'] = false;
				}
				$character['prevcid'] = $cid;
				$character['prevwidth'] = $width;
			}
		}
		$w = $this->writeFontRanges($character['range']);
		$this->writer->write($w);
		if (!$asSubset) {
			$this->fontCache->binaryWrite($font['fontkey'] . '.cw', $w);
		}
	}

	/**
	 * Render a /W array from character ranges, in whichever of the two forms PDF allows is shorter.
	 *
	 * A run of equal widths is written as first, last and the one width; anything else as a list. The
	 * ranges are merged where they abut first, so a font does not pay for a boundary that says
	 * nothing.
	 *
	 * @param array $range Widths by starting character. Merged in place.
	 *
	 * @return string The /W array
	 */
	private function writeFontRanges(&$range) // _putfontranges
	{
		// optimize ranges
		$prevk = -1;
		$nextk = -1;
		$prevint = false;
		foreach ($range as $k => $ws) {
			$cws = count($ws);
			if (($k == $nextk) and ( !$prevint) and ( (!isset($ws['interval'])) or ( $cws < 4))) {
				if (isset($range[$k]['interval'])) {
					unset($range[$k]['interval']);
				}
				$range[$prevk] = array_merge($range[$prevk], $range[$k]);
				unset($range[$k]);
			} else {
				$prevk = $k;
			}
			$nextk = $k + $cws;
			if (isset($ws['interval'])) {
				if ($cws > 3) {
					$prevint = true;
				} else {
					$prevint = false;
				}
				unset($range[$k]['interval']);
				--$nextk;
			} else {
				$prevint = false;
			}
		}
		// output data
		$w = '';
		foreach ($range as $k => $ws) {
			if (count(array_count_values($ws)) === 1) {
				// interval mode is more compact
				$w .= ' ' . $k . ' ' . ($k + count($ws) - 1) . ' ' . $ws[0];
			} else {
				// range mode
				$w .= ' ' . $k . ' [ ' . implode(' ', $ws) . ' ]' . "\n";
			}
		}
		return '/W [' . $w . ' ]';
	}

	/**
	 * Write the /W array of a CJK font, whose widths are held by CID rather than by character.
	 *
	 * @param array $font      The font, whose 'cw' holds the widths and 'dw' the default
	 * @param int   $cidoffset What to subtract from each CID to reach the font's own numbering
	 */
	private function writeFontWidths(&$font, $cidoffset = 0) // _putfontwidths
	{
		ksort($font['cw']);
		unset($font['cw'][65535]);
		$rangeid = 0;
		$range = [];
		$prevcid = -2;
		$prevwidth = -1;
		$interval = false;
		// for each character
		foreach ($font['cw'] as $cid => $width) {
			$cid -= $cidoffset;
			if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
				if ($cid === ($prevcid + 1)) {
					// consecutive CID
					if ($width === $prevwidth) {
						if ($width === $range[$rangeid][0]) {
							$range[$rangeid][] = $width;
						} else {
							array_pop($range[$rangeid]);
							// new range
							$rangeid = $prevcid;
							$range[$rangeid] = [];
							$range[$rangeid][] = $prevwidth;
							$range[$rangeid][] = $width;
						}
						$interval = true;
						$range[$rangeid]['interval'] = true;
					} else {
						if ($interval) {
							// new range
							$rangeid = $cid;
							$range[$rangeid] = [];
							$range[$rangeid][] = $width;
						} else {
							$range[$rangeid][] = $width;
						}
						$interval = false;
					}
				} else {
					// new range
					$rangeid = $cid;
					$range[$rangeid] = [];
					$range[$rangeid][] = $width;
					$interval = false;
				}
				$prevcid = $cid;
				$prevwidth = $width;
			}
		}
		$this->writer->write($this->writeFontRanges($range));
	}

	/**
	 * Write one of the Adobe CJK fonts, which are named in the PDF rather than embedded.
	 *
	 * The reader is expected to have the font already, so what goes in is the descendant font, its
	 * character collection, and the widths - never a font program.
	 *
	 * @param array $font The font, as Mpdf::fonts holds it
	 */
	public function writeType0(&$font) // _putType0
	{
		// Type0
		$this->writer->write('/Subtype /Type0');
		$this->writer->write('/BaseFont /' . $font['name'] . '-' . $font['CMap']);
		$this->writer->write('/Encoding /' . $font['CMap']);
		$this->writer->write('/DescendantFonts [' . ($this->mpdf->n + 1) . ' 0 R]');
		$this->writer->write('>>');
		$this->writer->write('endobj');
		// CIDFont
		$this->writer->object();
		$this->writer->write('<</Type /Font');
		$this->writer->write('/Subtype /CIDFontType0');
		$this->writer->write('/BaseFont /' . $font['name']);

		$cidinfo = '/Registry ' . $this->writer->string('Adobe');
		$cidinfo .= ' /Ordering ' . $this->writer->string($font['registry']['ordering']);
		$cidinfo .= ' /Supplement ' . $font['registry']['supplement'];
		$this->writer->write('/CIDSystemInfo <<' . $cidinfo . '>>');

		$this->writer->write('/FontDescriptor ' . ($this->mpdf->n + 1) . ' 0 R');
		if (isset($font['MissingWidth'])) {
			$this->writer->write('/DW ' . $font['MissingWidth'] . '');
		}
		$this->writeFontWidths($font, 31);
		$this->writer->write('>>');
		$this->writer->write('endobj');

		// Font descriptor
		$this->writer->object();
		$s = '<</Type /FontDescriptor /FontName /' . $font['name'];
		foreach ($font['desc'] as $k => $v) {
			if ($k !== 'Style') {
				$s .= ' /' . $k . ' ' . $v . '';
			}
		}
		$this->writer->write($s . '>>');
		$this->writer->write('endobj');
	}

}
