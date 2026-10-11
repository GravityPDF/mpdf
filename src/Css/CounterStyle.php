<?php

namespace Mpdf\Css;

use Mpdf\Utils\UtfString;

/**
 * The list-style-type values that CSS Counter Styles defines and mPDF's own number conversions do not cover, and the
 * string markers, which the standard CSS mode draws
 */
final class CounterStyle
{

	/**
	 * @var string[][] The symbols of the alphabetic styles, in order
	 */
	private static $alphabets = [
		'upper-greek' => [
			'Α', 'Β', 'Γ', 'Δ', 'Ε', 'Ζ', 'Η', 'Θ', 'Ι', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ', 'Ο', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Φ', 'Χ',
			'Ψ', 'Ω',
		],
		'hiragana' => [
			'あ', 'い', 'う', 'え', 'お', 'か', 'き', 'く', 'け', 'こ', 'さ', 'し', 'す', 'せ', 'そ', 'た', 'ち', 'つ', 'て', 'と',
			'な', 'に', 'ぬ', 'ね', 'の', 'は', 'ひ', 'ふ', 'へ', 'ほ', 'ま', 'み', 'む', 'め', 'も', 'や', 'ゆ', 'よ', 'ら', 'り',
			'る', 'れ', 'ろ', 'わ', 'ゐ', 'ゑ', 'を', 'ん',
		],
		'katakana' => [
			'ア', 'イ', 'ウ', 'エ', 'オ', 'カ', 'キ', 'ク', 'ケ', 'コ', 'サ', 'シ', 'ス', 'セ', 'ソ', 'タ', 'チ', 'ツ', 'テ', 'ト',
			'ナ', 'ニ', 'ヌ', 'ネ', 'ノ', 'ハ', 'ヒ', 'フ', 'ヘ', 'ホ', 'マ', 'ミ', 'ム', 'メ', 'モ', 'ヤ', 'ユ', 'ヨ', 'ラ', 'リ',
			'ル', 'レ', 'ロ', 'ワ', 'ヰ', 'ヱ', 'ヲ', 'ン',
		],
		'hiragana-iroha' => [
			'い', 'ろ', 'は', 'に', 'ほ', 'へ', 'と', 'ち', 'り', 'ぬ', 'る', 'を', 'わ', 'か', 'よ', 'た', 'れ', 'そ', 'つ', 'ね',
			'な', 'ら', 'む', 'う', 'ゐ', 'の', 'お', 'く', 'や', 'ま', 'け', 'ふ', 'こ', 'え', 'て', 'あ', 'さ', 'き', 'ゆ', 'め',
			'み', 'し', 'ゑ', 'ひ', 'も', 'せ', 'す',
		],
		'katakana-iroha' => [
			'イ', 'ロ', 'ハ', 'ニ', 'ホ', 'ヘ', 'ト', 'チ', 'リ', 'ヌ', 'ル', 'ヲ', 'ワ', 'カ', 'ヨ', 'タ', 'レ', 'ソ', 'ツ', 'ネ',
			'ナ', 'ラ', 'ム', 'ウ', 'ヰ', 'ノ', 'オ', 'ク', 'ヤ', 'マ', 'ケ', 'フ', 'コ', 'エ', 'テ', 'ア', 'サ', 'キ', 'ユ', 'メ',
			'ミ', 'シ', 'ヱ', 'ヒ', 'モ', 'セ', 'ス',
		],
	];

	/**
	 * @var int[] The Georgian additive symbols, by the value each stands for, largest first
	 */
	private static $georgian = [
		10000 => 0x10F5, 9000 => 0x10F0, 8000 => 0x10EF, 7000 => 0x10F4, 6000 => 0x10EE, 5000 => 0x10ED, 4000 => 0x10EC,
		3000 => 0x10EB, 2000 => 0x10EA, 1000 => 0x10E9, 900 => 0x10E8, 800 => 0x10E7, 700 => 0x10E6, 600 => 0x10E5,
		500 => 0x10E4, 400 => 0x10F3, 300 => 0x10E2, 200 => 0x10E1, 100 => 0x10E0, 90 => 0x10DF, 80 => 0x10DE,
		70 => 0x10DD, 60 => 0x10F2, 50 => 0x10DC, 40 => 0x10DB, 30 => 0x10DA, 20 => 0x10D9, 10 => 0x10D8, 9 => 0x10D7,
		8 => 0x10F1, 7 => 0x10D6, 6 => 0x10D5, 5 => 0x10D4, 4 => 0x10D3, 3 => 0x10D2, 2 => 0x10D1, 1 => 0x10D0,
	];

