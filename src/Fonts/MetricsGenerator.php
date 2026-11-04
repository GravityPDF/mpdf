<?php

namespace Mpdf\Fonts;

use Mpdf\TTFontFile;
use Psr\Log\LoggerInterface;

class MetricsGenerator
{

	/**
	 * What generateMetrics() writes, in the font's metrics and the files beside them. Raise it whenever
	 * that changes. Mpdf::fontMetrics() compares it and regenerates on a mismatch, so a cache from before
	 * the font's ranges were written gains them on its next load.
	 */
	const CACHE_FORMAT = 1;

	/**
	 * Whether cached metrics are in the shape this release writes - see CACHE_FORMAT
	 *
	 * @param array $metrics The cached metrics
	 *
	 * @return bool
	 */
	public static function isCurrent(array $metrics)
	{
		return isset($metrics['cacheFormat']) && $metrics['cacheFormat'] === self::CACHE_FORMAT;
	}

	/**
	 * Writes the ranges of the family's font into the font cache, with the font they were built from.
	 *
	 * @param FontCache       $fontCache
	 * @param string          $family
	 * @param CharacterRanges $ranges
	 * @param string          $file      The font file's name
	 * @param int             $TTCfontID
	 * @param int|bool        $useOTL
	 */
	public static function writeCachedRanges(FontCache $fontCache, $family, CharacterRanges $ranges, $file, $TTCfontID, $useOTL)
	{
		$fontCache->jsonWrite(self::rangesFile($family), $ranges->toArray() + self::rangesSource($file, $TTCfontID, $useOTL));
	}

	/**
	 * The ranges writeCachedRanges() wrote for the family, where they were written for the font file,
	 * TTCfontID and useOTL it has now. Read past FontCache's memory, so the decoded file is not kept for
	 * the rest of the document.
	 *
	 * @param FontCache       $fontCache
	 * @param string          $family
	 * @param array           $fontdata The family's entry in fontdata
	 * @param bool            $bmpOnly  Whether the family is in BMPonly, which gets no ranges file
	 * @param LoggerInterface $logger   Told of a file that is not a valid ranges file
	 *
	 * @return CharacterRanges|null
	 */
	public static function cachedRanges(FontCache $fontCache, $family, array $fontdata, $bmpOnly, LoggerInterface $logger)
	{
		if ($bmpOnly) {
			return null;
		}

		$file = self::rangesFile($family);
		$contents = $fontCache->loadIfPresent($file);
		if ($contents === null) {
			return null;
		}

		$cached = json_decode($contents, true);
		$source = self::rangesSource(
			basename($fontdata['R']),
			isset($fontdata['TTCfontID']['R']) ? $fontdata['TTCfontID']['R'] : 0,
			isset($fontdata['useOTL']) ? $fontdata['useOTL'] : 0
		);
		if (is_array($cached) && array_intersect_key($cached, $source) !== $source) {
			return null;
		}

		return CharacterRanges::fromData($cached, $fontCache->tempFilename($file), $logger);
	}

	/**
	 * @param string $family
	 *
	 * @return string The name of the family's ranges file in the font cache
	 */
	private static function rangesFile($family)
	{
		return $family . '.ranges.json';
	}

	/**
	 * @param string   $file      The font file's name
	 * @param int      $TTCfontID
	 * @param int|bool $useOTL
	 *
	 * @return array What a ranges file in the font cache records of the font it was built from. The cache
	 *               is keyed by family, so a file another config wrote can be of another font, or carry
	 *               the Private Use codepoints useOTL gives unmapped glyphs.
	 */
	private static function rangesSource($file, $TTCfontID, $useOTL)
	{
		return ['font' => (string) $file, 'TTCfontID' => (int) $TTCfontID, 'useOTL' => (int) $useOTL];
	}

	private $fontCache;

	private $fontDescriptor;

	public function __construct(FontCache $fontCache, $fontDescriptor)
	{
		$this->fontCache = $fontCache;
		$this->fontDescriptor = $fontDescriptor;
	}

