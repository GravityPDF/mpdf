<?php

namespace Mpdf\Shaper;

use Mpdf\Ucdn;

/**
 * Which of a font's OpenType script and language system tags a run of text is laid out with.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/scripttags
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/languagetags
 */
class OtlTags
{

	/**
	 * The v2 Indic and Myanmar tags, each with the original tag that a font made to the earlier
	 * specification offers instead.
	 */
	private static $originalIndicTags = [
		'bng2' => 'beng',
		'dev2' => 'deva',
		'gjr2' => 'gujr',
		'gur2' => 'guru',
		'knd2' => 'knda',
		'mlm2' => 'mlym',
		'ory2' => 'orya',
		'tml2' => 'taml',
		'tel2' => 'telu',
		'mym2' => 'mymr',
	];

	/**
	 * Variant and script subtags with a language system of their own whatever the language, in the
	 * order HarfBuzz tests them.
	 */
	private static $variants = [
		'fonnapa' => ['APPH'],
		'polyton' => ['PGR '],
		'arevmda' => ['HYE '],
		'provenc' => ['PRO '],
		'fonipa' => ['IPPH'],
		'geok' => ['KGE '],
		'syre' => ['SYRE'],
		'syrj' => ['SYRJ'],
		'syrn' => ['SYRN'],
	];

	/**
	 * Retired tags HarfBuzz reads whole, before the extended language rule would read no-nyn as
	 * Nkole.
	 */
	private static $retired = [
		'art-lojban' => ['JBO '],
		'i-hak' => ['ZHS '],
		'i-lux' => ['LTZ '],
		'i-navajo' => ['NAV ', 'ATH '],
		'no-bok' => ['NOR '],
		'no-nyn' => ['NYN '],
		'zh-min' => ['ZHS '],
		'zh-min-nan' => ['ZHS '],
	];

	/**
	 * Languages that take another language system for one script or region.
	 */
	private static $languageSubtags = [
		'ga' => ['latg' => ['IRT ']],
		'mnw' => ['th' => ['MONT']],
		'ro' => ['md' => ['MOL ', 'ROM ']],
	];

	/**
	 * The Chinese regions with language systems of their own, in the order HarfBuzz looks for them.
	 */
	private static $chineseRegions = [
		'hk' => ['ZHH '],
		'mo' => ['ZHTM', 'ZHH '],
		'tw' => ['ZHT '],
	];

	/**
	 * The script tag a run is laid out with, out of what the table offers.
	 *
	 * The tag Unicode implies is only a first choice: a font may offer the v2 Indic tag and not the
	 * original one or the other way round, may offer nothing for the script and still have a default
	 * entry, and may offer a script mPDF has no shaper for.
	 *
	 * @param array  $ScriptLang  The scripts the table offers, each with its language systems
	 * @param string $scripttag   The tag the text's Unicode script implies
	 * @param int    $scriptblock The text's Unicode script, as Ucdn::SCRIPT_*
	 * @param string $shaper      The shaper picked for it, or '' where there is none
	 * @param int    $useOTL      The useOTL mask of the font
	 *
	 * @return array The tag, or '' for none, and whether it is an original Indic tag rather than a v2 one
	 */
	public static function script(array $ScriptLang, $scripttag, $scriptblock, $shaper, $useOTL)
	{
		if (!($useOTL & self::useOTLbit($scriptblock))) {
			return ['', false];
		}

		if (isset($ScriptLang[$scripttag])) {
			return [$scripttag, false];
		}

		// Only the run's own original tag: another Indic script's lookups and reordering are no fit
		// for it, and a font offering nothing for the script is laid out by its default entry
		$original = isset(self::$originalIndicTags[$scripttag]) ? self::$originalIndicTags[$scripttag] : '';
		if ($shaper && $original && isset($ScriptLang[$original])) {
			return [$original, true];
		}

		foreach (['DFLT', 'dflt', 'latn'] as $fallback) {
			if (isset($ScriptLang[$fallback])) {
				return [$fallback, false];
			}
		}

		return ['', false];
	}

	/**
	 * The language system tag a run is laid out with, out of what the script offers.
	 *
	 * @param string $ietf      The language of the text as an IETF tag, e.g. 'sr-Cyrl' or 'zh-Hant-HK'
	 * @param string $available The language systems the script offers, as one string of tags
	 *
	 * @return string The first of the language's tags the script offers, 'DFLT' where it offers none of
	 *                them and offers that, or '' for neither
	 */
	public static function language($ietf, $available)
	{
		if ($available == '') {
			return '';
		}

		foreach (self::languageSystems($ietf) as $langsys) {
			if (strpos($available, $langsys) !== false) {
				return $langsys;
			}
		}

		return strpos($available, 'DFLT') !== false ? 'DFLT' : '';
	}

