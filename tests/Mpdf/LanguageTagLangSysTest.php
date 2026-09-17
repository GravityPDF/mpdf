<?php

namespace Mpdf;

/**
 * A variant, script, region or extended language subtag, a Chinese language other than zh, a language
 * with more than one tag, a code used upper-cased as its own tag and one HarfBuzz blocks from the tag
 * it would take all select the font's language system the way HarfBuzz does.
 *
 * Noto-LanguageTags-Synthetic merges subsets of Noto Sans TC 2.004, Noto Sans Georgian 2.005, Noto
 * Sans Myanmar 2.107 and Noto Sans Syriac 3.000 (all OFL 1.1), with its GSUB replaced. Each script
 * has no features by default, and one 'locl' lookup per language system that gives one character
 * another's glyph: a becomes h under IPPH, i under IRT, m under MOL, p under PRO and t under ATH;
 * α becomes β under PGR, Ⴀ Ⴁ under KGE, က ခ under MONT and ܐ ܒ under SYRE; 骨 becomes 一 under
 * ZHS, 二 under ZHT and 三 under ZHH. No script offers IRI, ROM or NAV. `hb-shape --language`
 * draws the same glyph for every case below.
 */
class LanguageTagLangSysTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider languages
	 */
	public function testTheLanguageSelectsTheLanguageSystem($lang, $character, $expected)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notolanguagetagssynthetic' => [
				'R' => 'Noto-LanguageTags-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notolanguagetagssynthetic',
		]);
		$mpdf->WriteHTML(sprintf('<p lang="%s">&#x%04X;</p>', $lang, $character));

		$this->assertSame([$expected], array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8'))));
	}

	public function languages()
	{
		return [
			'el-polyton, PGR' => ['el-polyton', 0x03B1, 0x03B2],
			'ga-Latg, IRT' => ['ga-Latg', 0x61, 0x69],
			'ro-MD, MOL' => ['ro-MD', 0x61, 0x6D],
			'mnw-TH, MONT' => ['mnw-TH', 0x1000, 0x1001],
			'oc-provenc, PRO' => ['oc-provenc', 0x61, 0x70],
			'syr-Syre, SYRE' => ['syr-Syre', 0x0710, 0x0712],
			'en-fonipa, IPPH' => ['en-fonipa', 0x61, 0x68],
			'ka-Geok, KGE' => ['ka-Geok', 0x10A0, 0x10A1],
			'yue, ZHH' => ['yue', 0x9AA8, 0x4E09],
			'yue-Hant-HK, ZHH' => ['yue-Hant-HK', 0x9AA8, 0x4E09],
			'cmn-Hans, ZHS' => ['cmn-Hans', 0x9AA8, 0x4E00],
			'lzh, ZHT' => ['lzh', 0x9AA8, 0x4E8C],
			'zh-yue, ZHH' => ['zh-yue', 0x9AA8, 0x4E09],
			'zh-lzh, ZHT' => ['zh-lzh', 0x9AA8, 0x4E8C],
			'ga, IRT after IRI' => ['ga', 0x61, 0x69],
			'nv, ATH after NAV' => ['nv', 0x61, 0x74],
			'cmn-Hant-TW, ZHT' => ['cmn-Hant-TW', 0x9AA8, 0x4E8C],
			'yue-Hans, ZHS' => ['yue-Hans', 0x9AA8, 0x4E00],
			'ro, ROM not offered' => ['ro', 0x61, 0x61],
			'scs, ATH after SCS and SLA' => ['scs', 0x61, 0x74],
			'pro, its own code upper-cased' => ['pro', 0x61, 0x70],
			'kge, blocked although KGE is offered' => ['kge', 0x10A0, 0x10A0],
		];
	}

}
