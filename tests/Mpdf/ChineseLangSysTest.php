<?php

namespace Mpdf;

/**
 * The lang attribute selects the font's Chinese language system the way HarfBuzz does: the script
 * subtag before the region, ZHS for zh alone, and for Macao ZHTM, then ZHH.
 *
 * No font in the corpus has a Chinese language system, so both fonts here are subsets of Noto Sans TC
 * 2.004 (OFL 1.1) with their GSUB replaced. Their hani script has no features by default, and one
 * 'locl' lookup per language system giving 骨 the glyph of 三 under ZHH, 一 under ZHS and 二 under
 * ZHT. NotoSansTC-MacaoLangSys-Synthetic also has 四 under ZHTM. `hb-shape --language` draws the
 * same glyph for every case below.
 */
class ChineseLangSysTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const BONE = 0x9AA8;

	const ZHS = 0x4E00;

	const ZHT = 0x4E8C;

	const ZHH = 0x4E09;

	const ZHTM = 0x56DB;

	/**
	 * @dataProvider languages
	 */
	public function testTheLanguageSelectsTheLanguageSystem($lang, $expected)
	{
		$this->assertSame([$expected], $this->drawn('NotoSansTC-RegionLangSys-Synthetic.ttf', $lang));
	}

	public function languages()
	{
		return [
			'zh-HK' => ['zh-HK', self::ZHH],
			'zh-TW' => ['zh-TW', self::ZHT],
			'zh-CN' => ['zh-CN', self::ZHS],
			'zh' => ['zh', self::ZHS],
			'zh-Hans' => ['zh-Hans', self::ZHS],
			'zh-Hant' => ['zh-Hant', self::ZHT],
			'zh-Hans-HK' => ['zh-Hans-HK', self::ZHS],
			'zh-Hant-CN' => ['zh-Hant-CN', self::ZHT],
			'zh-MO, where the font has no ZHTM' => ['zh-MO', self::ZHH],
		];
	}

	public function testMacaoSelectsZHTMWhereTheFontOffersIt()
	{
		$this->assertSame([self::ZHTM], $this->drawn('NotoSansTC-MacaoLangSys-Synthetic.ttf', 'zh-MO'));
	}

	private function drawn($font, $lang)
	{
		$key = strtolower(str_replace('-', '', basename($font, '.ttf')));

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$key => [
				'R' => $font,
				'useOTL' => 0xFF,
			]],
			'default_font' => $key,
		]);
		$mpdf->WriteHTML(sprintf('<p lang="%s">&#x%04X;</p>', $lang, self::BONE));

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
