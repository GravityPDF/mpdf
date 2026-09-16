<?php

namespace Mpdf;

/**
 * Whether a TrueType font is embedded whole or cut down to the characters the document drew is
 * `percentSubset` and `maxTTFFilesize`'s to decide, and GravityPDF/mpdf#131 is that they decided
 * nothing: `Writer\FontWriter::writeFonts()` worked out how much of the font was used and how big it
 * was, then subsetted whatever the answer.
 *
 * Poppins is 154KB and this document draws 11% of it, so every threshold below is one the font
 * passes or fails outright, and 11 itself is the boundary the comparison sits on.
 */
class FontSubsetDecisionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Every letter of the alphabet in both cases: 52 distinct characters, enough of the 470 Poppins
	 * covers to put the usage somewhere a threshold can sit either side of.
	 */
	const TEXT = '<p>ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz</p>';

	/**
	 * The share of Poppins writeFonts() counts this document as drawing.
	 */
	const USAGE = 11;

	/**
	 * @param array $config What the document is given beyond the font, typically the two options
	 *
	 * @return array The font as it was written, and the document it was written into
	 */
	private function embed(array $config)
	{
		$mpdf = new Mpdf($config + [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['probe' => ['R' => 'Poppins-Regular.ttf', 'useOTL' => 0]],
			'default_font' => 'probe',
		]);
		$mpdf->WriteHTML(self::TEXT);
		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

		return [$mpdf->fonts['probe'], $pdf];
	}

	/**
	 * A subset wears a six-letter tag on its name, which is what PDF/A and PDF/X ask of one and what
	 * tells the two branches apart in the output.
	 */
	private function assertSubsetted($font, $pdf)
	{
		$this->assertTrue($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /MPDFAA+Poppins', $pdf);
	}

	private function assertEmbeddedWhole($font, $pdf)
	{
		$this->assertFalse($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /Poppins-Regular', $pdf);
		$this->assertStringNotContainsString('MPDFAA+', $pdf);
	}

	/**
	 * percentSubset ships at 100, which every font is at or under, so a document that says nothing
	 * about either option is given subsets - what mPDF has done since 8.2.0.
	 */
	public function testTheShippedDefaultSubsets()
	{
		list($font, $pdf) = $this->embed([]);

		$this->assertSubsetted($font, $pdf);
	}

	/**
	 * A small font the document leans on harder than percentSubset asks is cheaper carried whole.
	 */
	public function testAFontUsedMoreThanPercentSubsetIsEmbeddedWhole()
	{
		list($font, $pdf) = $this->embed(['percentSubset' => self::USAGE - 1]);

		$this->assertEmbeddedWhole($font, $pdf);
	}

	/**
	 * percentSubset is the most of a font that may be drawn and still subsetted, rather than the least
	 * that must be. Reading it the other way would leave 100 - the default - embedding whole any font
	 * a document happened to use every glyph of.
	 */
	public function testAFontUsedExactlyPercentSubsetIsSubsetted()
	{
		list($font, $pdf) = $this->embed(['percentSubset' => self::USAGE]);

		$this->assertSubsetted($font, $pdf);
	}

	/**
	 * maxTTFFilesize overrides the usage: a font too big to carry is cut down however much of it the
	 * document drew.
	 */
	public function testAFontLargerThanMaxTtfFilesizeIsSubsettedWhateverItsUsage()
	{
		list($font, $pdf) = $this->embed(['percentSubset' => self::USAGE - 1, 'maxTTFFilesize' => 100]);

		$this->assertSubsetted($font, $pdf);
	}

	/**
	 * The name says which branch ran; this says the branch carried the font program with it, rather
	 * than writing a whole font's name over a subset's bytes.
	 */
	public function testTheWholeFontBranchEmbedsTheWholeFont()
	{
		list(, $whole) = $this->embed(['percentSubset' => self::USAGE - 1]);
		list(, $subset) = $this->embed([]);

		$this->assertGreaterThan(strlen($subset) * 2, strlen($whole));
	}
}
