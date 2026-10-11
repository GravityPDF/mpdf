<?php

namespace Mpdf\Css;

use Mpdf\Utils\UtfString;

/**
 * Reads CSS strings, such as those of list-style-type and quotes, with their escapes decoded
 */
final class CssString
{

	/**
	 * @param string $value A declared value
	 *
	 * @return string[]|null The strings the value is made of, separated by white space, or null when anything else is
	 *                       in it
	 */
	public static function parseList($value)
	{
		$value = trim($value);
		if ($value === '' || !preg_match_all('/\G\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/su', $value, $m)) {
			return null;
		}

		if (strlen(implode('', $m[0])) !== strlen($value)) {
			return null;
		}

		$strings = [];
		foreach ($m[1] as $quoted) {
			$strings[] = self::decode($quoted);
		}

		return $strings;
	}

	/**
	 * @param string $value A declared value
	 *
	 * @return bool Whether the value is a single string
	 */
	public static function isString($value)
	{
		$strings = self::parseList($value);

		return $strings !== null && count($strings) === 1;
	}

	/**
	 * @param string $quoted A string with its quotes
	 *
	 * @return string Its text, with each escape replaced by the character it stands for. An escaped newline is
	 *                dropped, as CSS continues the string on the next line
	 */
	private static function decode($quoted)
	{
		return preg_replace_callback('/\\\\(?:([0-9a-fA-F]{1,6})(?:\r\n|[ \t\r\n\f])?|(\r\n|[\r\n\f])|(.))/su', function ($m) {
			if (isset($m[3]) && $m[3] !== '') {
				return $m[3];
			}

			if (isset($m[2]) && $m[2] !== '') {
				return '';
			}

			$code = hexdec($m[1]);

			return $code === 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF) ? "\xEF\xBF\xBD" : UtfString::code2utf($code);
		}, substr($quoted, 1, -1));
	}
}
