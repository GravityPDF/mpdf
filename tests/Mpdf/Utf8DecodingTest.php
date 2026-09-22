<?php

namespace Mpdf;

/**
 * UTF8StringToArray() reads a string it knows to be valid UTF-8 through mbstring and everything else
 * with the byte loop it has always used. These pin what it returns for both, and that decoding is
 * still what puts a character in the current font's subset.
 */
class Utf8DecodingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var int|string
	 */
	private $substituteCharacter;

	/**
	 * The default document, whose font is a TrueType font being subsetted, so that the current font has
	 * a subset for the characters to land in.
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();
		$this->substituteCharacter = mb_substitute_character();
	}

	/**
	 * Release the document's temporary files, and whatever a test did to mbstring.
	 */
	protected function tear_down()
	{
		parent::tear_down();

		mb_substitute_character($this->substituteCharacter);
		$this->mpdf->cleanup();
	}

	/**
	 * Valid UTF-8, one case per width of sequence and for the characters layout treats specially.
	 *
	 * @return array
	 */
	public function validStrings()
	{
		return [
			'empty' => ['', []],
			'ASCII' => ['Hi!', [72, 105, 33]],
			'NUL' => ["a\x00b", [97, 0, 98]],
			'two-byte, Latin-1 range' => ["caf\xc3\xa9", [99, 97, 102, 233]],
			'two-byte, soft hyphen' => ["co\xc2\xadop", [99, 111, 173, 111, 112]],
			'three-byte, U+200B' => ["a\xe2\x80\x8bb", [97, 8203, 98]],
			'three-byte, CJK' => ["\xe6\x97\xa5\xe6\x9c\xac", [26085, 26412]],
			'four-byte, emoji' => ["\xf0\x9f\x98\x80", [128512]],
			'four-byte, U+10FFFF' => ["\xf4\x8f\xbf\xbf", [1114111]],
			'a line of mixed scripts' => [
				"The caf\xc3\xa9 sign says \xe6\x97\xa5 and \xf0\x9f\x98\x80",
				[84, 104, 101, 32, 99, 97, 102, 233, 32, 115, 105, 103, 110, 32, 115, 97, 121, 115, 32, 26085, 32, 97, 110, 100, 32, 128512],
			],
		];
	}

	/**
	 * @dataProvider validStrings
	 *
	 * @param string $string
	 * @param int[] $expected
	 */
	public function testValidTextDecodesToItsCodePoints($string, array $expected)
	{
		$this->assertSame($expected, $this->mpdf->UTF8StringToArray($string, false));
	}

	/**
	 * Malformed UTF-8, and what mPDF has always read it as. mb_convert_encoding() would put a
	 * substitute character in place of each of these instead, which is why it never sees them.
	 *
	 * @return array
	 */
	public function malformedStrings()
	{
		return [
			'a stray continuation byte is dropped' => ["a\x80b", [97, 98]],
			'nothing but continuation bytes decodes to nothing' => ["\x80\xbf", []],
			'a byte above the valid range is dropped' => ["a\xffb", [97, 98]],
			'a two-byte sequence cut off by the end is dropped' => ["abc\xc3", [97, 98, 99]],
			'a three-byte sequence cut off by the end is dropped' => ["abc\xe2\x82", [97, 98, 99]],
			'a four-byte sequence cut off by the end is dropped' => ["ab\xf0\x9f\x98", [97, 98]],
			'a lead byte takes the character after it whatever it is' => ["a\xc3zb", [97, 250, 98]],
			'an overlong two-byte sequence is dropped' => ["\xc0\xaf", []],
			'an overlong three-byte sequence decodes to the character it spells' => ["\xe0\x80\xaf", [47]],
			'an overlong four-byte sequence decodes to the character it spells' => ["\xf0\x80\x80\xaf", [47]],
			'a surrogate decodes to the surrogate' => ["\xed\xa0\x80", [55296]],
			'a surrogate pair decodes to both halves' => ["\xed\xa0\xbd\xed\xb8\x80", [55357, 56832]],
			'a sequence above U+10FFFF decodes above U+10FFFF' => ["\xf4\x90\x80\x80", [1114112]],
			'a lead byte above F4 is dropped' => ["\xf5\x80\x80\x80", []],
			'a five-byte sequence is dropped' => ["\xf8\x88\x80\x80\x80", []],
			'ISO-8859-1 text loses its accented characters' => ["caf\xe9", [99, 97, 102]],
			'windows-1252 quotation marks are dropped' => ["\x93hi\x94", [104, 105]],
			'a chunk cut in the middle of a soft hyphen loses it' => [
				"16pt font-size \xc2",
				[49, 54, 112, 116, 32, 102, 111, 110, 116, 45, 115, 105, 122, 101, 32],
			],
			'a long malformed string still goes character by character' => [
				"one \x80 two \xe0\x80\xaf three \xff four",
				[111, 110, 101, 32, 32, 116, 119, 111, 32, 47, 32, 116, 104, 114, 101, 101, 32, 32, 102, 111, 117, 114],
			],
		];
	}

	/**
	 * @dataProvider malformedStrings
	 *
	 * @param string $string
	 * @param int[] $expected
	 */
	public function testMalformedTextDecodesAsTheByteLoopReadsIt($string, array $expected)
	{
		$this->assertSame($expected, $this->mpdf->UTF8StringToArray($string, false));
	}

	/**
	 * Layout hands over a line in chunks, so the same characters have to decode the same whether they
	 * arrive on their own or after a run of text.
	 */
	public function testALongStringDecodesItsCharactersAsAShortOneDoes()
	{
		$padding = 'the quick brown fox jumps over the lazy dog ';
		$head = $this->mpdf->UTF8StringToArray($padding, false);

		foreach (array_merge($this->validStrings(), $this->malformedStrings()) as $case) {
			$short = $this->mpdf->UTF8StringToArray($case[0], false);
			$long = $this->mpdf->UTF8StringToArray($padding . $case[0], false);

			$this->assertSame(array_merge($head, $short), $long, 'decoding ' . bin2hex($case[0]));
		}
	}

	/**
	 * Every string the two decoders are held to agree on: each single byte on its own and in company,
	 * each lead byte against a run of tails, and pseudo-random strings of the pieces a document is made
	 * of. Every one of them is tried twice, alone and after a run of text, so that both decoders see a
	 * string of every length rather than only the lengths a line happens to break at.
	 *
	 * @return string[]
	 */
	private function corpus()
	{
		$strings = ["\xef\xbb\xbfabc", str_repeat("Lorem \xc3\xa9 \xe6\x97\xa5 \xf0\x9f\x98\x80 ", 12)];

		$pieces = [];
		for ($b = 0; $b < 256; $b++) {
			$pieces[] = chr($b);
			$pieces[] = 'a' . chr($b);
			$pieces[] = chr($b) . 'a';
		}

		$tails = ["\x80", "\xbf", 'A', "\xc3", "\x80\x80", "\x80A", "\xbf\xbf", "\x80\x80\x80", "\x80\xc3\xa9"];
		foreach ([0xc0, 0xc1, 0xc2, 0xc3, 0xdf, 0xe0, 0xe1, 0xed, 0xef, 0xf0, 0xf4, 0xf5, 0xf7, 0xfd, 0xff] as $lead) {
			foreach ($tails as $tail) {
				$pieces[] = chr($lead) . $tail;
			}
		}

		// Generated here rather than with mt_rand(), whose sequence changed in PHP 7.1, and kept inside
		// 2^31 so that a 32-bit build draws the same strings as a 64-bit one
		$alphabet = ['a', 'Z', ' ', "\x00", "\xc2\xad", "\xc3\xa9", "\xe2\x80\x8b", "\xe6\x97\xa5", "\xf0\x9f\x98\x80",
			"\x80", "\xbf", "\xc0", "\xc1", "\xc3", "\xe2", "\xe2\x82", "\xf0\x9f", "\xf5", "\xff", "\xed\xa0\x80", "\xe0\x80\xaf"];
		$seed = 4711;
		for ($i = 0; $i < 1000; $i++) {
			$s = '';
			$seed = ($seed * 75 + 74) % 65537;
			$length = $seed % 25;
			for ($j = 0; $j < $length; $j++) {
				$seed = ($seed * 75 + 74) % 65537;
				$s .= $alphabet[$seed % count($alphabet)];
			}
			$pieces[] = $s;
		}

		foreach ($pieces as $piece) {
			$strings[] = $piece;
			$strings[] = 'the quick brown fox ' . $piece . ' jumps over it';
		}

		return $strings;
	}

	/**
	 * The byte loop as UTF8StringToArray() had it before mbstring decoded anything. It is frozen at
	 * that reading on purpose: it is what the function is held to, not a copy to be kept in step with
	 * whatever src/Mpdf.php says next.
	 *
	 * @param string $str
	 *
	 * @return int[]
	 */
	private function referenceDecode($str)
	{
		$out = [];
		$len = strlen($str);
		for ($i = 0; $i < $len; $i++) {
			$uni = -1;
			$h = ord($str[$i]);
			if ($h <= 0x7F) {
				$uni = $h;
			} elseif ($h >= 0xC2) {
				if (($h <= 0xDF) && ($i < $len - 1)) {
					$uni = ($h & 0x1F) << 6 | (ord($str[++$i]) & 0x3F);
				} elseif (($h <= 0xEF) && ($i < $len - 2)) {
					$uni = ($h & 0x0F) << 12 | (ord($str[++$i]) & 0x3F) << 6 | (ord($str[++$i]) & 0x3F);
				} elseif (($h <= 0xF4) && ($i < $len - 3)) {
					$uni = ($h & 0x0F) << 18 | (ord($str[++$i]) & 0x3F) << 12 | (ord($str[++$i]) & 0x3F) << 6 | (ord($str[++$i]) & 0x3F);
				}
			}
			if ($uni >= 0) {
				$out[] = $uni;
			}
		}

		return $out;
	}

	/**
	 * Every string of the corpus the decoder and the byte loop read differently, in hex.
	 *
	 * @return string[]
	 */
	private function disagreementsOverTheCorpus()
	{
		$disagreements = [];

		foreach ($this->corpus() as $string) {
			if ($this->mpdf->UTF8StringToArray($string, false) !== $this->referenceDecode($string)) {
				$disagreements[] = bin2hex($string);
			}
		}

		return $disagreements;
	}

	/**
	 * This is the test that fails if mbstring and the byte loop ever read a string differently.
	 */
	public function testTheTwoDecodersAgreeOverTheCorpus()
	{
		$this->assertSame([], $this->disagreementsOverTheCorpus());
	}

	/**
	 * A string's length decides which of the two decoders reads it, and where that line falls is a
	 * tuning number rather than a behaviour. Sweeping every length from one byte to well past any line
	 * worth drawing keeps both decoders under test wherever it is put, without this having to know.
	 */
	public function testBothDecodersAreExercisedAtEveryLength()
	{
		$strings = [
			'the quick brown fox jumps over the lazy dog and then rests a while', // valid at every cut
			"caf\xc3\xa9 \xe6\x97\xa5\xe6\x9c\xac \xf0\x9f\x98\x80 and some more text to pad it out", // cut mid-character
			"caf\xe9 \x80 \xe0\x80\xaf \xed\xa0\x80 and some more text that never was UTF-8", // never valid
		];

		$wrong = [];
		foreach ($strings as $string) {
			for ($len = 1; $len <= strlen($string); $len++) {
				$cut = substr($string, 0, $len);
				if ($this->mpdf->UTF8StringToArray($cut, false) !== $this->referenceDecode($cut)) {
					$wrong[] = bin2hex($cut);
				}
			}
		}

		$this->assertSame([], $wrong);
	}

	/**
	 * mb_convert_encoding() answers a byte it cannot read with mbstring.substitute_character, which a
	 * host is free to set to anything. Nothing here may depend on it.
	 */
	public function testTheSubstituteCharacterDoesNotReachTheDecoder()
	{
		mb_substitute_character(0x3000);

		$this->assertSame([], $this->disagreementsOverTheCorpus());
	}

	/**
	 * Decoding is what registers a character in the font being subsetted.
	 */
	public function testCharactersReachTheCurrentFontSubset()
	{
		$this->mpdf->CurrentFont['subset'] = [];
		$this->mpdf->UTF8StringToArray("a\xe2\x80\x8b\xf0\x9f\x98\x80");

		$this->assertSame([97 => 97, 8203 => 8203, 128512 => 128512], $this->mpdf->CurrentFont['subset']);

		$this->mpdf->CurrentFont['subset'] = [];
		$this->mpdf->UTF8StringToArray("ab \xe6\x97\xa5 ab \xe6\x97\xa5 ab");

		$this->assertSame([97 => 97, 98 => 98, 32 => 32, 26085 => 26085], $this->mpdf->CurrentFont['subset']);
	}

	/**
	 * A character the byte loop drops is not in the subset either, since it was never decoded. This is
	 * also the subset side effect on the fallback route, which the cases above do not reach.
	 */
	public function testOnlyDecodedCharactersReachTheSubset()
	{
		$this->mpdf->CurrentFont['subset'] = [];
		$this->mpdf->UTF8StringToArray("a\x80b\xe6\x97\xa5");

		$this->assertSame([97 => 97, 98 => 98, 26085 => 26085], $this->mpdf->CurrentFont['subset']);
	}

	/**
	 * The callers that only want the code points leave the subset alone, on either route.
	 */
	public function testTheSubsetIsUntouchedWhenAddSubsetIsFalse()
	{
		$this->mpdf->CurrentFont['subset'] = [];
		$this->mpdf->UTF8StringToArray("a long line with \xe6\x97\xa5 in it", false);
		$this->mpdf->UTF8StringToArray("a line with \x80 a stray byte in it", false);

		$this->assertSame([], $this->mpdf->CurrentFont['subset']);
	}

	/**
	 * A character already in the subset keeps the place it was given, so the order characters were
	 * first met in is the order the subset holds them.
	 */
	public function testTheSubsetKeepsTheOrderCharactersWereFirstMetIn()
	{
		$this->mpdf->CurrentFont['subset'] = [];
		$this->mpdf->UTF8StringToArray('banana and a long tail of text');

		$this->assertSame([98, 97, 110, 32, 100, 108, 111, 103, 116, 105, 102, 101, 120], array_keys($this->mpdf->CurrentFont['subset']));
	}
}
