<?php

namespace Mpdf\Shaper;

use Mpdf\Utils\UtfString;

/**
 * Runs are built by hand here: one entry of char_data and one letter of group per character, and
 * GPOSinfo only for the characters that carry positioning.
 *
 * substr() past the end of a string gives false before PHP 7.0 and '' since, so a group cut down to
 * nothing is compared as a string.
 */
class OtlDataTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SOFT_HYPHEN = 0xAD;

	const NBSP = 0xA0;

	const IDEOGRAPHIC_SPACE = 0x3000;

	const EMOJI = 0x1F600;

	public function testSplitCutsEveryPartOfTheRunAtTheSameCharacter()
	{
		$run = $this->laidOut([0x41, 0x42, 0x43, 0x44], [1 => 'b', 3 => 'd']);

		$after = OtlData::split($run, 2);

		$this->assertSame('CC', $run['group']);
		$this->assertSame([0x41, 0x42], $this->unis($run));
		$this->assertSame([1 => 'b'], $run['GPOSinfo']);

		$this->assertSame('CC', $after['group']);
		$this->assertSame([0x43, 0x44], $this->unis($after));
		$this->assertSame([1 => 'd'], $after['GPOSinfo']);
	}

	/**
	 * A break at a space ends the line before the space and starts the next after it, so the space
	 * belongs to neither.
	 */
	public function testSplitLeavesOutWhatIsBetweenTheCutAndTheRestart()
	{
		$run = $this->laidOut([0x41, 0x20, 0x42], [0 => 'a', 1 => 'space', 2 => 'b']);

		$after = OtlData::split($run, 1, 2);

		$this->assertSame('C', $run['group']);
		$this->assertSame([0x41], $this->unis($run));
		$this->assertSame([0 => 'a'], $run['GPOSinfo']);

		$this->assertSame('C', $after['group']);
		$this->assertSame([0x42], $this->unis($after));
		$this->assertSame([0 => 'b'], $after['GPOSinfo']);
	}

	/**
	 * Mpdf passes the same position for both where nothing is left out, and 0 reads as no restart.
	 */
	public function testSplitRestartsAtTheCutWhereNoRestartIsGiven()
	{
		foreach (['', 0] as $restart) {
			$run = $this->laidOut([0x41, 0x42, 0x43]);

			$after = OtlData::split($run, 1, $restart);

			$this->assertSame([0x41], $this->unis($run));
			$this->assertSame([0x42, 0x43], $this->unis($after));
		}
	}

	public function testSplitAtTheStartLeavesAnEmptyRunBehind()
	{
		$run = $this->laidOut([0x41, 0x42], [0 => 'a']);

		$after = OtlData::split($run, 0);

		$this->assertSame('', $run['group']);
		$this->assertSame([], $run['char_data']);
		$this->assertSame([], $run['GPOSinfo']);

		$this->assertSame('CC', $after['group']);
		$this->assertSame([0x41, 0x42], $this->unis($after));
		$this->assertSame([0 => 'a'], $after['GPOSinfo']);
	}

	public function testSplitAtTheEndReturnsAnEmptyRun()
	{
		$run = $this->laidOut([0x41, 0x42], [1 => 'b']);

		$after = OtlData::split($run, 2);

		$this->assertSame('CC', $run['group']);
		$this->assertSame([1 => 'b'], $run['GPOSinfo']);

		$this->assertSame('', (string) $after['group']);
		$this->assertSame([], $after['char_data']);
		$this->assertSame([], $after['GPOSinfo']);
	}

	public function testSplitPutsThePositioningOfBothPartsInCharacterOrder()
	{
		$run = $this->laidOut([0x41, 0x42, 0x43, 0x44]);
		$run['GPOSinfo'] = [3 => 'd', 1 => 'b', 2 => 'c', 0 => 'a'];

		$after = OtlData::split($run, 2);

		$this->assertSame([0 => 'a', 1 => 'b'], $run['GPOSinfo']);
		$this->assertSame([0 => 'c', 1 => 'd'], $after['GPOSinfo']);
	}

	public function testSplitOfARunWithoutPositioningLeavesItWithoutAny()
	{
		$run = $this->laidOut([0x41, 0x42]);
		unset($run['GPOSinfo']);

		$after = OtlData::split($run, 1);

		$this->assertArrayNotHasKey('GPOSinfo', $run);
		$this->assertSame([], $after['GPOSinfo']);
	}

	public function testSliceTakesOnlyThePositioningInsideIt()
	{
		$run = $this->laidOut([0x41, 0x42, 0x43, 0x44], [0 => 'a', 1 => 'b', 3 => 'd']);

		$slice = OtlData::slice($run, 1, 2);

		$this->assertSame('CC', $slice['group']);
		$this->assertSame([0x42, 0x43], $this->unis($slice));
		$this->assertSame([0 => 'b'], $slice['GPOSinfo']);
		$this->assertSame($this->laidOut([0x41, 0x42, 0x43, 0x44], [0 => 'a', 1 => 'b', 3 => 'd']), $run, 'the run sliced is not changed');
	}

	public function testSliceOfNothingIsAnEmptyRun()
	{
		$run = $this->laidOut([0x41, 0x42], [0 => 'a']);

		$atTheEnd = OtlData::slice($run, 2, 1);
		$ofNoLength = OtlData::slice($run, 0, 0);

		$this->assertSame('', (string) $atTheEnd['group']);
		$this->assertSame([], $atTheEnd['char_data']);
		$this->assertSame([], $atTheEnd['GPOSinfo']);

		$this->assertSame('', (string) $ofNoLength['group']);
		$this->assertSame([], $ofNoLength['char_data']);
		$this->assertSame([], $ofNoLength['GPOSinfo']);
	}

	public function testSlicePutsThePositioningInCharacterOrder()
	{
		$run = $this->laidOut([0x41, 0x42, 0x43]);
		$run['GPOSinfo'] = [2 => 'c', 1 => 'b'];

		$this->assertSame([0 => 'b', 1 => 'c'], OtlData::slice($run, 1, 2)['GPOSinfo']);
	}

	/**
	 * applyOTL() leaves OTLdata an empty array for a blank string. See mpdf/mpdf#2158.
	 */
	public function testSliceOfAnEmptyArrayIsAnEmptyRun()
	{
		$this->assertSame(['GPOSinfo' => [], 'char_data' => [], 'group' => ''], OtlData::slice([], 0, 0));
	}

	public function testPrependCharMovesTheRestOfTheRunAlongByOne()
	{
		$run = $this->laidOut([0x42, 0x43], [0 => 'b', 1 => 'c']);

		OtlData::prependChar($run, ['uni' => 0x2D, 'bidi_class' => 12], 'C');

		$this->assertSame('CCC', $run['group']);
		$this->assertSame([0x2D, 0x42, 0x43], $this->unis($run));
		$this->assertSame([1 => 'b', 2 => 'c'], $run['GPOSinfo']);
	}

	public function testPrependCharToAnEmptyArrayMakesARunOfOne()
	{
		$run = [];

		OtlData::prependChar($run, ['uni' => 0x2D], 'C');

		$this->assertSame(['group' => 'C', 'char_data' => [['uni' => 0x2D]], 'GPOSinfo' => []], $run);
	}

	public function testRemoveCharTakesEveryOccurrenceOutOfTheTextAndTheRun()
	{
		$text = $this->text([0x41, self::SOFT_HYPHEN, 0x42, self::SOFT_HYPHEN, 0x43]);
		$run = $this->laidOut([0x41, self::SOFT_HYPHEN, 0x42, self::SOFT_HYPHEN, 0x43], [0 => 'a', 1 => 'shy', 2 => 'b', 4 => 'c']);

		OtlData::removeChar($text, $run, "\xc2\xad", 'UTF-8');

		$this->assertSame('ABC', $text);
		$this->assertSame('CCC', $run['group']);
		$this->assertSame([0x41, 0x42, 0x43], $this->unis($run));
		$this->assertSame([0 => 'a', 1 => 'b', 2 => 'c'], $run['GPOSinfo']);
	}

	/**
	 * The text is UTF-8 and the run has one entry per character, so the position found in the text
	 * has to be counted in characters: counted in bytes, the four-byte emoji ahead of the hyphen
	 * would put it three entries too far along.
	 */
	public function testRemoveCharCountsPositionsInCharactersNotBytes()
	{
		$text = $this->text([self::EMOJI, 0x41, self::SOFT_HYPHEN, 0x42]);
		$run = $this->laidOut([self::EMOJI, 0x41, self::SOFT_HYPHEN, 0x42], [0 => 'emoji', 3 => 'b']);

		OtlData::removeChar($text, $run, "\xc2\xad", 'UTF-8');

		$this->assertSame($this->text([self::EMOJI, 0x41, 0x42]), $text);
		$this->assertSame('CCC', $run['group']);
		$this->assertSame([self::EMOJI, 0x41, 0x42], $this->unis($run));
		$this->assertSame([0 => 'emoji', 2 => 'b'], $run['GPOSinfo']);
	}

	public function testRemoveCharAtEitherEnd()
	{
		$text = $this->text([self::SOFT_HYPHEN, 0x41, self::SOFT_HYPHEN]);
		$run = $this->laidOut([self::SOFT_HYPHEN, 0x41, self::SOFT_HYPHEN], [0 => 'first', 1 => 'a', 2 => 'last']);

		OtlData::removeChar($text, $run, "\xc2\xad", 'UTF-8');

		$this->assertSame('A', $text);
		$this->assertSame('C', $run['group']);
		$this->assertSame([0x41], $this->unis($run));
		$this->assertSame([0 => 'a'], $run['GPOSinfo']);
	}

	public function testRemoveCharOfACharacterNotInTheTextChangesNothing()
	{
		$text = 'AB';
		$run = $this->laidOut([0x41, 0x42], [1 => 'b']);

		OtlData::removeChar($text, $run, "\xc2\xad", 'UTF-8');

		$this->assertSame('AB', $text);
		$this->assertSame($this->laidOut([0x41, 0x42], [1 => 'b']), $run);
	}

	public function testNbspToSpaceTurnsEachNoBreakSpaceIntoASpaceInTheTextAndTheRun()
	{
		$text = $this->text([self::EMOJI, self::NBSP, 0x41, self::NBSP]);
		$run = $this->laidOut([self::EMOJI, self::NBSP, 0x41, self::NBSP]);

		OtlData::nbspToSpace($text, $run, 'UTF-8');

		$this->assertSame($this->text([self::EMOJI, 0x20, 0x41, 0x20]), $text);
		$this->assertSame([self::EMOJI, 0x20, 0x41, 0x20], $this->unis($run));
		$this->assertSame('CCCC', $run['group'], 'the group is not changed');
	}

	/**
	 * Shaping may have put something else in the no-break space's place in the run, and that is not
	 * turned into a space.
	 */
	public function testNbspToSpaceLeavesACharacterTheRunDoesNotHoldAsANoBreakSpace()
	{
		$text = $this->text([0x41, self::NBSP]);
		$run = $this->laidOut([0x41, 0xE000]);

		OtlData::nbspToSpace($text, $run, 'UTF-8');

		$this->assertSame('A ', $text);
		$this->assertSame([0x41, 0xE000], $this->unis($run));
	}

	public function testTrimDropsSpacesAndIdeographicSpacesFromBothEnds()
	{
		$run = $this->laidOut([0x20, self::IDEOGRAPHIC_SPACE, 0x41, 0x20, 0x42, self::IDEOGRAPHIC_SPACE, 0x20], [1 => 'lead', 2 => 'a', 4 => 'b', 6 => 'trail']);

		OtlData::trim($run);

		$this->assertSame('CSC', $run['group']);
		$this->assertSame([0x41, 0x20, 0x42], $this->unis($run));
		$this->assertSame([0 => 'a', 2 => 'b'], $run['GPOSinfo']);
	}

	public function testTrimOfTheStartOnly()
	{
		$run = $this->laidOut([0x20, 0x41, 0x20], [0 => 'lead', 1 => 'a', 2 => 'trail']);

		OtlData::trim($run, true, false);

		$this->assertSame('CS', $run['group']);
		$this->assertSame([0x41, 0x20], $this->unis($run));
		$this->assertSame([0 => 'a', 1 => 'trail'], $run['GPOSinfo']);
	}

	public function testTrimOfTheEndOnly()
	{
		$run = $this->laidOut([0x20, 0x41, 0x20], [0 => 'lead', 1 => 'a', 2 => 'trail']);

		OtlData::trim($run, false, true);

		$this->assertSame('SC', $run['group']);
		$this->assertSame([0x20, 0x41], $this->unis($run));
		$this->assertSame([0 => 'lead', 1 => 'a'], $run['GPOSinfo']);
	}

	public function testTrimOfARunWithNoSpaceAtEitherEndChangesNothing()
	{
		$run = $this->laidOut([0x41, 0x20, 0x42], [1 => 'space']);

		OtlData::trim($run);

		$this->assertSame($this->laidOut([0x41, 0x20, 0x42], [1 => 'space']), $run);
	}

	public function testTrimOfARunOfSpacesLeavesNothing()
	{
		$run = $this->laidOut([0x20, self::IDEOGRAPHIC_SPACE, 0x20], [1 => 'space']);

		OtlData::trim($run);

		$this->assertSame('', (string) $run['group']);
		$this->assertSame([], $run['char_data']);
		$this->assertSame([], $run['GPOSinfo']);
	}

	/**
	 * A chunk that was never laid out carries no run at all.
	 */
	public function testTrimOfSomethingThatIsNotARunChangesNothing()
	{
		$run = null;
		OtlData::trim($run);
		$this->assertNull($run);

		$run = ['group' => '', 'char_data' => null, 'GPOSinfo' => []];
		OtlData::trim($run);
		$this->assertSame(['group' => '', 'char_data' => null, 'GPOSinfo' => []], $run);
	}

	/**
	 * @param int[] $unicode
	 * @param array $GPOSinfo
	 *
	 * @return array A run of the characters, grouped as Mpdf::getBasicOTLdata() groups them
	 */
	private function laidOut($unicode, $GPOSinfo = [])
	{
		$run = ['group' => '', 'char_data' => [], 'GPOSinfo' => $GPOSinfo];
		foreach ($unicode as $char) {
			$run['group'] .= ($char == 0x20 || $char == self::IDEOGRAPHIC_SPACE) ? 'S' : 'C';
			$run['char_data'][] = ['bidi_class' => 0, 'uni' => $char];
		}

		return $run;
	}

	private function unis($run)
	{
		return array_column($run['char_data'], 'uni');
	}

	private function text($unicode)
	{
		return implode('', array_map([UtfString::class, 'code2utf'], $unicode));
	}

}