	/**
	 * The language systems for an IETF tag, in the order HarfBuzz 14.3.1 tries them
	 * (hb_ot_tags_from_language() in hb-ot-tag.cc and hb_ot_tags_from_complex_language() in
	 * hb-ot-tag-table.hh):
	 *
	 * 1. a variant or script that has its own language system in any language, e.g. -polyton
	 * 2. a retired tag, read whole
	 * 3. a script or region with its own language system in this language, e.g. ro-MD
	 * 4. Chinese scripts and regions
	 * 5. an extended language subtag, e.g. zh-yue
	 * 6. the language subtag
	 *
	 * Only the subtags before the first singleton count, so an extension or private use subtag
	 * selects nothing. A script is the subtag after the language; a region may be anywhere.
	 *
	 * An extended language Ucdn::$ot_languages has no key for keeps the language subtag's tags, where
	 * HarfBuzz would take an unlisted one as its own ISO 639-3 code.
	 *
	 * @param string $ietf
	 *
	 * @return string[]
	 */
	private static function languageSystems($ietf)
	{
		$tag = strtolower((string) $ietf);
		if ($tag == '') {
			return [];
		}

		$subtags = explode('-', preg_replace('/-[^-]-.*/s', '', $tag));
		$language = array_shift($subtags);
		if ($language == 'x') {
			return [];
		}

		foreach (self::$variants as $variant => $langsys) {
			if (in_array($variant, $subtags, true)) {
				return $langsys;
			}
		}

		if (isset(self::$retired[$tag])) {
			return self::$retired[$tag];
		}

		$script = isset($subtags[0]) ? $subtags[0] : '';

		if (isset(self::$languageSubtags[$language])) {
			foreach (self::$languageSubtags[$language] as $subtag => $langsys) {
				if (strlen($subtag) == 4 ? $script == $subtag : in_array($subtag, $subtags, true)) {
					return $langsys;
				}
			}
		}

		$own = self::tags($language);

		$chinese = self::chinese($own, $script, $subtags);
		if ($chinese) {
			return $chinese;
		}

		// A three-digit region such as 419 has no key, so three characters that find one are a language
		$extended = strlen($script) == 3 ? self::tags($script) : [];

		return $extended ?: $own;
	}

	/**
	 * The Chinese language systems a script or region gives, in the order HarfBuzz tries them.
	 *
	 * The script subtag outranks the region, so zh-Hans-HK is Simplified, except that Traditional
	 * Chinese in Hong Kong or Macao keeps its region's tag. Macao has its own ZHTM, and where a font
	 * lacks it takes Hong Kong's ZHH before the default.
	 *
	 * Only Hans moves Cantonese (yue, ZHH) and Literary Chinese (lzh, ZHT) off their own tags. Every
	 * other Chinese language is Simplified by default and takes zh's rules.
	 *
	 * @param string[] $own     The language's own tags
	 * @param string   $script  The subtag after the language
	 * @param string[] $subtags The subtags after the language
	 *
	 * @return string[] The language systems, or none
	 */
	private static function chinese(array $own, $script, array $subtags)
	{
		if (!$own || !in_array($own[0], ['ZHS ', 'ZHT ', 'ZHH '], true)) {
			return [];
		}
		if ($script == 'hans') {
			return ['ZHS '];
		}
		if ($own[0] != 'ZHS ') {
			return [];
		}
		if ($script == 'hant') {
			$region = isset($subtags[1]) ? $subtags[1] : '';

			return $region == 'hk' || $region == 'mo' ? self::$chineseRegions[$region] : ['ZHT '];
		}

		foreach (self::$chineseRegions as $region => $langsys) {
			if (in_array($region, $subtags, true)) {
				return $langsys;
			}
		}

		return [];
	}

	/**
	 * @param string $language A language subtag
	 *
	 * @return string[] Its language systems in Ucdn::$ot_languages, or none
	 */
	private static function tags($language)
	{
		return isset(Ucdn::$ot_languages[$language]) ? (array) Ucdn::$ot_languages[$language] : [];
	}

	/**
	 * The bit of the useOTL config that opens a script to OpenType layout.
	 *
	 * @param int $scriptblock A Unicode script, as Ucdn::SCRIPT_*
	 *
	 * @return int 0x01 Latin, 0x02 Cyrillic, 0x04 Greek, 0x08 CJK other than Hangul, 0x80 everything else
	 */
	private static function useOTLbit($scriptblock)
	{
		if ($scriptblock == Ucdn::SCRIPT_LATIN) {
			return 0x01;
		}
		if ($scriptblock == Ucdn::SCRIPT_CYRILLIC) {
			return 0x02;
		}
		if ($scriptblock == Ucdn::SCRIPT_GREEK) {
			return 0x04;
		}
		if ($scriptblock >= Ucdn::SCRIPT_HIRAGANA && $scriptblock <= Ucdn::SCRIPT_YI) {
			return 0x08;
		}

		return 0x80;
	}

}
