<?php

namespace Mpdf;

/**
 * The above-base forms reach the glyphs the reordering marked for them, not the whole run (#263).
 *
 * Indic::initial_reordering_syllable() sets Indic::ABVF on the characters after the base along with
 * the below-base and post-base bits, and the Khmer basic forms are the only stage that names abvf,
 * but indicFeatureMasks() left it out. A feature it does not name is applied under a mask of 0,
 * which applyGSUBlookupOverRun() reads as every glyph, so abvf's Lookups were taken over the run
 * rather than over the glyphs the reordering had marked.
 *
 * Khmer-SharedMask-Synthetic states a Lookup blwf and abvf share, uni1780 -> uni17D2_1780;
 * MergedLookupMaskTest describes the font and why it was built. It is the one font here whose abvf
 * covers a glyph the reordering does not mark for it - a lone base consonant - which `hb-shape`
 * 14.3.1 leaves alone where mPDF drew the below-base form:
 *
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1780 --no-positions
 *   [uni1780=0]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,1780 --no-positions
 *   [uni1784=0|uni17D2=0|uni17D2_1780=2]
 *
 * KhmerOS is the only packaged font that states abvf, and it drew HarfBuzz's glyphs before the bit
 * was read and draws them still, so the rows below are what the fix must leave alone. Its abvf is
 * the one substitution uni17be -> unif155, and U+17BE decomposes into a U+17C1 the reordering moves
 * in front of the base and a U+17BE left after it, marked, in every position it can be put in:
 *
 *   $ hb-shape --font-file=KhmerOS.ttf --no-clusters --no-positions --unicodes=17BE
 *   [uni17c1|uni25cc|unif155]
 *   $ hb-shape --font-file=KhmerOS.ttf --no-clusters --no-positions --unicodes=1780,17BE
 *   [uni17c1|uni1780|unif155]
 *   $ hb-shape --font-file=KhmerOS.ttf --no-clusters --no-positions --unicodes=1780,17D2,1781,17BE
 *   [uni17c1|uni1780|uni1781.sub|unif155]
 */
class AbvfMaskTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataSharedLookupRuns()
	{
		// uni17D2_1780, the below-base KA the shared Lookup substitutes, has no code point of its
		// own and is given one in the Private Use Area as the subset is built
		return [
			'a base consonant the reordering marks for neither feature' => [
				[0x1780],
				[0x1780],
			],
			'the same consonant after the base, where it marks all three post-base bits' => [
				[0x1784, 0x17D2, 0x1780],
				[0x1784, 0x17D2, 0xE002],
			],
		];
	}

	/**
	 * @dataProvider dataSharedLookupRuns
	 */
	public function testALookupAbvfSharesReachesOnlyWhatTheReorderingMarked($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn('khmersharedmasksynthetic', $codepoints));
	}

	public function dataKhmerOsRuns()
	{
		// uni1781.sub is the Private Use Area stand-in, KhmerOS's own below-base KHA
		return [
			'the vowel with no base, on the dotted circle its broken syllable gets' => [
				[0x17BE],
				[0x17C1, 0x25CC, 0xF155],
			],
			'after a base consonant' => [
				[0x1780, 0x17BE],
				[0x17C1, 0x1780, 0xF155],
			],
			'after a base consonant and a Coeng group' => [
				[0x1780, 0x17D2, 0x1781, 0x17BE],
				[0x17C1, 0x1780, 0xE001, 0xF155],
			],
		];
	}

	/**
	 * @dataProvider dataKhmerOsRuns
	 */
	public function testTheAboveBaseFormOfTheBundledKhmerFontIsUnmoved($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn('khmeros', $codepoints));
	}

	/**
	 * The synthetic is registered through the config and KhmerOS arrives from its package, so one
	 * Mpdf serves both and the paragraph picks between them.
	 *
	 * @param string $family     The font-family the paragraph asks for
	 * @param int[]  $codepoints
	 *
	 * @return int[] the code points of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($family, $codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['khmersharedmasksynthetic' => [
				'R' => 'Khmer-SharedMask-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
		]);
		$mpdf->WriteHTML('<p style="font-family:' . $family . '">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
