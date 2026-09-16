<?php

namespace Mpdf;

/**
 * Whether a TrueType font is embedded whole or cut down to the characters the document drew is
 * `percentSubset` and `maxTTFFilesize`'s to decide, and GravityPDF/mpdf#131 is that they decided
 * nothing: `Writer\FontWriter::writeFonts()` worked out how much of the font was used and how big it
 * was, then subsetted whatever the answer.
 *
 * Poppins is 154KB and this document draws 20% of it, so every threshold below is one the font
 * passes or fails outright, and 20 itself is the boundary the comparison sits on.
 */
class FontSubsetDecisionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The share of Poppins `<p>Hello</p>` draws, as writeFonts() counts it: the 96 characters mPDF
	 * asks every font for, over the 470 the font covers.
	 */
	const USAGE = 20;

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
		$mpdf->SetCompression(false);
		$mpdf->WriteHTML('<p>Hello</p>');
		$pdf = $mpdf->Output('', 'S');

		return [$mpdf->fonts['probe'], $pdf];
	}

	/**
	 * percentSubset ships at 100, which every font is at or under, so a document that says nothing
	 * about either option is given subsets - what mPDF has done since 8.2.0.
	 */
	public function testTheShippedDefaultSubsets()
	{
		list($font, $pdf) = $this->embed([]);

		$this->assertTrue($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /MPDFAA+Poppins', $pdf);
	}

	/**
	 * A small font the document leans on harder than percentSubset asks is cheaper carried whole, and
	 * is named without the six-letter tag a subset is required to wear.
	 */
	public function testAFontUsedMoreThanPercentSubsetIsEmbeddedWhole()
	{
		list($font, $pdf) = $this->embed(['percentSubset' => self::USAGE - 1]);

		$this->assertFalse($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /Poppins-Regular', $pdf);
		$this->assertStringNotContainsString('MPDFAA+', $pdf);
	}

	/**
	 * percentSubset is the most of a font that may be drawn and still subsetted, rather than the least
	 * that must be. Reading it the other way would leave 100 - the default - embedding whole any font
	 * a document happened to use every glyph of.
	 */
	public function testAFontUsedExactlyPercentSubsetIsSubsetted()
	{
		list($font, $pdf) = $this->embed(['percentSubset' => self::USAGE]);

		$this->assertTrue($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /MPDFAA+Poppins', $pdf);
	}

	/**
	 * maxTTFFilesize overrides the usage: a font too big to carry is cut down however much of it the
	 * document drew.
	 */
	public function testAFontLargerThanMaxTtfFilesizeIsSubsettedWhateverItsUsage()
	{
		list($font, $pdf) = $this->embed(['percentSubset' => self::USAGE - 1, 'maxTTFFilesize' => 100]);

		$this->assertTrue($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /MPDFAA+Poppins', $pdf);
	}

	/**
	 * What the options are worth: the whole font carries every glyph Poppins has, the subset carries
	 * the handful this document asked for.
	 */
	public function testTheTwoOptionsChangeWhatTheDocumentWeighs()
	{
		list(, $whole) = $this->embed(['percentSubset' => self::USAGE - 1]);
		list(, $subset) = $this->embed([]);

		$this->assertGreaterThan(strlen($subset) * 2, strlen($whole));
	}
}
