<?php

namespace Mpdf;

use Mpdf\Utils\UtfString;

/**
 * The Unicode bidi algorithm used to be private to Otl, reachable only by rendering a document.
 * Now it can be asked directly what it resolved, which is what these tests do.
 *
 * sort() returns the characters of one chunk in display order as a UTF-8 string, plus a bitmask of
 * which strong directions the chunk carried: 1 for L, 2 for R. char_data is left in logical order.
 */
class BidiTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ALEF = 0x05D0;

	const BET = 0x05D1;

	const ARABIC_ALEF = 0x0627;

	/** U+08AD ARABIC LETTER LOW ALEF, added in Unicode 7.0 */
	const LOW_ALEF = 0x08AD;

	/** U+0870 ARABIC LETTER ALEF WITH ATTACHED FATHA, added in Unicode 14.0 */
	const ALEF_WITH_FATHA = 0x0870;

	/**
	 * L2 with an LTR paragraph level: the Hebrew run is the only sequence above level 0, so it is the
	 * only one reversed
	 */
	public function testAStrongRtlRunInsideLtrTextComesBackReversed()
	{
		list($ordered, $strong) = $this->sort([0x41, 0x42, self::ALEF, self::BET, 0x43], 'ltr');

		$this->assertSame([0x41, 0x42, self::BET, self::ALEF, 0x43], $ordered);
		$this->assertSame(3, $strong, 'the chunk carries both an L and an R character');
	}

	/**
	 * L2 with an RTL paragraph level: every character is at level 1 or above, so the whole line
	 * reverses and the Latin run - at level 2 - reverses again back into its own order
	 */
	public function testAnLtrRunInsideRtlTextKeepsItsOwnOrder()
	{
		list($ordered) = $this->sort([self::ALEF, self::BET, 0x41, 0x42], 'rtl');

		$this->assertSame([0x41, 0x42, self::BET, self::ALEF], $ordered);
	}

	/**
	 * W2 then I1: European digits after an Arabic letter resolve to Arabic numbers, which take level
	 * 2 in an RTL paragraph and so read left to right inside the reversed run
	 */
	public function testEuropeanDigitsAfterAnArabicLetterBecomeAnArabicNumber()
	{
		list($ordered) = $this->sort([self::ARABIC_ALEF, 0x31, 0x32], 'rtl');

		$this->assertSame([0x31, 0x32, self::ARABIC_ALEF], $ordered);
	}

	/**
	 * The same, for two Arabic letters added to Unicode after 6.1. They used to read as unassigned
	 * codepoints, which are neutral - a run of them took its direction from its neighbours instead of
	 * setting it, and so was laid out in the order it was typed. See GravityPDF/mpdf#101.
	 */
	public function testAnRtlRunOfLettersAddedSinceUnicodeSixOneComesBackReversed()
	{
		list($ordered, $strong) = $this->sort([0x41, self::LOW_ALEF, self::ALEF_WITH_FATHA, 0x42], 'ltr');

		$this->assertSame([0x41, self::ALEF_WITH_FATHA, self::LOW_ALEF, 0x42], $ordered);
		$this->assertSame(3, $strong, 'the chunk carries no strong right-to-left character');
	}

	public function testLatinOnlyTextIsUntouchedAndReportsOnlyL()
	{
		list($ordered, $strong) = $this->sort([0x41, 0x42, 0x43], 'ltr');

		$this->assertSame([0x41, 0x42, 0x43], $ordered);
		$this->assertSame(1, $strong);
	}

	/**
	 * L4: a bracket keeps its meaning rather than its shape, so an R-resolved one is swapped for its
	 * mirror before the run is reversed
	 */
	public function testABracketInARtlRunIsMirrored()
	{
		list($ordered) = $this->sort([self::ALEF, 0x28, self::BET, 0x29], 'rtl');

		$this->assertSame([0x28, self::BET, 0x29, self::ALEF], $ordered);
	}

	/**
	 * @return array [the codepoints in display order, the strong-direction bitmask]
	 */
	private function sort($unicode, $dir)
	{
		$otlData = ['group' => '', 'GPOSinfo' => [], 'char_data' => []];
		$str = '';
		foreach ($unicode as $char) {
			$record = Ucdn::get_ucd_record($char);
			$otlData['char_data'][] = ['bidi_class' => $record[2], 'uni' => $char];
			$otlData['group'] .= 'C';
			$str .= UtfString::code2utf($char);
		}

		list($ordered, $strong) = Bidi::sort($unicode, $str, $dir, $otlData, false);

		return [array_values(unpack('N*', mb_convert_encoding($ordered, 'UTF-32BE', 'UTF-8'))), $strong];
	}

}
