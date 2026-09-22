<?php

namespace Mpdf;

/**
 * The ToUnicode CMap `Writer\FontWriter` writes for an Identity-H font, which tells a reader what
 * Unicode value the text it draws stands for.
 *
 * GravityPDF/mpdf#344 is that it mapped the whole two-byte space in one bfrange, `<0000> <FFFF>
 * <0000>`. ISO 32000-1 9.10.3 increments only the last byte of the destination over a range, and
 * asks that the byte is no more than 255 - (srcCode2 - srcCode1), so a range may not run past a
 * high-byte boundary and the space needs 256 of them rather than one. veraPDF reads such a range as
 * its first 256 codes and finds no Unicode value for any code above them.
 */
class ToUnicodeCMapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A document in one TrueType font, written uncompressed so that its CMap and the codes it drew
	 * can be read back out of it.
	 *
	 * @param string $html What the document draws
	 *
	 * @return string The document
	 */
	private function render($html)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['probe' => ['R' => 'Poppins-Regular.ttf', 'useOTL' => 0]],
			'default_font' => 'probe',
		]);
		$mpdf->compress = false;
		$mpdf->WriteHTML($html);

		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

		return $pdf;
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
	 * The bfrange entries of a CMap, each as the integer code it starts at, the one it ends at, and
	 * the Unicode value the first code stands for.
	 *
	 * @param string $cmap
	 *
	 * @return array[]
	 */
	private function ranges($cmap)
	{
		$ranges = [];

		preg_match_all('/(\d+) beginbfrange\n(.*?)endbfrange\n/s', $cmap, $blocks, PREG_SET_ORDER);

		$this->assertNotSame([], $blocks, 'The CMap should hold bfrange entries');

		foreach ($blocks as $block) {
			$entries = preg_match_all('/^<([0-9A-F]{4})> <([0-9A-F]{4})> <([0-9A-F]{4})>$/m', $block[2], $matches, PREG_SET_ORDER);

			$this->assertSame(substr_count($block[2], "\n"), $entries, 'Every line of a bfrange block should be an entry');
			$this->assertSame((int) $block[1], $entries, 'A bfrange block should hold the number of entries it declares');
			$this->assertLessThanOrEqual(100, $entries, 'A bfrange block may hold at most 100 entries');

			foreach ($matches as $entry) {
				$ranges[] = [hexdec($entry[1]), hexdec($entry[2]), hexdec($entry[3])];
			}
		}

		return $ranges;
	}

	/**
	 * What the CMap maps each code to, as Unicode values by code.
	 *
	 * The last byte of the destination is what a range increments, which is the same as counting up
	 * from it while the range stays inside one high byte - which is what the first test asserts.
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
	 * two-byte units of a string, which `Writer\BaseWriter::escape()` has put its brackets and its
	 * backslashes behind a backslash in.
	 *
	 * @param string $pdf
	 *
	 * @return int[]
	 */
	private function drawn($pdf)
	{
		$found = preg_match_all('/\((.*?)\)\s*Tj/s', $pdf, $matches);

		$this->assertGreaterThan(0, $found, 'The document should draw text');

		$codes = [];
		foreach ($matches[1] as $string) {
			$codes = array_merge($codes, array_values(unpack('n*', preg_replace('/\\\\([()\\\\])/', '$1', $string))));
		}

		return $codes;
	}

	/**
	 * A document drawing characters two apart, which cannot be run together into ranges and so takes
	 * more entries than one block holds.
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
	 * byte it starts in.
	 */
	public function testNoRangeCrossesAHighByteBoundary()
	{
		foreach ($this->ranges($this->cmap($this->render('<p>Hello</p>'))) as $range) {
			list($low, $high) = $range;
			$this->assertSame($low >> 8, $high >> 8, sprintf('The range <%04X> <%04X> crosses a high-byte boundary', $low, $high));
		}
	}

	/**
	 * Ranges beyond the hundred a block may hold are written as further blocks.
	 */
	public function testTheRangesAreWrittenInBlocksOfAtMostAHundred()
	{
		$cmap = $this->cmap($this->render('<p>' . $this->scatteredText() . '</p>'));

		$this->assertGreaterThan(100, count($this->ranges($cmap)));
		$this->assertGreaterThan(1, substr_count($cmap, 'beginbfrange'));
	}

	/**
	 * What the document drew still reads back as the text it was given, through the map alone.
	 */
	public function testTheTextDrawnMapsBackToItsUnicode()
	{
		$text = 'Hello, café — €100';

		$pdf = $this->render('<p>' . $text . '</p>');
		$mapping = $this->mapping($this->cmap($pdf));

		$read = '';
		foreach ($this->drawn($pdf) as $code) {
			$this->assertArrayHasKey($code, $mapping, sprintf('The map should cover the code <%04X> the document drew', $code));
			$read .= mb_convert_encoding(pack('n', $mapping[$code]), 'UTF-8', 'UTF-16BE');
		}

		$this->assertSame($text, $read);
	}

	/**
	 * The 32-127 range every subset font carries is mapped whether the document drew it or not, so
	 * that the glyphs the font holds all have a Unicode value.
	 */
	public function testTheAsciiRangeEverySubsetCarriesIsMapped()
	{
		$mapping = $this->mapping($this->cmap($this->render('<p>Hello</p>')));

		foreach (range(32, 127) as $code) {
			$this->assertSame($code, $mapping[$code]);
		}
	}

	/**
	 * Nothing else is: the map is over the codes the document drew, not over the whole space.
	 */
	public function testACodeTheDocumentDidNotDrawIsNotMapped()
	{
		$mapping = $this->mapping($this->cmap($this->render('<p>Hello</p>')));

		$this->assertArrayNotHasKey(0x20AC, $mapping);
	}
}
