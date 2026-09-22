<?php

namespace Mpdf\Ua;

/**
 * Under PDF/UA the ToUnicode CMap of an Identity-H font maps each code the font drew to itself,
 * in bfranges whose ends differ only in their last byte (ISO 32000-1 §9.10.3).
 *
 * @group pdfua
 */
class ToUnicodeCMapTest extends PdfUaTestCase
{

	/**
	 * @param string $pdf Uncompressed output
	 *
	 * @return string[][] Each bfrange as [low, high, destination] in hex
	 */
	private function ranges($pdf)
	{
		preg_match_all('/beginbfrange\n(.*?)endbfrange/s', $pdf, $blocks);
		$ranges = [];
		foreach ($blocks[1] as $block) {
			preg_match_all('/<([0-9A-F]+)> <([0-9A-F]+)> <([0-9A-F]+)>/', $block, $entries, PREG_SET_ORDER);
			foreach ($entries as $entry) {
				$ranges[] = [$entry[1], $entry[2], $entry[3]];
			}
		}

		return $ranges;
	}

	/**
	 * @param string[][] $ranges
	 * @param int        $code
	 *
	 * @return bool Whether one of the ranges maps the code to itself
	 */
	private function mapsToItself(array $ranges, $code)
	{
		foreach ($ranges as $range) {
			if (hexdec($range[0]) <= $code && $code <= hexdec($range[1]) && $range[0] === $range[2]) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every range is well formed and maps to itself.
	 */
	public function testRangesAreIdentityWithinOneHighByte()
	{
		$ranges = $this->ranges($this->getOutput($this->makeMpdf(), '<h1>Heading</h1><p>Some text, and some more.</p>'));

		$this->assertNotEmpty($ranges);
		foreach ($ranges as $range) {
			$this->assertSame(4, strlen($range[0]));
			$this->assertSame(substr($range[0], 0, 2), substr($range[1], 0, 2), 'The ends of a bfrange differ only in their last byte');
			$this->assertSame($range[0], $range[2]);
		}
	}

	/**
	 * What was drawn is mapped, with the printable ASCII every subset carries, and nothing else.
	 */
	public function testMapsTheCodesDrawn()
	{
		$ranges = $this->ranges($this->getOutput($this->makeMpdf(), '<p>Café</p>'));

		$this->assertTrue($this->mapsToItself($ranges, 0xE9));
		$this->assertTrue($this->mapsToItself($ranges, 0x5A));
		$this->assertFalse($this->mapsToItself($ranges, 0xE8));
	}

	/**
	 * A supplementary character is shown as its surrogates, so each is mapped to itself and the pair
	 * reads back as the character.
	 */
	public function testSupplementaryCharacterMapsThroughItsSurrogates()
	{
		$ranges = $this->ranges($this->getOutput($this->makeMpdf(), '<p style="font-family: dejavusans">Grinning &#128512; face</p>'));

		$this->assertTrue($this->mapsToItself($ranges, 0xD83D));
		$this->assertTrue($this->mapsToItself($ranges, 0xDE00));
	}

	/**
	 * Other documents keep the CMap they had.
	 */
	public function testPlainDocumentKeepsItsCmap()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>Text</p>');

		$this->assertStringContainsString("1 beginbfrange\n<0000> <FFFF> <0000>\nendbfrange", $mpdf->Output('', 'S'));
	}
}
