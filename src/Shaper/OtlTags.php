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

		$subtags = $ietf ? explode('-', strtolower($ietf)) : [];

		if (isset($subtags[0]) && $subtags[0] == 'zh') {
			$candidates = self::chinese($subtags);
		} elseif (isset($subtags[0]) && isset(Ucdn::$ot_languages[$subtags[0]])) {
			$candidates = [Ucdn::$ot_languages[$subtags[0]]];
		} else {
			$candidates = [];
		}

		foreach ($candidates as $langsys) {
			if (strpos($available, $langsys) !== false) {
				return $langsys;
			}
		}

		return strpos($available, 'DFLT') !== false ? 'DFLT' : '';
	}

	/**
	 * The language systems for Chinese, in the order HarfBuzz tries them
	 * (hb_ot_tags_from_complex_language() in hb-ot-tag-table.hh).
	 *
	 * The script subtag outranks the region, so zh-Hans-HK is Simplified, except that Traditional
	 * Chinese in Hong Kong or Macao keeps its region's tag. Macao has its own ZHTM, and where a font
	 * lacks it takes Hong Kong's ZHH before the default. A tag with neither is Simplified.
	 *
	 * The zh- keys of Ucdn::$ot_languages are not read: they hold one tag per region, and nothing for
	 * the script or for zh alone.
	 *
	 * @param string[] $subtags The lower-cased subtags of a tag whose language is zh
	 *
	 * @return string[]
	 */
	private static function chinese(array $subtags)
	{
		$regions = [
			'hk' => ['ZHH '],
			'mo' => ['ZHTM', 'ZHH '],
			'tw' => ['ZHT '],
		];

		$script = isset($subtags[1]) ? $subtags[1] : '';

		if ($script == 'hant' && isset($subtags[2]) && ($subtags[2] == 'hk' || $subtags[2] == 'mo')) {
			return $regions[$subtags[2]];
		}
		if ($script == 'hans') {
			return ['ZHS '];
		}
		if ($script == 'hant') {
			return ['ZHT '];
		}

		foreach ($regions as $region => $langsys) {
			if (in_array($region, $subtags, true)) {
				return $langsys;
			}
		}

		return ['ZHS '];
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