	/**
	 * @var string[] The styles drawn here, as keys, to the suffix CSS gives them. Null stands for the
	 *               list_number_suffix configured for mPDF's other numbered styles
	 */
	private static $suffixes = [
		'decimal-leading-zero' => null,
		'upper-greek' => null,
		'armenian' => null,
		'upper-armenian' => null,
		'lower-armenian' => null,
		'georgian' => null,
		'hiragana' => '、',
		'katakana' => '、',
		'hiragana-iroha' => '、',
		'katakana-iroha' => '、',
		'disclosure-open' => '',
		'disclosure-closed' => '',
	];

	/**
	 * @param string $type A list-style-type value, as Mpdf::setCSS() keeps it
	 *
	 * @return bool Whether this class draws it
	 */
	public static function draws($type)
	{
		return self::isStyle($type) || CssString::isString($type);
	}

	/**
	 * @param string $type A list-style-type value, or a page number style
	 *
	 * @return bool Whether it names one of the counter styles drawn here
	 */
	public static function isStyle($type)
	{
		return array_key_exists($type, self::$suffixes);
	}

	/**
	 * @param string $value A declared list-style-type
	 *
	 * @return string It as the standard CSS mode keeps it: a string as written, a keyword lowercased
	 */
	public static function readType($value)
	{
		return CssString::isString($value) ? trim($value) : strtolower($value);
	}

	/**
	 * @param int $n The counter value
	 * @param string $type A style isStyle() is true of
	 * @param bool $rtl Whether the list runs right to left
	 *
	 * @return string The counter's representation, without the suffix. A value outside the style's range is
	 *                written in decimal, as CSS falls back to it
	 */
	public static function represent($n, $type, $rtl = false)
	{
		$n = (int) $n;

		if ($type === 'decimal-leading-zero') {
			return sprintf('%02d', $n);
		}

		if ($type === 'disclosure-open') {
			return "\xE2\x96\xBE"; // U+25BE
		}

		if ($type === 'disclosure-closed') {
			return $rtl ? "\xE2\x97\x82" : "\xE2\x96\xB8"; // U+25C2, U+25B8
		}

		if ($n < 1) {
			return (string) $n;
		}

		if (isset(self::$alphabets[$type])) {
			return self::alphabetic($n, self::$alphabets[$type]);
		}

		if ($type === 'georgian') {
			return $n > 19999 ? (string) $n : self::additive($n, self::$georgian);
		}

		if ($n > 9999) {
			return (string) $n;
		}

		// Armenian: one letter for each non-zero digit, in runs of nine from U+0531 (U+0561 lowercase)
		$first = $type === 'lower-armenian' ? 0x0561 : 0x0531;
		$digits = (string) $n;
		$length = strlen($digits);
		$text = '';
		for ($i = 0; $i < $length; $i++) {
			if ($digits[$i] !== '0') {
				$text .= UtfString::code2utf($first + 9 * ($length - 1 - $i) + (int) $digits[$i] - 1);
			}
		}

		return $text;
	}

	/**
	 * @param string $type A style isStyle() is true of
	 * @param string $defaultSuffix The list_number_suffix mPDF is configured with
	 *
	 * @return string The suffix drawn after the counter's representation
	 */
	public static function suffix($type, $defaultSuffix)
	{
		return self::$suffixes[$type] === null ? $defaultSuffix : self::$suffixes[$type];
	}

	/**
	 * @param int $n The counter value
	 * @param string $type A value draws() is true of
	 * @param string $defaultSuffix The list_number_suffix mPDF is configured with
	 *
	 * @return string The marker's text: a string marker as written, else the counter's representation and suffix
	 */
	public static function marker($n, $type, $defaultSuffix)
	{
		if (!self::isStyle($type)) {
			return CssString::single($type);
		}

		return self::represent($n, $type) . self::suffix($type, $defaultSuffix);
	}

	/**
	 * @param int $n Greater than 0
	 * @param string[] $symbols
	 *
	 * @return string
	 */
	private static function alphabetic($n, array $symbols)
	{
		$count = count($symbols);
		$text = '';
		while ($n > 0) {
			$n--;
			$text = $symbols[$n % $count] . $text;
			$n = (int) ($n / $count);
		}

		return $text;
	}

	/**
	 * @param int $n Greater than 0
	 * @param int[] $symbols Code points by value, largest first
	 *
	 * @return string
	 */
	private static function additive($n, array $symbols)
	{
		$text = '';
		foreach ($symbols as $value => $code) {
			while ($n >= $value) {
				$text .= UtfString::code2utf($code);
				$n -= $value;
			}
		}

		return $text;
	}
}
