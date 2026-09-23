<?php

namespace Mpdf;

use setasign\Fpdi\PdfParser\Type\PdfString;

/**
 * The ToUnicode CMap `Writer\FontWriter` writes for an Identity-H font, which tells a reader what
 * Unicode value the text it draws stands for.
 *
 * GravityPDF/mpdf#344 is that it mapped the whole two-byte space in one bfrange, `<0000> <FFFF>
 * <0000>`. ISO 32000-1 9.10.3 increments only the last byte of the destination over a range, and
 * asks that the byte is no more than 255 - (srcCode2 - srcCode1), so a range may not run past a
 * high-byte boundary. veraPDF reads such a range as its first 256 codes and finds no Unicode value
 * for any code above them.
 */
class ToUnicodeCMapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A document in one TrueType font, which the trait renders uncompressed so that its CMap and the
	 * codes it drew can both be read back out of it.
	 *
	 * @param string $html What the document draws
	 *
	 * @return string The document
	 */
	private function document($html)
	{
		return $this->render($html, [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['probe' => ['R' => 'Poppins-Regular.ttf', 'useOTL' => 0]],
			'default_font' => 'probe',
		]);
	}

	/**
	 * The ToUnicode CMap of the document's one font.
	 *
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function cmap($pdf)
	{
		$found = preg_match_all('/stream\n(\/CIDInit.*?)\nendstream/s', $pdf, $matches);

		$this->assertSame(1, $found, 'The document should carry one ToUnicode CMap');

		return $matches[1][0];
	}

	/**
	 * The bfrange blocks of a CMap, each holding its entries: the code a range starts at, the code it
	 * ends at, and the Unicode value the first code stands for.
	 *
	 * @param string $cmap
	 *
	 * @return array[][]
	 */
	private function blocks($cmap)
	{
		preg_match_all('/(\d+) beginbfrange\n(.*?)endbfrange\n/s', $cmap, $matches, PREG_SET_ORDER);

		$this->assertNotSame([], $matches, 'The CMap should hold bfrange entries');

		$blocks = [];
		foreach ($matches as $block) {
			$found = preg_match_all('/^<([0-9A-F]{4})> <([0-9A-F]{4})> <([0-9A-F]{4})>$/m', $block[2], $entries, PREG_SET_ORDER);

			$this->assertSame(substr_count($block[2], "\n"), $found, 'Every line of a block should be an entry');
			$this->assertSame((int) $block[1], $found, 'A block should hold the number of entries it declares');

			$ranges = [];
			foreach ($entries as $entry) {
				$ranges[] = [hexdec($entry[1]), hexdec($entry[2]), hexdec($entry[3])];
			}

			$blocks[] = $ranges;
		}

		return $blocks;
	}

	/**
	 * Every bfrange entry of a CMap, whichever block it is written in.
	 *
	 * @param string $cmap
	 *
	 * @return array[]
	 */
	private function ranges($cmap)
	{
		return call_user_func_array('array_merge', $this->blocks($cmap));
	}

	/**
	 * What the CMap maps each code to, as Unicode values by code.
	 *
	 * A range increments the last byte of its destination, which is the same as counting up from it
	 * as long as the range stays inside one high byte, as the first test asserts every range does.
	 *
	 * @param string $cmap
	 *
	 * @return int[]
	 */
	private function mapping($cmap)
	{
		$mapping = [];

		foreach ($this->ranges($cmap) as $range) {
			list($low, $high, $destination) = $range;
			for ($code = $low; $code <= $high; $code++) {
				$mapping[$code] = $destination + ($code - $low);
			}
		}

		return $mapping;
	}

	/**
	 * The codes the document drew, in the order it drew them. An Identity-H font takes them as the
	 * two-byte units of the strings the page draws.
	 *
	 * @param string $pdf
	 *
	 * @return int[]
	 */
	private function drawn($pdf)
	{
		$text = '';
		foreach ($this->pages($pdf) as $stream) {
			$text .= PdfString::unescape($this->drawnText($stream));
		}

		$this->assertNotSame('', $text, 'The document should draw text');

		return array_values(unpack('n*', $text));
	}

	/**
	 * A run of characters straight across a high-byte boundary, which the map has to break in two.
	 *
	 * @return string
	 */
	private function textAcrossAHighByte()
	{
		$text = '';
		for ($code = 0x00F0; $code <= 0x0110; $code++) {
			$text .= '&#x' . dechex($code) . ';';
		}

		return $text;
	}

	/**
	 * Characters two apart, which cannot be run together into ranges and so take more entries than
	 * one block holds.
	 *
	 * @return string
	 */
	private function scatteredText()
	{
		$text = '';
		for ($code = 0x0100; $code < 0x0300; $code += 2) {
			$text .= '&#x' . dechex($code) . ';';
		}

		return $text;
	}

	/**
	 * The rule the single `<0000> <FFFF>` range broke: a range may only run to the end of the high
	 * byte it starts in, so a document drawing straight across one takes two ranges.
	 */
	public function testNoRangeCrossesAHighByteBoundary()
	{
		$ranges = $this->ranges($this->cmap($this->document('<p>' . $this->textAcrossAHighByte() . '</p>')));

		foreach ($ranges as $range) {
			list($low, $high) = $range;
			$this->assertSame($low >> 8, $high >> 8, sprintf('The range <%04X> <%04X> crosses a high-byte boundary', $low, $high));
		}

		$this->assertContains([0x00F0, 0x00FF, 0x00F0], $ranges, 'The run should break where the high byte turns over');
		$this->assertContains([0x0100, 0x0110, 0x0100], $ranges, 'The rest of the run should carry on in a range of its own');
	}

	/**
	 * Ranges beyond the hundred a block may hold are written as further blocks.
	 */
	public function testTheRangesAreWrittenInBlocksOfAtMostAHundred()
	{
		$blocks = $this->blocks($this->cmap($this->document('<p>' . $this->scatteredText() . '</p>')));

		$this->assertGreaterThan(1, count($blocks), 'The document should take more than one block');

		foreach ($blocks as $block) {
			$this->assertLessThanOrEqual(100, count($block));
		}
	}

	/**
	 * What the document drew still reads back as the text it was given, through the map alone.
	 */
	public function testTheTextDrawnMapsBackToItsUnicode()
	{
		$text = 'Hello, café — €100';

		$pdf = $this->document('<p>' . $text . '</p>');
		$mapping = $this->mapping($this->cmap($pdf));

		$read = '';
		foreach ($this->drawn($pdf) as $code) {
			$this->assertArrayHasKey($code, $mapping, sprintf('The map should cover the code <%04X> the document drew', $code));
			$read .= mb_convert_encoding(pack('n', $mapping[$code]), 'UTF-8', 'UTF-16BE');
		}

		$this->assertSame($text, $read);
	}

	/**
	 * The map is over what the font holds: the 32-127 range every subset carries whether the document
	 * drew it or not, and the codes the document drew. Not over the whole two-byte space.
	 */
	public function testTheMapIsTheAsciiRangeAndTheCodesDrawn()
	{
		$mapping = $this->mapping($this->cmap($this->document('<p>Hello</p>')));

		foreach (range(32, 127) as $code) {
			$this->assertSame($code, $mapping[$code]);
		}

		$this->assertArrayNotHasKey(0x20AC, $mapping);
	}
}
