<?php

namespace Mpdf\Css;

/**
 * The quotation marks a <q> is drawn with, from the quotes property or, for quotes: auto, its language
 */
final class Quotes
{

	/**
	 * @var string[][] The opening and closing marks of the outer and the inner quotation in each language, as HTML's
	 *                 rendering rules and CLDR give them. Looked up by the whole language tag, then by its first subtag
	 */
	private static $languages = [
		'ar' => ['”', '“', '’', '‘'],
		'cs' => ['„', '“', '‚', '‘'],
		'da' => ['»', '«', '›', '‹'],
		'de' => ['„', '“', '‚', '‘'],
		'el' => ['«', '»', '“', '”'],
		'en' => ['“', '”', '‘', '’'],
		'es' => ['«', '»', '“', '”'],
		'fi' => ['”', '”', '’', '’'],
		'fr' => ['«', '»', '«', '»'],
		'he' => ['”', '”', '’', '’'],
		'hu' => ['„', '”', '»', '«'],
		'it' => ['«', '»', '“', '”'],
		'ja' => ['「', '」', '『', '』'],
		'ko' => ['“', '”', '‘', '’'],
		'nb' => ['«', '»', '‘', '’'],
		'nl' => ['“', '”', '‘', '’'],
		'nn' => ['«', '»', '‘', '’'],
		'no' => ['«', '»', '‘', '’'],
		'pl' => ['„', '”', '«', '»'],
		'pt' => ['«', '»', '“', '”'],
		'pt-br' => ['“', '”', '‘', '’'],
		'ru' => ['«', '»', '„', '“'],
		'sv' => ['”', '”', '’', '’'],
		'tr' => ['“', '”', '‘', '’'],
		'uk' => ['«', '»', '„', '“'],
		'zh' => ['“', '”', '‘', '’'],
	];

	/**
	 * @param string|null $quotes The quotes value the element has, or null for none set
	 * @param string $lang The element's language, or empty for none
	 *
	 * @return string[][] The pairs of marks, outermost first, as [open, close]. Empty for quotes: none
	 */
	public static function pairs($quotes, $lang)
	{
		$quotes = $quotes === null ? 'auto' : trim($quotes);
		if (strtolower($quotes) === 'none') {
			return [];
		}

		$strings = CssString::parseList($quotes);
		if ($strings === null || count($strings) % 2 !== 0) {
			$strings = self::forLanguage($lang);
		}

		return array_chunk($strings, 2);
	}

	/**
	 * @param string $lang
	 *
	 * @return string[] The marks for the language, or English ones for a language not listed
	 */
	private static function forLanguage($lang)
	{
		$lang = strtolower(str_replace('_', '-', trim($lang)));
		if (isset(self::$languages[$lang])) {
			return self::$languages[$lang];
		}

		$primary = strtok($lang, '-');

		return $primary !== false && isset(self::$languages[$primary]) ? self::$languages[$primary] : self::$languages['en'];
	}
}
