<?php

namespace Mpdf;

/**
 * The mask of a Lookup two features of one stage name is the masks of both (#243).
 *
 * Otl::lookupsForStage() takes such a Lookup once, in the pass of the first feature that named it,
 * and has to decide which glyphs it may reach. HarfBuzz ORs the masks of the entries it merges;
 * here a feature with no mask of its own is 0 rather than a bit every glyph carries, so 0 absorbs
 * and two masks are the union of their bits.
 *
 * Only the Khmer basic forms put masked and unmasked features in one stage - shapeIndic() asks for
 * 'locl ccmp pref blwf abvf pstf cfar' in a single call, of which indicFeatureMasks() masks pref,
 * blwf, pstf and cfar - so a Khmer font is the only way to reach the merge, and no font in
 * tests/data/ttf had a Lookup under two of those tags.
 *
 * Khmer-SharedMask is a subset of Battambang Regular 8.002 (Danh Hong, SIL OFL 1.1, fsType 0)
 * carrying a GSUB of four single substitutions built for this test: the below-base and pre-base
 * forms are the donor's own, the features that name them are not. Battambang's OFL notice reserves
 * no font name, so clause 3 leaves the derived font free to be called anything; it carries a name
 * of its own because a font called Battambang that is not Battambang would mislead, and it keeps
 * the donor's copyright and licence strings because clauses 1 and 2 require them to travel.
 *
 *   lookup 0  blwf                 uni1783 -> uni17D21783
 *   lookup 1  blwf, then abvf      uni1780 -> uni17D2_1780
 *   lookup 2  ccmp, then blwf      uni1781 -> uni17D2_1781
 *   lookup 3  pref, then blwf      uni1782 -> uni17D2_1782, uni179A -> uni17D2179A
 *
 * The reordering marks a consonant after the base BLWF, ABVF and PSTF together, and the Coeng and
 * Ra of a Coeng+Ro sequence PREF, so pref's glyphs and blwf's are disjoint and the union of their
 * bits can be told from either of them alone. Lookup 0 is the control: a mask no other feature
 * merges into stays confined to what the reordering marked.
 *
 * `hb-shape` 14.3.1 draws all of this the same way, save the first row:
 *
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1780 --no-positions
 *   [uni1780=0]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1781 --no-positions
 *   [uni17D2_1781=0]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1782 --no-positions
 *   [uni1782=0]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1783 --no-positions
 *   [uni1783=0]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,1780 --no-positions
 *   [uni1784=0|uni17D2=0|uni17D2_1780=2]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,1782 --no-positions
 *   [uni1784=0|uni17D2=0|uni17D2_1782=2]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,1783 --no-positions
 *   [uni1784=0|uni17D2=0|uni17D21783=2]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,179A --no-positions
 *   [uni17D2=0|uni17D2179A=0|uni1784=0]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,179A,17D2,1782 --no-positions
 *   [uni17D2=0|uni17D2179A=0|uni1784=0|uni17D2=0|uni17D2_1782=4]
 *
 * The two it agrees with on the union are the ones that matter for it, because pref and blwf are in
 * one stage of HarfBuzz's Khmer plan too and it merges them the same way. The row it draws
 * differently is the lone uni1780: HarfBuzz gives abvf a mask of its own, so the merged Lookup
 * stays confined to the glyphs after the base, while mPDF leaves abvf out of indicFeatureMasks()
 * although the reordering sets Indic::ABVF on exactly those glyphs. That is a gap of its own rather
 * than anything this merge decides - give abvf its bit and mPDF draws HarfBuzz's uni1780 while
 * every other row below stands - and the row records what mPDF does today.
 */
class MergedLookupMaskTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataRuns()
	{
		// The forms no code point names are given one in the Private Use Area, in glyph order:
		// uni17D21783, uni17D2179A, uni17D2_1780, uni17D2_1781, uni17D2_1782
		return [
			'a Lookup blwf and abvf share, on a base consonant blwf does not mark' => [
				[0x1780],
				[0xE002],
			],
			'a Lookup blwf and abvf share, on a below-base consonant blwf does mark' => [
				[0x1784, 0x17D2, 0x1780],
				[0x1784, 0x17D2, 0xE002],
			],
			'a Lookup ccmp and blwf share, the unmasked feature of the two named first' => [
				[0x1781],
				[0xE003],
			],
			'a Lookup pref and blwf share, on the pre-base form only pref marks' => [
				[0x1784, 0x17D2, 0x179A],
				[0x17D2, 0xE001, 0x1784],
			],
			'a Lookup pref and blwf share, on the below-base form only blwf marks' => [
				[0x1784, 0x17D2, 0x1782],
				[0x1784, 0x17D2, 0xE004],
			],
			'both bits of that union in one syllable, from the one Lookup' => [
				[0x1784, 0x17D2, 0x179A, 0x17D2, 0x1782],
				[0x17D2, 0xE001, 0x1784, 0x17D2, 0xE004],
			],
			'the union is still a mask: a base consonant carries neither bit' => [
				[0x1782],
				[0x1782],
			],
			'the Lookup blwf names alone, on a base consonant it does not mark' => [
				[0x1783],
				[0x1783],
			],
			'the Lookup blwf names alone, on a below-base consonant it does' => [
				[0x1784, 0x17D2, 0x1783],
				[0x1784, 0x17D2, 0xE000],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAMergedLookupReachesTheGlyphsOfEveryFeatureThatNamedIt($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the code points of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints)
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
			'default_font' => 'khmersharedmasksynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
