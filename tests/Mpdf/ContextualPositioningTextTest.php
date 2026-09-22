<?php

namespace Mpdf;

/**
 * GPOS Types 7 and 8 in fonts that cover a whole script, so that a document of running text reaches
 * them glyph after glyph. The fixtures ContextualPositioningTest uses carry a handful of glyphs each,
 * enough to pin one rule and too few to write a paragraph in. Each case here is one run in which the
 * lookup moves a glyph, checked against the same run with that lookup taken out: nothing else in
 * the font accounts for the position.
 *
 * All four fonts are Noto, SIL OFL 1.1, whose notice reserves no font name. Each keeps its name and
 * licence strings, cut down with fontTools to one script plus the space, the danda, the joiners and
 * the dotted circle, with hinting dropped:
 *
 *   NotoSansGurmukhiUI-Subset   Noto Sans Gurmukhi UI 2.001, the default instance of the variable
 *                               font in google/fonts, over U+0A01-0A76: 7.1 and 8.2 in `dist`
 *   NotoSansSharada-Subset      Noto Sans Sharada 2.002, from notofonts/noto-fonts at 9e7321e, over
 *                               U+11180-111DF: 7.2 in `mkmk` and `dist`, 8.2 in `dist`. No later
 *                               release carries both.
 *   NotoSansTakri-Subset        Noto Sans Takri 2.005, from google/fonts, over U+11680-116CF: six
 *                               8.1 lookups, four of them in `kern`
 *   NotoSans-ContextCoverage-   Noto Sans 2.007 cut down to printable ASCII, with its GSUB and GDEF
 *   Synthetic                   dropped and a GPOS written for it, since none of the fonts surveyed
 *                               for GravityPDF/mpdf#80 carries a 7.3: a single positioning that
 *                               moves a consonant 20 units left, and
 *                               a `dist` lookup of two 7.3 subtables that run it at position 1 of a
 *                               vowel then a consonant, or of two consonants then a vowel
 */
class ContextualPositioningTextTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * An mPDF that records positioning, with one font registered and OTL fully on.
	 *
	 * @param string $fontkey The name the font is registered and selected under
	 * @param string $file    Its file name within tests/data/ttf
	 *
	 * @return PositionRecordingMpdf
	 */
	private function mpdf($fontkey, $file)
	{
		return new PositionRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$fontkey => [
				'R' => $file,
				'useOTL' => 0xFF,
			]],
			'default_font' => $fontkey,
		]);
	}

	/**
	 * Gurmukhi UI's plain context by glyph starts a rule set at BA: BA, the AU matra + addak
	 * ligature, DA and the OO matra + bindi ligature pull the ligature 70 units left.
	 */
	public function testAppliesAGlyphListPlainContextInRunningGurmukhi()
	{
		$mpdf = $this->mpdf('gurmukhitext', 'NotoSansGurmukhiUI-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x0A2C;&#x0A4C;&#x0A71;&#x0A26;&#x0A4B;&#x0A02;</p>');

		$this->assertEquals([[1 => ['XPlacement' => -70]]], $mpdf->drawnPositions);
	}

	/**
	 * Its chained context by class moves an addak that follows an AU matra after NA 93 units right,
	 * and the matra 20.
	 */
	public function testAppliesAClassBasedChainedContextInRunningGurmukhi()
	{
		$mpdf = $this->mpdf('gurmukhitext', 'NotoSansGurmukhiUI-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x0A28;&#x0A4C;&#x0A71;</p>');

		$this->assertEquals(
			[[
				1 => ['BaseWidth' => 648, 'XPlacement' => 473, 'YPlacement' => 0],
				2 => ['XPlacement' => 93, 'XAdvanceL' => 93, 'XAdvanceR' => 93],
			]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * Sharada's plain context by class lifts the nukta under KHA by 120 units where a UU sign
	 * follows it.
	 */
	public function testAppliesAClassBasedPlainContextInRunningSharada()
	{
		$mpdf = $this->mpdf('sharadatext', 'NotoSansSharada-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x11192;&#x111CA;&#x111B7;</p>');

		$this->assertEquals(
			[[
				1 => ['BaseWidth' => 706, 'XPlacement' => 100, 'YPlacement' => 120],
				2 => ['BaseWidth' => 706, 'XPlacement' => 632, 'YPlacement' => 0, 'XAdvanceL' => 632, 'XAdvanceR' => 632],
			]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * Its chained context by class moves CA 145 units right before a UU sign, and the sign with it.
	 */
	public function testAppliesAClassBasedChainedContextInRunningSharada()
	{
		$mpdf = $this->mpdf('sharadatext', 'NotoSansSharada-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x11196;&#x111B7;</p>');

		$this->assertEquals(
			[[
				0 => ['XPlacement' => 145, 'XAdvanceR' => 145],
				1 => ['BaseWidth' => 625, 'XPlacement' => 347, 'YPlacement' => 34, 'XAdvanceL' => 347, 'XAdvanceR' => 347],
			]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * Takri's `kern` reaches a pair adjustment through a chained context by glyph, which adds 31
	 * units after PA where the conjunct PRA follows it.
	 */
	public function testAppliesAGlyphListChainedContextInRunningTakri()
	{
		$mpdf = $this->mpdf('takritext', 'NotoSansTakri-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x1169E;&#x116B6;&#x116A4;&#x116AD;&#x1169E;&#x116B6;&#x11699;</p>');

		$this->assertEquals(
			[[
				0 => ['XAdvanceR' => 31],
				1 => ['BaseWidth' => 574, 'XPlacement' => 385, 'YPlacement' => 0],
				2 => ['XAdvanceL' => 31, 'BaseWidth' => 574, 'XPlacement' => 572, 'YPlacement' => -36],
				4 => ['BaseWidth' => 574, 'XPlacement' => 517, 'YPlacement' => 0],
			]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * The synthetic plain contexts by coverage move a consonant after a vowel and one between a
	 * consonant and a vowel, and leave a vowel after a consonant alone.
	 */
	public function testAppliesACoverageBasedPlainContextInRunningLatin()
	{
		$mpdf = $this->mpdf('contexttext', 'NotoSans-ContextCoverage-Synthetic.ttf');

		$mpdf->WriteHTML('<p>ab</p><p>bca</p><p>ba</p>');

		$this->assertEquals(
			[
				[1 => ['XPlacement' => -20, 'XAdvanceL' => -20, 'XAdvanceR' => -20]],
				[1 => ['XPlacement' => -20]],
			],
			$mpdf->drawnPositions
		);
	}

}
