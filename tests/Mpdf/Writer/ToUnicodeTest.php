<?php

namespace Mpdf\Writer;

/**
 * The ToUnicode CMap both byte-subset writers build, which carries the text layer of a font written
 * a byte at a time: FontWriter for a font with SMP or SIP coverage, Type3FontWriter for a colour
 * font, where one byte can copy out as several characters.
 *
 * GravityPDF/mpdf#298 is that the two wrote it twice, each with its own idea of how to turn a code
 * point into hexadecimal and how large a bfchar block may be. The block limit is the one that bites:
 * ISO 32000-1 9.10.3 holds a block to a hundred entries, and a reader that enforces it finds no
 * Unicode value for the bytes past that point.
 */
class ToUnicodeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The bfchar blocks of a CMap, each holding the entry lines written inside it.
	 *
	 * @param string $cmap
	 *
	 * @return string[][]
	 */
	private function blocks($cmap)
	{
		preg_match_all('/(\d+) beginbfchar\n(.*?)\nendbfchar\n/s', $cmap, $matches, PREG_SET_ORDER);

		$blocks = [];
		foreach ($matches as $block) {
			$entries = explode("\n", $block[2]);

			$this->assertSame((int) $block[1], count($entries), 'A block should hold the number of entries it declares');

			$blocks[] = $entries;
		}

		return $blocks;
	}

	/**
	 * Every entry of a CMap, whichever block it was written in, as byte => the hexadecimal it maps to.
	 *
	 * @param string $cmap
	 *
	 * @return string[]
	 */
	private function entries($cmap)
	{
		$entries = [];
		foreach ($this->blocks($cmap) as $block) {
			foreach ($block as $entry) {
				$this->assertSame(1, preg_match('/^<([0-9A-F]{2})> <((?:[0-9A-F]{4})+)>$/', $entry, $match), 'An entry should be a byte and its code units');

				$entries[hexdec($match[1])] = $match[2];
			}
		}

		return $entries;
	}

	/**
	 * The CMap names itself an Adobe-Identity-UCS map of one byte codes, which is what tells a reader
	 * to read its entries as the text of a simple font.
	 */
	public function testTheCMapDeclaresAOneByteCodeSpace()
	{
		$cmap = ToUnicode::byteCMap([0 => 65]);

		$this->assertStringContainsString("/CIDSystemInfo <</Registry (Adobe) /Ordering (UCS) /Supplement 0>> def\n", $cmap);
		$this->assertStringContainsString("/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n", $cmap);
		$this->assertStringContainsString("1 begincodespacerange\n<00> <FF>\nendcodespacerange\n", $cmap);
		$this->assertStringEndsWith("endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n", $cmap);
	}

	/**
	 * The rule a subset larger than a block used to break: a bfchar block holds a hundred entries at
	 * most, so a subset of every byte a simple font has takes three blocks and loses none of them.
	 */
	public function testASubsetLargerThanABlockIsSplitAndCopiesBackEveryCharacter()
	{
		$subset = [];
		foreach (range(0, 254) as $code) {
			$subset[$code] = 0x4E00 + $code;
		}

		$cmap = ToUnicode::byteCMap($subset);

		$blocks = $this->blocks($cmap);
		$this->assertSame([100, 100, 55], array_map('count', $blocks));

		$entries = $this->entries($cmap);
		$this->assertSame(array_keys($subset), array_keys($entries), 'Every byte of the subset should be mapped, in the order the subset holds them');

		foreach ($subset as $code => $char) {
			$this->assertSame(sprintf('%04X', $char), $entries[$code]);
		}
	}

	/**
	 * A colour font maps a ligature back to the characters it was formed from, so a copied emoji
	 * family is the ZWJ sequence it was typed as rather than the one glyph it is drawn as.
	 */
	public function testAByteMayStandForSeveralCharacters()
	{
		$family = [0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467];

		$entries = $this->entries(ToUnicode::byteCMap([7 => $family]));

		$this->assertSame('D83DDC68200DD83DDC69200DD83DDC67', $entries[7]);
	}

	/**
	 * A character is written as the code units UTF-16 gives it: itself inside the basic plane, and the
	 * surrogate pair it splits into above it, which is the coverage that sends a font down this path.
	 *
	 * @dataProvider codePointProvider
	 *
	 * @param int    $char     A code point
	 * @param string $expected The hexadecimal it should be written as
	 */
	public function testACharacterIsWrittenAsItsCodeUnits($char, $expected)
	{
		$entries = $this->entries(ToUnicode::byteCMap([0 => $char]));

		$this->assertSame($expected, $entries[0]);
	}

	/**
	 * @return array[] A code point, and the hexadecimal a CMap writes it as
	 */
	public function codePointProvider()
	{
		return [
			'the byte a padded subset entry holds' => [0, '0000'],
			'an ascii character' => [0x41, '0041'],
			'the last character of the basic plane' => [0xFFFF, 'FFFF'],
			'the first character above it' => [0x10000, 'D800DC00'],
			'an emoji' => [0x1F600, 'D83DDE00'],
			'the last character Unicode has' => [0x10FFFF, 'DBFFDFFF'],
		];
	}

	/**
	 * A font that drew nothing writes no block at all, rather than an empty one a reader would have to
	 * make sense of.
	 */
	public function testAnEmptyMapWritesNoBlock()
	{
		$this->assertSame([], $this->blocks(ToUnicode::byteCMap([])));
	}
}
