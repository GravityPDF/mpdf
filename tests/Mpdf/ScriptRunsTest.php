<?php

namespace Mpdf;

/**
 * The rule that used to be copied into Otl, Mpdf, Image\Svg and ArabicJoining. Past the two that ask
 * startsARun() about a script directly, as ArabicJoining does, every case is built out of real
 * codepoints, so a Unicode release that reclassified one of them is read here as the failure it is.
 */
class ScriptRunsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const LATIN_A = 0x0041;
	const SPACE = 0x0020;
	const COMBINING_ACUTE = 0x0301;
	const NONCHARACTER = 0xFDD0;
	const ARABIC_ALEF = 0x0627;
	const END_OF_AYAH = 0x06DD;
	const HEBREW_ALEF = 0x05D0;

	public function testACharacterOfARealScriptStartsARun()
	{
		$this->assertTrue(ScriptRuns::startsARun(Ucdn::SCRIPT_LATIN));
		$this->assertTrue(ScriptRuns::startsARun(Ucdn::SCRIPT_ARABIC));
	}

	public function testTheThreeScriptsThatCarryNoShapingDoNotStartARun()
	{
		$this->assertFalse(ScriptRuns::startsARun(Ucdn::SCRIPT_COMMON));
		$this->assertFalse(ScriptRuns::startsARun(Ucdn::SCRIPT_INHERITED));
		$this->assertFalse(ScriptRuns::startsARun(Ucdn::SCRIPT_UNKNOWN));
	}

	/**
	 * The codepoints the rest of these tests stand each clause up with, so that a Unicode release which
	 * reclassified one of them is caught here rather than by a run splitting in an unexplained place.
	 */
	public function testTheCharactersTheseTestsUseAreTheScriptsTheyAreTakenFor()
	{
		$this->assertSame(Ucdn::SCRIPT_LATIN, Ucdn::get_script(self::LATIN_A));
		$this->assertSame(Ucdn::SCRIPT_ARABIC, Ucdn::get_script(self::ARABIC_ALEF));
		$this->assertSame(Ucdn::SCRIPT_HEBREW, Ucdn::get_script(self::HEBREW_ALEF));
		$this->assertSame(Ucdn::SCRIPT_COMMON, Ucdn::get_script(self::SPACE));
		$this->assertSame(Ucdn::SCRIPT_COMMON, Ucdn::get_script(self::END_OF_AYAH));
		$this->assertSame(Ucdn::SCRIPT_INHERITED, Ucdn::get_script(self::COMBINING_ACUTE));
		$this->assertSame(Ucdn::SCRIPT_UNKNOWN, Ucdn::get_script(self::NONCHARACTER));
	}

	public function testAStringOfOneScriptIsOneRunOfThatScript()
	{
		$runs = ScriptRuns::split([self::LATIN_A, self::LATIN_A, self::LATIN_A]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_LATIN, $runs[0]['script']);
		$this->assertSame([self::LATIN_A, self::LATIN_A, self::LATIN_A], $this->codepointsOf($runs[0]));
	}

	/**
	 * Common is the clause with the most traffic: a space or a comma inside a sentence would otherwise
	 * cut it into three, and each fragment would be shaped alone.
	 */
	public function testASpaceBetweenTwoLatinLettersLeavesThemInOneRun()
	{
		$runs = ScriptRuns::split([self::LATIN_A, self::SPACE, self::LATIN_A]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_LATIN, $runs[0]['script']);
		$this->assertSame([self::LATIN_A, self::SPACE, self::LATIN_A], $this->codepointsOf($runs[0]));
	}

	/**
	 * A combining mark is Inherited because its script is whatever it is attached to, and it has to be
	 * shaped together with that letter to be positioned on it at all.
	 */
	public function testACombiningMarkStaysWithTheLetterItFollows()
	{
		$runs = ScriptRuns::split([self::LATIN_A, self::COMBINING_ACUTE]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_LATIN, $runs[0]['script']);
		$this->assertSame([self::LATIN_A, self::COMBINING_ACUTE], $this->codepointsOf($runs[0]));
	}

	/**
	 * #251's review turned this one up. A codepoint the script table cannot name is folded into the run
	 * around it rather than ignored, so the Arabic shaper is handed it and its joining behaviour still
	 * counts - which is why ArabicJoining writes an entry for a character of no script.
	 */
	public function testACharacterOfNoKnownScriptIsFoldedIntoTheRunAroundItRatherThanDropped()
	{
		$runs = ScriptRuns::split([self::ARABIC_ALEF, self::NONCHARACTER, self::ARABIC_ALEF]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[0]['script']);
		$this->assertSame([self::ARABIC_ALEF, self::NONCHARACTER, self::ARABIC_ALEF], $this->codepointsOf($runs[0]));
	}

	public function testAChangeOfScriptStartsANewRunAndNumbersItsCharactersFromZero()
	{
		$runs = ScriptRuns::split([self::LATIN_A, self::LATIN_A, self::ARABIC_ALEF, self::HEBREW_ALEF]);

		$this->assertCount(3, $runs);
		$this->assertSame(Ucdn::SCRIPT_LATIN, $runs[0]['script']);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[1]['script']);
		$this->assertSame(Ucdn::SCRIPT_HEBREW, $runs[2]['script']);

		$this->assertSame([self::LATIN_A, self::LATIN_A], $this->codepointsOf($runs[0]));
		$this->assertSame([self::ARABIC_ALEF], $this->codepointsOf($runs[1]));
		$this->assertSame([self::HEBREW_ALEF], $this->codepointsOf($runs[2]));

		// Each caller numbers its own output by the key the run gives it, so the second run has to begin
		// at zero rather than carry on from the first
		$this->assertSame([0], array_keys($runs[1]['characters']));
	}

	/**
	 * A script the string returns to is a run of its own again. Nothing merges two runs of one script
	 * across a third, because the shaper is given each run as its own line of text.
	 */
	public function testAScriptReturnedToStartsAThirdRun()
	{
		$runs = ScriptRuns::split([self::LATIN_A, self::ARABIC_ALEF, self::LATIN_A]);

		$this->assertCount(3, $runs);
		$this->assertSame(Ucdn::SCRIPT_LATIN, $runs[0]['script']);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[1]['script']);
		$this->assertSame(Ucdn::SCRIPT_LATIN, $runs[2]['script']);

		$this->assertSame([self::LATIN_A], $this->codepointsOf($runs[0]));
		$this->assertSame([self::ARABIC_ALEF], $this->codepointsOf($runs[1]));
		$this->assertSame([self::LATIN_A], $this->codepointsOf($runs[2]));
	}

	/**
	 * Leading punctuation has no run to fall back into, so it opens the first one and that run takes the
	 * script of the first character to have one.
	 */
	public function testCharactersAheadOfTheFirstScriptOpenTheFirstRun()
	{
		$runs = ScriptRuns::split([self::SPACE, self::COMBINING_ACUTE, self::ARABIC_ALEF]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[0]['script']);
		$this->assertSame([self::SPACE, self::COMBINING_ACUTE, self::ARABIC_ALEF], $this->codepointsOf($runs[0]));
	}

	/**
	 * A string with no script in it at all still has to come back as a run, because every caller reads
	 * run zero without asking whether there is one.
	 */
	public function testAStringOfNothingButPunctuationIsOneRunOfNoScript()
	{
		$runs = ScriptRuns::split([self::SPACE, self::SPACE]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_COMMON, $runs[0]['script']);
		$this->assertSame([self::SPACE, self::SPACE], $this->codepointsOf($runs[0]));
	}

	public function testAnEmptyStringIsOneEmptyRunOfNoScript()
	{
		$runs = ScriptRuns::split([]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_COMMON, $runs[0]['script']);
		$this->assertSame([], $runs[0]['characters']);
	}

	/**
	 * The one departure from the table, for the reason ScriptRuns::END_OF_AYAH gives.
	 */
	public function testTheArabicEndOfAyahStartsARunOfArabicWhateverPrecedesIt()
	{
		$runs = ScriptRuns::split([self::LATIN_A, self::END_OF_AYAH]);

		$this->assertCount(2, $runs);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[1]['script']);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[1]['characters'][0]['script']);
	}

	public function testAnAyahMarkerAfterArabicStaysInTheArabicRunItNumbers()
	{
		$runs = ScriptRuns::split([self::ARABIC_ALEF, self::END_OF_AYAH]);

		$this->assertCount(1, $runs);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, $runs[0]['script']);
		$this->assertSame([self::ARABIC_ALEF, self::END_OF_AYAH], $this->codepointsOf($runs[0]));
	}

	/**
	 * The record is carried out with the character so that Otl, which reads two more of its fields, does
	 * not look the same codepoint up twice on every line it lays out.
	 */
	public function testTheRecordTheSplitReadIsHandedBackWithTheCharacter()
	{
		$runs = ScriptRuns::split([self::ARABIC_ALEF]);

		$this->assertSame(Ucdn::get_ucd_record(self::ARABIC_ALEF), $runs[0]['characters'][0]['record']);
	}

	/**
	 * @param array $run
	 *
	 * @return int[]
	 */
	private function codepointsOf($run)
	{
		$codepoints = [];

		foreach ($run['characters'] as $character) {
			$codepoints[] = $character['uni'];
		}

		return $codepoints;
	}

}
