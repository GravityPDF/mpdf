<?php

namespace Mpdf;

/**
 * The tables Ucdn reads a character's properties from used to stop at Unicode 6.1, so everything
 * added since fell through the index tables to the record of an unassigned codepoint - no general
 * category, no combining class, the neutral bidi class and no script. See GravityPDF/mpdf#101 for
 * what that cost, and #99 for the script half of it.
 *
 * These are the characters and the scripts those issues name. What is asserted is the property, not
 * the number behind it, except where the number itself is the contract.
 */
class UcdnTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A letter added after 6.1 is a letter, with the direction and the script of the block it sits in
	 *
	 * @dataProvider lettersAddedSinceUnicodeSixOne
	 */
	public function testALetterAddedSinceUnicodeSixOneIsReadAsItself($char, $category, $bidiClass, $script)
	{
		$this->assertSame($category, Ucdn::get_general_category($char), sprintf('U+%04X has the wrong general category', $char));
		$this->assertSame($bidiClass, Ucdn::get_bidi_class($char), sprintf('U+%04X has the wrong bidi class', $char));
		$this->assertSame($script, Ucdn::get_script($char), sprintf('U+%04X has the wrong script', $char));
	}

	public function lettersAddedSinceUnicodeSixOne()
	{
		return [
			'U+08AD ARABIC LETTER LOW ALEF (7.0)' => [0x08AD, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_AL, Ucdn::SCRIPT_ARABIC],
			'U+0870 ARABIC LETTER ALEF WITH ATTACHED FATHA (14.0)' => [0x0870, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_AL, Ucdn::SCRIPT_ARABIC],
			'U+05EF HEBREW YOD TRIANGLE (11.0)' => [0x05EF, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_R, Ucdn::SCRIPT_HEBREW],
			'U+1BC02 DUPLOYAN LETTER P (7.0)' => [0x1BC02, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_L, Ucdn::SCRIPT_DUPLOYAN],
			'U+16A40 MRO LETTER TA (7.0)' => [0x16A40, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_L, Ucdn::SCRIPT_MRO],
			'U+11700 AHOM LETTER KA (8.0)' => [0x11700, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_L, Ucdn::SCRIPT_AHOM],
			'U+1E900 ADLAM CAPITAL ALIF (9.0)' => [0x1E900, Ucdn::UNICODE_GENERAL_CATEGORY_UPPERCASE_LETTER, Ucdn::BIDI_CLASS_R, Ucdn::SCRIPT_ADLAM],
			'U+1B170 NUSHU CHARACTER-1B170 (10.0)' => [0x1B170, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_L, Ucdn::SCRIPT_NUSHU],
			'U+1E290 TOTO LETTER PA (14.0)' => [0x1E290, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_L, Ucdn::SCRIPT_TOTO],
			'U+1E2C0 WANCHO LETTER AA (12.0)' => [0x1E2C0, Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::BIDI_CLASS_L, Ucdn::SCRIPT_WANCHO],
		];
	}

	/**
	 * A mark added after 6.1 is a mark, which is what decides whether it is drawn on the character
	 * before it or beside it
	 */
	public function testAMarkAddedSinceUnicodeSixOneIsGroupedAsAMark()
	{
		$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK, Ucdn::get_general_category(0xA9E5), 'U+A9E5 MYANMAR SIGN SHAN SAW is not a non-spacing mark');
		$this->assertSame(Ucdn::BIDI_CLASS_NSM, Ucdn::get_bidi_class(0xA9E5));
		$this->assertSame(Ucdn::SCRIPT_MYANMAR, Ucdn::get_script(0xA9E5));
	}

	/**
	 * A character already in the tables before the regeneration reads as it always did
	 */
	public function testACharacterOlderThanTheTablesIsUnchanged()
	{
		$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, Ucdn::get_general_category(0x1000), 'U+1000 MYANMAR LETTER KA');
		$this->assertSame(Ucdn::BIDI_CLASS_L, Ucdn::get_bidi_class(0x1000));
		$this->assertSame(Ucdn::SCRIPT_MYANMAR, Ucdn::get_script(0x1000));

		$this->assertSame(230, Ucdn::get_combining_class(0x0301), 'U+0301 COMBINING ACUTE ACCENT');
		$this->assertSame(1, Ucdn::get_mirrored(0x0028), 'U+0028 LEFT PARENTHESIS mirrors');
		$this->assertSame(0, Ucdn::get_mirrored(0x0041), 'U+0041 LATIN CAPITAL LETTER A does not');
	}

	/**
	 * Otl reads the tag out of $uni_scriptblock with the script as the key and no check that it is
	 * there, so every script a record can carry has to have an entry - and a script that a font can
	 * declare needs a tag in it rather than the empty string that sends the text to DFLT.
	 */
	public function testEveryScriptHasATagExceptTheThreeThatAreNotOne()
	{
		$untagged = [Ucdn::SCRIPT_COMMON, Ucdn::SCRIPT_INHERITED, Ucdn::SCRIPT_UNKNOWN];

		foreach ($this->scriptConstants() as $script => $name) {
			$this->assertArrayHasKey($script, Ucdn::$uni_scriptblock, $name . ' has no entry at all');

			if (in_array($script, $untagged, true)) {
				$this->assertSame('', Ucdn::$uni_scriptblock[$script], $name . ' has a tag');
				continue;
			}

			$this->assertSame(4, strlen(Ucdn::$uni_scriptblock[$script]), $name . ' has no four-character tag');
		}
	}

	/**
	 * Every script in the record table is one of the constants, so nothing reads a script the rest of
	 * the code has no name for
	 */
	public function testNoCharacterCarriesAScriptWithoutAConstant()
	{
		$seen = [];
		for ($char = 0; $char < 0x110000; $char++) {
			$seen[Ucdn::get_script($char)] = true;
		}

		$this->assertSame([], array_diff_key($seen, $this->scriptConstants()), 'a character carries a script number no constant names');
	}

	/**
	 * @return string[] the name of every SCRIPT_ constant, by its number
	 */
	private function scriptConstants()
	{
		$reflection = new \ReflectionClass('Mpdf\Ucdn');
		$scripts = [];

		foreach ($reflection->getConstants() as $name => $value) {
			if (strpos($name, 'SCRIPT_') === 0) {
				$scripts[$value] = $name;
			}
		}

		return $scripts;
	}

	/**
	 * Otl chooses the Indic shaper for the scripts between Devanagari and Malayalam, and the CJK
	 * branch of useOTL for those between Hiragana and Yi, by comparing the numbers rather than by
	 * listing them. Regenerating the tables must leave a script where callers expect to find it - so
	 * these numbers are the contract, not an implementation detail.
	 */
	public function testTheScriptsTheShaperRangesRestOnKeepTheirNumbers()
	{
		$this->assertSame(9, Ucdn::SCRIPT_DEVANAGARI);
		$this->assertSame(17, Ucdn::SCRIPT_MALAYALAM);
		$this->assertSame(32, Ucdn::SCRIPT_HIRAGANA);
		$this->assertSame(36, Ucdn::SCRIPT_YI);
		$this->assertSame(40, Ucdn::SCRIPT_INHERITED);
		$this->assertSame(102, Ucdn::SCRIPT_UNKNOWN);
	}

	/**
	 * get_ucd_record() answers for anything, including a number past the last codepoint Unicode has,
	 * and what it answers there is a codepoint nothing is known about
	 */
	public function testACodepointPastTheEndOfUnicodeReadsAsUnassigned()
	{
		$record = Ucdn::get_ucd_record(0x110000);

		$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_UNASSIGNED, $record[0]);
		$this->assertSame(0, $record[1]);
		$this->assertSame(Ucdn::SCRIPT_UNKNOWN, $record[6]);
		$this->assertSame($record, Ucdn::get_ucd_record(0x0378), 'U+0378, which Unicode has not assigned either');
	}

}
