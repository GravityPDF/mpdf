<?php

namespace Mpdf;

/**
 * A Chinese region in the lang attribute selects the font's language system for it: ZHH for Hong
 * Kong, ZHT for Taiwan and Macao, ZHS for mainland China and Singapore.
 *
 * No font in the corpus has a Chinese language system, so NotoSansTC-RegionLangSys-Synthetic is a
 * subset of Noto Sans TC 2.004 (OFL 1.1) with its GSUB replaced. Its hani script has no features by
 * default, and one 'locl' lookup under each of ZHH, ZHS and ZHT, which gives 骨 the glyph of 三, 一
 * and 二 in turn. `hb-shape --language` draws the same glyph for each of these tags.
 */
class ChineseRegionLangSysTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const BONE = 0x9AA8;

	const ZHS = 0x4E00;

	const ZHT = 0x4E8C;

	const ZHH = 0x4E09;

	public function regions()
	{
		return [
			'zh-HK' => ['zh-HK', self::ZHH],
			'zh-TW' => ['zh-TW', self::ZHT],
			'zh-CN' => ['zh-CN', self::ZHS],
		];
	}

	/**
	 * @dataProvider regions
	 */
	public function testTheRegionSelectsTheLanguageSystem($lang, $expected)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosanstcregionlangsyssynthetic' => [
				'R' => 'NotoSansTC-RegionLangSys-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosanstcregionlangsyssynthetic',
		]);
		$mpdf->WriteHTML(sprintf('<p lang="%s">&#x%04X;</p>', $lang, self::BONE));

		$drawn = array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));

		$this->assertSame([$expected], $drawn);
	}

}
