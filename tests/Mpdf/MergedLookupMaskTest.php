<?php

namespace Mpdf;

/**
 * The mask of a Lookup two features of one stage name is the masks of both (#243).
 *
 * Otl::lookupsForStage() settles it, and states the rule: the masks are ORed, except that a feature
 * with no mask of its own is 0 here rather than a bit every glyph carries, so 0 absorbs. Only the
 * Khmer basic forms put masked and unmasked features in one stage - shapeIndic() asks for
 * 'locl ccmp pref blwf abvf pstf cfar' in a single call, of which indicFeatureMasks() leaves only
 * locl and ccmp unmasked - and no font in tests/data/ttf offered the khmr script at all, let alone a
 * Lookup under two of those tags, which is why the line went in uncovered.
 *
 * Khmer-SharedMask is a subset of Battambang Regular 8.002 (Danh Hong, SIL OFL 1.1, fsType 0)
 * carrying a GSUB built for this test:
 *
 *   lookup 0  blwf                 uni1783 -> uni17D21783
 *   lookup 1  blwf, then abvf      uni1780 -> uni17D2_1780
 *   lookup 2  ccmp, then blwf      uni1781 -> uni17D2_1781
 *   lookup 3  pref, then blwf      uni1782 -> uni17D2_1782, uni179A -> uni17D2179A
 *
 * The forms substituted in are the donor's own below-base and pre-base glyphs; only the features
 * that name them are new. Battambang's OFL notice reserves no font name, so clause 3 leaves a
 * modified version free to be called anything; it carries a name of its own because a font called
 * Battambang that is not Battambang would mislead, and keeps the donor's copyright and licence
 * strings because clauses 1 and 2 require them to travel.
 *
 * The reordering marks a consonant after the base BLWF, ABVF and PSTF together, and the Coeng and
 * Ra of a Coeng+Ro sequence PREF, so pref's glyphs and blwf's are disjoint and the union of their
 * bits can be told from either of them alone. Lookup 3 is stated on a glyph of each and on one of
 * neither; lookup 0, which no second feature merges into, is the control.
 *
 * `hb-shape` 14.3.1 draws every row the same way:
 *
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,1780 --no-positions
 *   [uni1784=0|uni17D2=0|uni17D2_1780=2]
 *   $ hb-shape --font-file=Khmer-SharedMask-Synthetic.ttf --unicodes=1784,17D2,179A,17D2,1782 --no-positions
 *   [uni17D2=0|uni17D2179A=0|uni1784=0|uni17D2=0|uni17D2_1782=4]
 *
 * The second of those is what speaks for the union, because pref and blwf are in one stage of
 * HarfBuzz's Khmer plan too and it merges their masks the same way. Lookup 2 is no evidence either
 * way: HarfBuzz pauses to reorder between ccmp and the basic forms, so it takes that Lookup twice,
 * once unmasked, and reaches the same glyph by a route mPDF does not take.
 *
 * Lookup 1 on a lone uni1780 was a row here until abvf was given its bit (#263), the one pinning the
 * merge the other way about - a masked feature named first and an unmasked one second. That fix put
 * the case out of reach: the Khmer basic forms are the only stage carrying masks at all, and abvf
 * was the only tag in the list without one that followed a tag with one. AbvfMaskTest has the run.
 */
class MergedLookupMaskTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataRuns()
	{
		// The forms no code point names are given one in the Private Use Area, in glyph order:
		// uni17D21783, uni17D2179A, uni17D2_1780, uni17D2_1781, uni17D2_1782
		return [
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
