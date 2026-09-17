<?php

namespace Mpdf;

/**
 * Whether a run goes to the shaper its script needs is decided on the GSUB script chosen for it, as
 * HarfBuzz's hb_ot_shaper_categorize() decides: a font designed for DFLT, or one where the choice fell
 * through to latn, is laid out by its features alone, and a font with no script for the run still
 * has it shaped.
 *
 * Both Bengali fonts are subsets of Noto Sans Bengali 3.011 (OFL 1.1) with their GSUB replaced, each
 * with one 'locl' lookup giving KA another glyph. NotoSansBengali-DevaScript-Synthetic offers DFLT,
 * whose lookup gives KA the glyph of GA, and deva; NotoSansBengali-GuruScript-Synthetic offers only
 * guru, whose lookup a Bengali run never reaches. `hb-shape` 14.3.1 draws what each test expects.
 */
class ShaperChoiceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const KA = 0x0995;

	const GA = 0x0997;

	const SSA = 0x09B7;

	/** U+09BF BENGALI VOWEL SIGN I, written after the consonant and drawn before it */
	const I = 0x09BF;

	const VIRAMA = 0x09CD;

	public function testAnIndicRunUnderTheDefaultScriptIsLeftInTheOrderItWasWritten()
	{
		$this->assertSame([self::GA, self::I], $this->drawn('NotoSansBengali-DevaScript-Synthetic', [self::KA, self::I]));
	}

	public function testAnIndicRunTheFontOffersNoScriptForIsReorderedWithNothingSubstituted()
	{
		$this->assertSame([self::I, self::KA], $this->drawn('NotoSansBengali-GuruScript-Synthetic', [self::KA, self::I]));
	}

	/**
	 * The original specification puts the matra before the base consonant, SSA, and after the KA and
	 * virama in front of it. The v2 specification would put it before the whole conjunct.
	 */
	public function testAnIndicRunWithNoScriptIsReorderedToTheOriginalSpecification()
	{
		$this->assertSame(
			[self::KA, self::VIRAMA, self::I, self::SSA],
			$this->drawn('NotoSansBengali-GuruScript-Synthetic', [self::KA, self::VIRAMA, self::SSA, self::I])
		);
	}

	public function testARunInAScriptTheDocumentHasNotOpenedToOpenTypeLayoutIsNotShaped()
	{
		$this->assertSame([self::KA, self::I], $this->drawn('NotoSansBengali-GuruScript-Synthetic', [self::KA, self::I], 0x01));
	}

	/**
	 * Lanna Alif offers only latn. Its 'ccmp' ligates a Sakot with the High Ka after it, and with no
	 * South East Asian shaper the broken cluster gets no dotted circle in front of it.
	 */
	public function testATaiThamRunUnderLatnIsLaidOutByTheFontsFeaturesAlone()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:lannaalif">&#x1A60;&#x1A20;</p>');

		// The ligature has no codepoint of its own, and is mapped into the Private Use Area
		$this->assertSame([0xF001], $this->codepoints($mpdf->drawnText[0]));
	}

	private function drawn($font, array $codepoints, $useOTL = 0xFF)
	{
		$key = strtolower(str_replace('-', '', $font));

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$key => ['R' => $font . '.ttf', 'useOTL' => $useOTL]],
			'default_font' => $key,
		]);

		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return $this->codepoints($mpdf->drawnText[0]);
	}

	private function codepoints($text)
	{
		return array_values(unpack('N*', mb_convert_encoding($text, 'UTF-32BE', 'UTF-8')));
	}

}
