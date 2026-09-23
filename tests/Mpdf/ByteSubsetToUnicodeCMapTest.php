<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * The ToUnicode CMap `Writer\FontWriter` writes for a font drawn a byte at a time, the path a font
 * with SIP or SMP coverage takes, which tells a reader what Unicode value each byte stands for.
 *
 * GravityPDF/mpdf#363 is that it wrote every entry of a subset in one bfchar block. ISO 32000-1
 * 9.10.3 and Adobe TN #5411 1.4.1 hold a block to 100 entries, and the first subset of such a font
 * is seeded with the whole ASCII range, so the block was over the limit before a character was
 * drawn. A reader that enforces it finds no Unicode value for the bytes past the hundredth.
 */
class ByteSubsetToUnicodeCMapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A document in one SMP font, which takes the byte-subset path, rendered uncompressed by the
	 * trait so that its CMaps can be read back out of it.
	 *
	 * @param string $html What the document draws
	 *
	 * @return string The document
	 */
	private function document($html)
	{
		return $this->render($html, [
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../../packages/Emoji/fonts'],
			'fontdata' => ['probe' => ['R' => 'NotoEmoji-Regular.ttf', 'useOTL' => 0]],
			'default_font' => 'probe',
		]);
	}

	/**
	 * The ToUnicode CMap of each subset of the document's one font, in the order they were written.
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function cmaps($pdf)
	{
		preg_match_all('/stream\n(\/CIDInit.*?)\nendstream/s', $pdf, $matches);

		$this->assertNotSame([], $matches[1], 'The document should carry a ToUnicode CMap');

		return $matches[1];
	}

	/**
	 * The bfchar blocks of a CMap, each holding its entries: the byte the document draws, and the
	 * Unicode value it stands for as the hexadecimal UTF-16BE the CMap gives it in.
	 *
	 * @param string $cmap
	 *
	 * @return array[][]
	 */
	private function blocks($cmap)
	{
		preg_match_all('/(\d+) beginbfchar\n(.*?)endbfchar\n/s', $cmap, $matches, PREG_SET_ORDER);

		$this->assertNotSame([], $matches, 'The CMap should hold bfchar entries');

		$blocks = [];
		foreach ($matches as $block) {
			$found = preg_match_all('/^<([0-9A-F]{2})> <([0-9A-F]{4}(?:[0-9A-F]{4})?)>$/m', $block[2], $entries, PREG_SET_ORDER);

			$this->assertSame(substr_count($block[2], "\n"), $found, 'Every line of a block should be an entry');
			$this->assertSame((int) $block[1], $found, 'A block should hold the number of entries it declares');

			$mappings = [];
			foreach ($entries as $entry) {
				$mappings[] = [hexdec($entry[1]), $entry[2]];
			}

			$blocks[] = $mappings;
		}

		return $blocks;
	}

	/**
	 * Every bfchar entry of a CMap, whichever block it is written in, in the order it was written.
	 *
	 * @param string $cmap
	 *
	 * @return array[]
	 */
	private function entries($cmap)
	{
		return call_user_func_array('array_merge', $this->blocks($cmap));
	}

	/**
	 * The rule one block of every entry broke: a bfchar block holds no more than a hundred entries,
	 * so a subset carrying more than that takes further blocks.
	 */
	public function testTheEntriesAreWrittenInBlocksOfAtMostAHundred()
	{
		$cmaps = $this->cmaps($this->document('<p>Hello &#x1F600;</p>'));

		$blocks = [];
		foreach ($cmaps as $cmap) {
			$blocks = array_merge($blocks, $this->blocks($cmap));
		}

		foreach ($blocks as $block) {
			$this->assertLessThanOrEqual(100, count($block), 'A bfchar block should hold no more than a hundred entries');
		}

		$this->assertGreaterThan(1, count($blocks), 'The ASCII range a subset is seeded with should take more than one block');
	}

	/**
	 * Breaking the entries into blocks leaves them the entries they were: every byte of the subset,
	 * in the order the subset holds them.
	 */
	public function testTheBlocksHoldEveryByteOfTheSubsetInOrder()
	{
		$entries = $this->entries($this->cmaps($this->document('<p>Hello</p>'))[0]);

		$codes = [];
		foreach ($entries as $entry) {
			$codes[] = $entry[0];
		}

		$this->assertSame(range(0, count($entries) - 1), $codes);
	}

	/**
	 * The ASCII range every subset is seeded with maps each byte to itself, the map a reader needs to
	 * read the text back.
	 */
	public function testTheAsciiRangeMapsToItself()
	{
		$entries = $this->entries($this->cmaps($this->document('<p>Hello</p>'))[0]);

		$mapping = [];
		foreach ($entries as $entry) {
			$mapping[$entry[0]] = $entry[1];
		}

		foreach (range(32, 127) as $code) {
			$this->assertSame(sprintf('%04X', $code), $mapping[$code]);
		}
	}

	/**
	 * A character above the basic plane, the coverage that sends a font down this path at all, is
	 * mapped as the surrogate pair its UTF-16 takes. It follows the ASCII range the subset is seeded
	 * with, so it is written in a later block - the entry a reader enforcing the limit used to lose.
	 */
	public function testACharacterAboveTheBasicPlaneIsMappedAsASurrogatePair()
	{
		$blocks = $this->blocks($this->cmaps($this->document('<p>Hello &#x1F600;</p>'))[0]);

		$written = null;
		foreach ($blocks as $number => $block) {
			foreach ($block as $entry) {
				if ($entry[1] === 'D83DDE00') {
					$written = $number;
				}
			}
		}

		$this->assertNotNull($written, 'U+1F600 should be mapped as the surrogate pair D83DDE00');
		$this->assertGreaterThan(0, $written, 'It follows the seeded ASCII range, so it should not be in the first block');
	}
}
