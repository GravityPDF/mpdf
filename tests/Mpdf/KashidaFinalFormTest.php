<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Utils\UtfString;

/**
 * The lowest-priority kashida rule gives priority 2 to any final form the rules above it have not
 * already placed, and it finds one by looking its glyph up in TTFontFile's `finals` string.
 *
 * `finals` was built out of whole rtlSUB entries, and a font may state a final form as a base and the
 * marks drawn on it. Every glyph of such an entry was then a substring of the string, so a mark read
 * as a final form; and the entry's first glyph was hidden from the exclusion list beside it, which is
 * tested the same way, so a letter the priority 3-6 rules already place was added as well.
 *
 * Justification keeps one kashida point per word - the highest priority, first of a tie - so a word
 * whose first priority-2 glyph is a dot had the tatweel inserted between a base and the mark on it.
 * That is GravityPDF/mpdf#126.
 *
 * The fixture states both of its final forms as two glyphs: U+0628's is a dotless base and the dot
 * below it, and U+06CC's is the glyph the font maps at U+FEAE - RA final, one of the fifteen the
 * exclusion list names - and the two dots above it.
 */
class KashidaFinalFormTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+0628 ARABIC LETTER BEH, dual-joining */
	const BEH = 0x0628;

	/** U+06CC ARABIC LETTER FARSI YEH, dual-joining */
	const FARSI_YEH = 0x06CC;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	protected function tear_down()
	{
		if ($this->mpdf) {
			$this->mpdf->cleanup();
			$this->mpdf = null;
		}

		parent::tear_down();
	}

	/**
	 * Two Behs: the first joins forward and the second takes the final form, which is a dotless base
	 * and the dot drawn under it. The point is the base. Before, the dot carried one of its own.
	 */
	public function testTheMarkOfAFinalFormIsNoKashidaPoint()
	{
		$this->assertSame(
			[[0xE003, 'C', null], [0xE001, 'C', 2], [0xE005, 'M', null]],
			$this->shape([self::BEH, self::BEH])
		);
	}

	/**
	 * Two Farsi Yehs, whose final form begins with the glyph the font maps at U+FEAE. RA final is
	 * placed by the priority 5 rule only where a medial Beh precedes it, and falls to priority 1 where
	 * none does, as here. The entry it leads put it in `finals`, so it took 2 - above every other final
	 * form in the word.
	 */
	public function testAFinalFormTheExclusionListNamesIsNotAddedThroughTheEntryItLeads()
	{
		$this->assertSame(
			[[0xE003, 'C', null], [0xE006, 'M', null], [0xFEAE, 'C', 1], [0xE006, 'M', null]],
			$this->shape([self::FARSI_YEH, self::FARSI_YEH])
		);
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return array[] the codepoint, the group and the kashida priority of each glyph of the run
	 */
	private function shape($codepoints)
	{
		// A fresh directory each time: the font cache is keyed by the family name and rewritten only
		// when the file's size changes, so a cache one run left behind is read by the next
		$tempDir = sys_get_temp_dir() . '/mpdf-kashida-final-form-' . uniqid('', true);

		$this->mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $tempDir,
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['multiplefinal' => [
				'R' => 'NotoSansArabic-MultipleFinal-Subset.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'multiplefinal',
		]);

		$otl = new Otl($this->mpdf, new FontCache(new Cache($tempDir . '/mpdf/ttfontdata')));

		$text = '';
		foreach ($codepoints as $codepoint) {
			$text .= UtfString::code2utf($codepoint);
		}
		$otl->applyOTL($text, 0xFF);

		$run = [];
		foreach ($otl->OTLdata['char_data'] as $i => $character) {
			$run[] = [
				$character['uni'],
				$otl->OTLdata['group'][$i],
				isset($otl->OTLdata['GPOSinfo'][$i]['kashida']) ? $otl->OTLdata['GPOSinfo'][$i]['kashida'] : null,
			];
		}

		return $run;
	}

}
