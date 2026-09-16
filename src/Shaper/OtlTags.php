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
	 * @return string The tag, 'DFLT' where the script offers that and not the language, or '' for neither
	 */
	public static function language($ietf, $available)
	{
		if ($available == '') {
			return '';
		}

		$tags = $ietf ? explode('-', $ietf) : [];

		$lang = isset($tags[0]) ? strtolower($tags[0]) : '';

		// The region, where the second subtag is one; a third subtag always is, the second being
		// the script
		$country = '';
		if (isset($tags[1]) && strlen($tags[1]) == 2) {
			$country = strtolower($tags[1]);
		}
		if (isset($tags[2]) && $tags[2]) {
			$country = strtolower($tags[2]);
		}

		if ($lang != '' && isset(Ucdn::$ot_languages[$lang])) {
			$langsys = Ucdn::$ot_languages[$lang];
		} elseif ($lang != '' && $country != '' && isset(Ucdn::$ot_languages[$lang . '-' . $country])) {
			$langsys = Ucdn::$ot_languages[$lang . '-' . $country];
		} else {
			$langsys = 'DFLT';
		}

		if (strpos($available, $langsys) !== false) {
			return $langsys;
		}

		return strpos($available, 'DFLT') !== false ? 'DFLT' : '';
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
