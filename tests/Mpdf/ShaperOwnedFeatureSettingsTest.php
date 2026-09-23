<?php

namespace Mpdf;

/**
 * font-feature-settings reaches the features a shaper applies for itself, at the stage it applies
 * them.
 *
 * A feature valued 0 is left out of its stage, as HarfBuzz leaves it out of its plan. A feature
 * valued 1 stays in its stage but loses its mask there, as HarfBuzz makes a feature the document
 * names for the whole run global. Neither is handed to the presentation pass, so the feature's
 * Lookups are still taken once.
 *
 * Every expected row is what `hb-shape` 14.3.1 draws for the same font, run and --features, e.g.
 *
 *   $ hb-shape --font-file=KhmerOS.ttf --unicodes=1780,17D2,1781 --no-positions --no-clusters
 *   [uni1780|uni1781.sub]
 *   $ hb-shape --font-file=KhmerOS.ttf --unicodes=1780,17D2,1781 --features=-blwf --no-positions --no-clusters
 *   [uni1780|uni17d2|uni1781]
 *   $ hb-shape --font-file=KhmerOS.ttf --unicodes=1780,17D2,179A --features=-pref --no-positions --no-clusters
 *   [uni17d2|uni179a|uni1780]
 *   $ hb-shape --font-file=NotoSansBengali-AbvfStage-Synthetic.ttf --unicodes=0997,09CD,0997 --features=-abvf --no-positions --no-clusters
 *   [uni0997|uni0997.pst]
 *   $ hb-shape --font-file=NotoSansBengali-AbvfStage-Synthetic.ttf --unicodes=0997,09CD,0997 --features=+abvf --no-positions --no-clusters
 *   [uni0996|uni09CD|uni0996]
 *   $ hb-shape --font-file=NotoSansBengali-AbvfStage-Synthetic.ttf --unicodes=0997,09CD,0997 --features=-pstf --no-positions --no-clusters
 *   [uni0997|uni09CD|uni0997]
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0915,094D,0930 --features=-blwf --no-positions --no-clusters
 *   [dev_ka.half|radeva]
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0915,094D,0915 --features=-half --no-positions --no-clusters
 *   [kadeva|virama|kadeva]
 *   $ hb-shape --font-file=FreeSerif.ttf --unicodes=0915,094D --features=+half --no-positions --no-clusters
 *   [dev_ka.half]
 *   $ hb-shape --font-file=Myanmar-Mym2Script-Synthetic.ttf --unicodes=1000,1039,1000 --features=-blwf --no-positions --no-clusters
 *   [u1000|u1039|u1000]
 *   $ hb-shape --font-file=NotoSansTaiTham-LanaScript-Synthetic.ttf --unicodes=1A20,1A60,1A20 --features=-ccmp --no-positions --no-clusters
 *   [uni1A20|uni1A60|uni1A20]
 *   $ hb-shape --font-file=NotoSansArabic-Joining-Subset.ttf --unicodes=0628,0628,0628 --features=-init --no-positions --no-clusters
 *   [dotbelowar|uni066E.fina|dotbelowar|uni066E.medi|dotbelowar|uni066E]
 *
 * A glyph with no code point of its own is drawn under the Private Use Area code the font cache gave
 * it, which is what the E0xx and E8xx values below stand for.
 */
class ShaperOwnedFeatureSettingsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Font, run, the paragraph's font-feature-settings, and what hb-shape draws for them.
	 *
	 * @return array[]
	 */
	public function dataRuns()
	{
		return [
			'Khmer, below-base KHA by default' => [
				'khmeros', [0x1780, 0x17D2, 0x1781], '', [0x1780, 0xE001],
			],
			'Khmer, blwf off' => [
				'khmeros', [0x1780, 0x17D2, 0x1781], "'blwf' 0", [0x1780, 0x17D2, 0x1781],
			],
			'Khmer, blwf on, which it already was where it applies' => [
				'khmeros', [0x1780, 0x17D2, 0x1781], "'blwf' 1", [0x1780, 0xE001],
			],
			'Khmer, pref off: the Coeng Ro still moves in front of the base, unsubstituted' => [
				'khmeros', [0x1780, 0x17D2, 0x179A], "'pref' 0", [0x17D2, 0x179A, 0x1780],
			],
			'Bengali, abvf off, which leaves the consonant to pstf' => [
				'notosansbengaliabvfstagesynthetic', [0x0997, 0x09CD, 0x0997], "'abvf' 0", [0x0997, 0xE000],
			],
			'Bengali, abvf on, which reaches the base consonant too' => [
				'notosansbengaliabvfstagesynthetic', [0x0997, 0x09CD, 0x0997], "'abvf' 1", [0x0996, 0x09CD, 0x0996],
			],
			'Bengali, pstf off, which no longer moves the base off the last consonant' => [
				'notosansbengaliabvfstagesynthetic', [0x0997, 0x09CD, 0x0997], "'pstf' 0", [0x0997, 0x09CD, 0x0997],
			],
			'Devanagari, blwf off, which makes RA the base and leaves KA a half form' => [
				'freeserif', [0x0915, 0x094D, 0x0930], "'blwf' 0", [0xE8C0, 0x0930],
			],
			'Devanagari, half off' => [
				'freeserif', [0x0915, 0x094D, 0x0915], "'half' 0", [0x0915, 0x094D, 0x0915],
			],
			'Devanagari, half on, which reaches a consonant the reordering left unmarked' => [
				'freeserif', [0x0915, 0x094D], "'half' 1", [0xE8C0],
			],
			'Myanmar, blwf off' => [
				'myanmarmym2scriptsynthetic', [0x1000, 0x1039, 0x1000], "'blwf' 0", [0x1000, 0x1039, 0x1000],
			],
			'Tai Tham, ccmp off' => [
				'notosanstaithamlanascriptsynthetic', [0x1A20, 0x1A60, 0x1A20], "'ccmp' 0", [0x1A20, 0x1A60, 0x1A20],
			],
			'Arabic, init off, drawn right to left' => [
				'notosansarabicjoiningsubset', [0x0628, 0x0628, 0x0628], "'init' 0", [0xE005, 0xE002, 0xE005, 0xE003, 0xE005, 0xE001],
			],
		];
	}

	/**
	 * A document's value for a shaper-owned feature changes what is drawn as hb-shape's does.
	 *
	 * @dataProvider dataRuns
	 *
	 * @param string $family     The font-family the paragraph asks for
	 * @param int[]  $codepoints The run
	 * @param string $settings   The paragraph's font-feature-settings, or '' for none
	 * @param int[]  $expected   The code points hb-shape's glyphs are drawn under, in visual order
	 */
	public function testAShaperOwnedFeatureTakesTheDocumentsValue($family, $codepoints, $settings, $expected)
	{
		$this->assertSame($expected, $this->drawn($family, $codepoints, $settings));
	}

	/**
	 * @param string $family     The font-family the paragraph asks for
	 * @param int[]  $codepoints The run
	 * @param string $settings   The paragraph's font-feature-settings, or '' for none
	 *
	 * @return int[] The code points of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($family, $codepoints, $settings)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$fontdata = [];
		foreach ([
			'notosansbengaliabvfstagesynthetic' => 'NotoSansBengali-AbvfStage-Synthetic.ttf',
			'myanmarmym2scriptsynthetic' => 'Myanmar-Mym2Script-Synthetic.ttf',
			'notosanstaithamlanascriptsynthetic' => 'NotoSansTaiTham-LanaScript-Synthetic.ttf',
			'notosansarabicjoiningsubset' => 'NotoSansArabic-Joining-Subset.ttf',
		] as $key => $file) {
			$fontdata[$key] = ['R' => $file, 'useOTL' => 0xFF];
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => $fontdata,
		]);

		$style = 'font-family:' . $family . ';';
		if ($settings !== '') {
			$style .= 'font-feature-settings:' . $settings . ';';
		}
		$mpdf->WriteHTML('<p style="' . $style . '">' . $html . '</p>');

		return $mpdf->drawnCodepoints(0);
	}

}
