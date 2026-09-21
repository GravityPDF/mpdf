<?php

namespace Mpdf\Unicode;

/**
 * Emoji::clusters() against the sequences UTS #51 defines, each as the codepoints a document holds.
 */
class EmojiTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each emoji is found at its start, as long as its sequence is and asking for the presentation
	 * its selector or its properties give it
	 *
	 * @param int[]   $codepoints The run
	 * @param array[] $expected   What clusters() should give for it
	 *
	 * @dataProvider sequences
	 */
	public function testAnEmojiIsFoundWhole(array $codepoints, array $expected)
	{
		$this->assertSame($expected, Emoji::clusters($codepoints));
	}

	/**
	 * @return array[] Each run of codepoints, and the clusters() it should give
	 */
	public function sequences()
	{
		$emoji = Emoji::PRESENTATION_EMOJI;
		$text = Emoji::PRESENTATION_TEXT;
		$default = Emoji::PRESENTATION_DEFAULT;

		return [
			'a ZWJ family is one emoji' => [[0x61, 0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467, 0x62], [1 => [5, $emoji]]],
			'two regional indicators are a flag' => [[0x1F1E6, 0x1F1FA], [0 => [2, $emoji]]],
			'a third starts a flag of its own' => [[0x1F1E6, 0x1F1FA, 0x1F1E6], [0 => [2, $emoji], 2 => [1, $emoji]]],
			'a keycap with its selector' => [[0x31, 0xFE0F, 0x20E3], [0 => [3, $emoji]]],
			'a keycap without one' => [[0x23, 0x20E3], [0 => [2, $emoji]]],
			'a digit on its own is text' => [[0x31, 0x32], []],
			'a digit with a selector and no keycap is still text' => [[0x31, 0xFE0F], []],
			'a skin tone after a person' => [[0x1F44D, 0x1F3FD], [0 => [2, $emoji]]],
			'a skin tone after anything else stands alone' => [[0x1F600, 0x1F3FD], [0 => [1, $emoji], 1 => [1, $emoji]]],
			'the flag of England' => [[0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F], [0 => [7, $emoji]]],
			'tags without a cancel tag are not a sequence' => [[0x1F3F4, 0xE0067, 0xE0062], [0 => [1, $emoji]]],
			'a text selector asks for text' => [[0x1F600, 0xFE0E], [0 => [2, $text]]],
			'an emoji selector asks for colour' => [[0x2764, 0xFE0F], [0 => [2, $emoji]]],
			'an emoji that is text by default asks for nothing' => [[0x2764], [0 => [1, $default]]],
			'Emoji_Presentation asks for colour with no selector' => [[0x231A], [0 => [1, $emoji]]],
			'a joiner with nothing after it is not part of the emoji' => [[0x1F600, 0x200D], [0 => [1, $emoji]]],
			'a joined sequence asks for colour even if its first emoji does not' => [[0x2764, 0x200D, 0x1F525], [0 => [3, $emoji]]],
			'a pictograph newer than the tables is still joined' => [[0x1F468, 0x200D, 0x1FAFF], [0 => [3, $emoji]]],
			'a selected emoji can be joined' => [[0x1F3F3, 0xFE0F, 0x200D, 0x1F308], [0 => [4, $emoji]]],
			'letters are not emoji' => [[0x41, 0x42, 0x2014], []],
		];
	}

	/**
	 * A joiner, a selector or a tag only shapes the emoji around it; a keycap, a skin tone, a regional
	 * indicator or a digit draws something of its own
	 */
	public function testTheJoinersSelectorsAndTagsAreFormatting()
	{
		foreach ([0x200D, 0xFE0E, 0xFE0F, 0xE0020, 0xE0067, 0xE007F] as $codepoint) {
			$this->assertTrue(Emoji::isFormatting($codepoint), sprintf('U+%04X', $codepoint));
		}

		foreach ([0x20E3, 0x1F3FD, 0x1F1E6, 0x31] as $codepoint) {
			$this->assertFalse(Emoji::isFormatting($codepoint), sprintf('U+%04X draws something', $codepoint));
		}
	}

	/**
	 * The binary search finds a codepoint at either end of the tables, and misses one just past a range
	 */
	public function testThePropertyTablesAreSearchedAcrossTheirWholeRange()
	{
		$this->assertTrue(Emoji::hasEmojiPresentation(0x231A));
		$this->assertTrue(Emoji::hasEmojiPresentation(0x1FAFA));
		$this->assertFalse(Emoji::hasEmojiPresentation(0x2764));
		$this->assertFalse(Emoji::hasEmojiPresentation(0x1FAFB));
		$this->assertTrue(Emoji::isModifierBase(0x1F44D));
		$this->assertFalse(Emoji::isModifierBase(0x1F600));
		$this->assertTrue(Emoji::isExtendedPictographic(0x1FFFD));
	}
}