	public function generateMetrics($ttffile, $ttfstat, $fontkey, $TTCfontID, $debugfonts, $BMPonly, $useOTL, $fontUseOTL)
	{
		$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
		$ttf->getMetrics($ttffile, $fontkey, $TTCfontID, $debugfonts, $BMPonly, $useOTL); // mPDF 5.7.1

		$font = [
			'name' => $this->getFontName($ttf->fullName),
			'type' => 'TTF',
			'desc' => [
				'CapHeight' => round($ttf->capHeight),
				'XHeight' => round($ttf->xHeight),
				'FontBBox' => '[' . round($ttf->bbox[0]) . " " . round($ttf->bbox[1]) . " " . round($ttf->bbox[2]) . " " . round($ttf->bbox[3]) . ']',
				/* FontBBox from head table */
				/* 		'MaxWidth' => round($ttf->advanceWidthMax),	// AdvanceWidthMax from hhea table	NB ArialUnicode MS = 31990 ! */
				'Flags' => $ttf->flags,
				'Ascent' => round($ttf->ascent),
				'Descent' => round($ttf->descent),
				'Leading' => round($ttf->lineGap),
				'ItalicAngle' => $ttf->italicAngle,
				'StemV' => round($ttf->stemV),
				'MissingWidth' => round($ttf->defaultWidth)
			],
			'unitsPerEm' => round($ttf->unitsPerEm),
			'up' => round($ttf->underlinePosition),
			'ut' => round($ttf->underlineThickness),
			'strp' => round($ttf->strikeoutPosition),
			'strs' => round($ttf->strikeoutSize),
			'ttffile' => $ttffile,
			'TTCfontID' => $TTCfontID,
			'originalsize' => $ttfstat['size'] + 0, /* cast ? */
			'sip' => ($ttf->sipset) ? true : false,
			'smp' => ($ttf->smpset) ? true : false,
			'BMPselected' => ($BMPonly) ? true : false,
			'fontkey' => $fontkey,
			'panose' => $this->getPanose($ttf),
			'haskerninfo' => ($ttf->kerninfo) ? true : false,
			'haskernGPOS' => ($ttf->haskernGPOS) ? true : false,
			'hassmallcapsGSUB' => ($ttf->hassmallcapsGSUB) ? true : false,
			'fontmetrics' => $this->fontDescriptor,
			'useOTL' => ($fontUseOTL) ? $fontUseOTL : 0,
			'rtlPUAstr' => $ttf->rtlPUAstr,
			'GSUBScriptLang' => $ttf->GSUBScriptLang,
			'GSUBFeatures' => $ttf->GSUBFeatures,
			'GSUBLookups' => $ttf->GSUBLookups,
			'GPOSScriptLang' => $ttf->GPOSScriptLang,
			'GPOSFeatures' => $ttf->GPOSFeatures,
			'GPOSLookups' => $ttf->GPOSLookups,
			'kerninfo' => $ttf->kerninfo,
			'cacheFormat' => self::CACHE_FORMAT,
		];

		$this->fontCache->jsonWrite($fontkey . '.mtx.json', $font);
		$this->fontCache->binaryWrite($fontkey . '.cw.dat', $ttf->charWidths);
		$this->fontCache->binaryWrite($fontkey . '.gid.dat', $ttf->glyphIDtoUni);

		// A BMPonly font's widths stop at U+FFFF, so its ranges would say the planes above are missing to
		// a document with another config sharing the cache
		if ($BMPonly) {
			if ($this->fontCache->jsonHas(self::rangesFile($fontkey))) {
				$this->fontCache->jsonRemove(self::rangesFile($fontkey));
			}
		} else {
			$ranges = CharacterRanges::fromWidths($ttf->charWidths, $font['originalsize']);
			self::writeCachedRanges($this->fontCache, $fontkey, $ranges, basename($ttffile), $TTCfontID, $fontUseOTL);
		}

		if ($this->fontCache->has($fontkey . '.cgm')) {
			$this->fontCache->remove($fontkey . '.cgm');
		}

		if ($this->fontCache->has($fontkey . '.z')) {
			$this->fontCache->remove($fontkey . '.z');
		}

		if ($this->fontCache->jsonHas($fontkey . '.cw127.json')) {
			$this->fontCache->jsonRemove($fontkey . '.cw127.json');
		}

		if ($this->fontCache->has($fontkey . '.cw')) {
			$this->fontCache->remove($fontkey . '.cw');
		}

		unset($ttf);
	}

	protected function getFontName($fullName)
	{
		return preg_replace('/[ ()]/', '', $fullName);
	}

	protected function getPanose($ttf)
	{
		$panose = '';
		if (count($ttf->panose)) {
			$panoseArray = array_merge([$ttf->sFamilyClass, $ttf->sFamilySubClass], $ttf->panose);
			foreach ($panoseArray as $value) {
				$panose .= ' ' . dechex($value);
			}
		}

		return $panose;
	}
}
