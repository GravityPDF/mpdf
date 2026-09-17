<?php

namespace Mpdf\Shaper;

use Mpdf\Ucdn;

class OtlTagsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function testTheTagTheTextImpliesIsTakenWhereTheFontOffersIt()
	{
		$this->assertSame(['arab', false], OtlTags::script(['arab' => 'DFLT', 'DFLT' => 'DFLT'], 'arab', Ucdn::SCRIPT_ARABIC, 'A', 0xFF));
	}

	/**
	 * @dataProvider scriptGroups
	 */
	public function testAScriptIsLaidOutOnlyWhereUseOTLOpensItsGroup($scriptblock, $bit)
	{
		$offered = ['latn' => 'DFLT'];

		$this->assertSame(['latn', false], OtlTags::script($offered, 'latn', $scriptblock, '', $bit));
		$this->assertSame(['', false], OtlTags::script($offered, 'latn', $scriptblock, '', 0xFF & ~$bit));
	}

	public function scriptGroups()
	{
		return [
			'Latin' => [Ucdn::SCRIPT_LATIN, 0x01],
			'Cyrillic' => [Ucdn::SCRIPT_CYRILLIC, 0x02],
			'Greek' => [Ucdn::SCRIPT_GREEK, 0x04],
			'Hiragana, the first CJK script' => [Ucdn::SCRIPT_HIRAGANA, 0x08],
			'Han' => [Ucdn::SCRIPT_HAN, 0x08],
			'Yi, the last CJK script' => [Ucdn::SCRIPT_YI, 0x08],
			'Hangul, which is not in the CJK group' => [Ucdn::SCRIPT_HANGUL, 0x80],
			'Arabic' => [Ucdn::SCRIPT_ARABIC, 0x80],
			'Devanagari' => [Ucdn::SCRIPT_DEVANAGARI, 0x80],
		];
	}

	public function testAFontWithOnlyTheOriginalIndicTagIsLaidOutToTheOriginalSpecification()
	{
		$this->assertSame(['deva', true], OtlTags::script(['deva' => 'DFLT', 'DFLT' => 'DFLT'], 'dev2', Ucdn::SCRIPT_DEVANAGARI, 'I', 0xFF));
		$this->assertSame(['mymr', true], OtlTags::script(['mymr' => 'DFLT'], 'mym2', Ucdn::SCRIPT_MYANMAR, 'M', 0xFF));
	}

	public function testTheV2TagIsPreferredOverTheOriginal()
	{
		$this->assertSame(['dev2', false], OtlTags::script(['deva' => 'DFLT', 'dev2' => 'DFLT'], 'dev2', Ucdn::SCRIPT_DEVANAGARI, 'I', 0xFF));
	}

	public function testTheOriginalIndicTagIsNotTriedWithoutAShaper()
	{
		$this->assertSame(['DFLT', false], OtlTags::script(['deva' => 'DFLT', 'DFLT' => 'DFLT'], 'dev2', Ucdn::SCRIPT_DEVANAGARI, '', 0xFF));
	}

	/**
	 * @dataProvider originalTagFallbacks
	 */
	public function testAV2TagFallsBackToItsOwnOriginalTagAndNoOtherScriptsOriginalTag(array $offered, $scripttag, $scriptblock, array $expected)
	{
		$this->assertSame($expected, OtlTags::script($offered, $scripttag, $scriptblock, 'I', 0xFF));
	}

	public function originalTagFallbacks()
	{
		return [
			'Bengali, where the font offers deva' => [['deva' => 'DFLT', 'DFLT' => 'DFLT'], 'bng2', Ucdn::SCRIPT_BENGALI, ['DFLT', false]],
			'Devanagari, where the font offers gujr' => [['gujr' => 'DFLT', 'DFLT' => 'DFLT'], 'dev2', Ucdn::SCRIPT_DEVANAGARI, ['DFLT', false]],
			'Tamil, where the font offers only mymr' => [['mymr' => 'DFLT'], 'tml2', Ucdn::SCRIPT_TAMIL, ['', false]],
			'Devanagari, where the font offers beng' => [['beng' => 'DFLT', 'DFLT' => 'DFLT'], 'dev2', Ucdn::SCRIPT_DEVANAGARI, ['DFLT', false]],
			'Bengali, where the font offers beng and deva' => [['deva' => 'DFLT', 'beng' => 'DFLT'], 'bng2', Ucdn::SCRIPT_BENGALI, ['beng', true]],
		];
	}

	public function testATagThatIsNotAV2IndicTagHasNoOriginalToFallBackTo()
	{
		$this->assertSame(['', false], OtlTags::script(['deva' => 'DFLT'], 'arab', Ucdn::SCRIPT_ARABIC, 'A', 0xFF));
	}

	/**
	 * @dataProvider fallbacks
	 */
	public function testAScriptTheFontDoesNotOfferFallsBackToDFLTThenDfltThenLatin(array $offered, $expected)
	{
		$this->assertSame([$expected, false], OtlTags::script($offered, 'arab', Ucdn::SCRIPT_ARABIC, 'A', 0xFF));
	}

	public function fallbacks()
	{
		return [
			'DFLT before dflt' => [['latn' => 'DFLT', 'dflt' => 'DFLT', 'DFLT' => 'DFLT'], 'DFLT'],
			'dflt before latn' => [['latn' => 'DFLT', 'dflt' => 'DFLT'], 'dflt'],
			'latn last' => [['cyrl' => 'DFLT', 'latn' => 'DFLT'], 'latn'],
			'nothing' => [['cyrl' => 'DFLT'], ''],
			'no scripts at all' => [[], ''],
		];
	}

	/**
	 * @dataProvider languages
	 */
	public function testTheLanguageSystemComesFromTheLanguageSubtag($ietf, $available, $expected)
	{
		$this->assertSame($expected, OtlTags::language($ietf, $available));
	}

	public function languages()
	{
		return [
			'a language alone' => ['tr', 'DFLT AZE TRK ', 'TRK '],
			'a language with its region' => ['sr-RS', 'DFLT SRB ', 'SRB '],
			'a language with its script' => ['sr-Cyrl', 'SRB ', 'SRB '],
			'a language with its script and region' => ['sr-Cyrl-RS', 'SRB ', 'SRB '],
			'upper case' => ['TR', 'TRK ', 'TRK '],
			'a language the script does not offer' => ['tr', 'DFLT SRB ', 'DFLT'],
			'a language the script does not offer, and no default' => ['tr', 'SRB ', ''],
			'a language with no OpenType tag' => ['xx', 'DFLT SRB ', 'DFLT'],
			'a language with no OpenType tag, and no default' => ['xx', 'SRB ', ''],
			'no language' => ['', 'DFLT TRK ', 'DFLT'],
			'no language, and no default' => [null, 'TRK ', ''],
			'a script offering no language systems' => ['tr', '', ''],
		];
	}

	/**
	 * @dataProvider chinese
	 */
	public function testChineseSelectsItsLanguageSystemTheWayHarfBuzzDoes($ietf, $expected)
	{
		$this->assertSame($expected, OtlTags::language($ietf, 'DFLT ZHH  ZHS  ZHT  ZHTM '));
	}

	public function chinese()
	{
		return [
			'Hong Kong' => ['zh-HK', 'ZHH '],
			'Taiwan' => ['zh-TW', 'ZHT '],
			'Macao' => ['zh-MO', 'ZHTM'],
			'China' => ['zh-CN', 'ZHS '],
			'Singapore' => ['zh-SG', 'ZHS '],
			'lower case' => ['zh-tw', 'ZHT '],
			'a region with no language system of its own' => ['zh-US', 'ZHS '],
			'no script or region' => ['zh', 'ZHS '],
			'Simplified' => ['zh-Hans', 'ZHS '],
			'Traditional' => ['zh-Hant', 'ZHT '],
			'upper case' => ['ZH-HANT', 'ZHT '],
			'Simplified, in Hong Kong' => ['zh-Hans-HK', 'ZHS '],
			'Simplified, in Taiwan' => ['zh-Hans-TW', 'ZHS '],
			'Simplified, in Macao' => ['zh-Hans-MO', 'ZHS '],
			'Traditional, in China' => ['zh-Hant-CN', 'ZHT '],
			'Traditional, in Taiwan' => ['zh-Hant-TW', 'ZHT '],
			'Traditional, in Hong Kong' => ['zh-Hant-HK', 'ZHH '],
			'Traditional, in Macao' => ['zh-Hant-MO', 'ZHTM'],
			'another script, in Hong Kong' => ['zh-Latn-HK', 'ZHH '],
			'Min Nan, a retired tag' => ['zh-min-nan', 'ZHS '],
		];
	}

	public function testMacaoFallsBackToHongKong()
	{
		$this->assertSame('ZHH ', OtlTags::language('zh-MO', 'DFLT ZHH  ZHS  ZHT  '));
		$this->assertSame('ZHH ', OtlTags::language('zh-Hant-MO', 'DFLT ZHH  ZHS  ZHT  '));
		$this->assertSame('DFLT', OtlTags::language('zh-MO', 'DFLT ZHS  ZHT  '));
	}

	/**
	 * @dataProvider complexLanguages
	 */
	public function testSubtagsBeyondTheLanguageSelectTheLanguageSystemHarfBuzzDoes($ietf, $expected)
	{
		$offered = 'DFLT ATH  ELL  IPPH IRI  IRT  KAT  KGE  MOL  MON  MONT NAV  OCI  PGR  PRO  ROM  SYR  SYRE ZHH  ZHS  ZHT  ZHTM ';

		$this->assertSame($expected, OtlTags::language($ietf, $offered));
	}

	public function complexLanguages()
	{
		return [
			'el-polyton' => ['el-polyton', 'PGR '],
			'ga-Latg' => ['ga-Latg', 'IRT '],
			'ro-MD' => ['ro-MD', 'MOL '],
			'mnw-TH' => ['mnw-TH', 'MONT'],
			'oc-provenc' => ['oc-provenc', 'PRO '],
			'syr-Syre' => ['syr-Syre', 'SYRE'],
			'en-fonipa' => ['en-fonipa', 'IPPH'],
			'ka-Geok' => ['ka-Geok', 'KGE '],
			'yue' => ['yue', 'ZHH '],
			'yue-Hant-HK' => ['yue-Hant-HK', 'ZHH '],
			'cmn-Hans' => ['cmn-Hans', 'ZHS '],
			'lzh' => ['lzh', 'ZHT '],
			'zh-yue' => ['zh-yue', 'ZHH '],
			'zh-lzh' => ['zh-lzh', 'ZHT '],
			'a variant after a region' => ['el-GR-polyton', 'PGR '],
			'the region, after the script' => ['ro-Latn-MD', 'MOL '],
			'upper case' => ['RO-MD', 'MOL '],
			'Irish without Latg' => ['ga-IE', 'IRI '],
			'Mon outside Thailand' => ['mnw-MM', 'MON '],
			'a variant or region after a private use subtag' => ['ro-x-md', 'ROM '],
			'a variant after an extension' => ['el-u-polyton', 'ELL '],
			'a private use tag' => ['x-fonipa', 'DFLT'],
			'Cantonese, Traditional, in Taiwan' => ['yue-Hant-TW', 'ZHH '],
			'Cantonese, in Macao' => ['yue-MO', 'ZHH '],
			'Cantonese, Simplified' => ['yue-Hans', 'ZHS '],
			'Literary Chinese, in Hong Kong' => ['lzh-HK', 'ZHT '],
			'Literary Chinese, Simplified' => ['lzh-Hans', 'ZHS '],
			'Mandarin, Traditional, in Taiwan' => ['cmn-Hant-TW', 'ZHT '],
			'Mandarin, in Macao' => ['cmn-MO', 'ZHTM'],
			'Hakka, in Hong Kong' => ['hak-HK', 'ZHH '],
			'Min Nan, alone' => ['nan', 'ZHS '],
			'zh-yue, in Hong Kong, by the region' => ['zh-yue-HK', 'ZHH '],
			'zh-yue, Simplified, by the extended language' => ['zh-yue-Hans', 'ZHH '],
			'zh-cmn, Traditional, by the extended language' => ['zh-cmn-Hant', 'ZHS '],
			'Navajo' => ['nv', 'NAV '],
			'Navajo, retired' => ['i-navajo', 'NAV '],
		];
	}

	/**
	 * @dataProvider languageFallbacks
	 */
	public function testALanguageWithMoreThanOneTagTakesTheFirstTheScriptOffers($ietf, $offered, $expected)
	{
		$this->assertSame($expected, OtlTags::language($ietf, $offered));
	}

	public function languageFallbacks()
	{
		return [
			'Moldova, with MOL' => ['ro-MD', 'DFLT MOL  ROM ', 'MOL '],
			'Moldova, without MOL' => ['ro-MD', 'DFLT ROM ', 'ROM '],
			'Moldova, with neither' => ['ro-MD', 'DFLT ENG ', 'DFLT'],
			'Irish, with IRI' => ['ga', 'DFLT IRI  IRT ', 'IRI '],
			'Irish, without IRI' => ['ga', 'DFLT IRT ', 'IRT '],
			'Irish, with neither' => ['ga', 'DFLT ENG ', 'DFLT'],
			'Navajo, without NAV' => ['nv', 'DFLT ATH ', 'ATH '],
			'Irish Traditional, without IRT' => ['ga-Latg', 'DFLT IRI ', 'DFLT'],
		];
	}

	/**
	 * HarfBuzz reads these whole. Read a subtag at a time, no-nyn would be Nkole.
	 *
	 * @dataProvider retiredTags
	 */
	public function testARetiredTagIsReadWhole($ietf, $expected)
	{
		$this->assertSame($expected, OtlTags::language($ietf, 'DFLT JBO  LTZ  NKL  NOR  NYN  ZHS '));
	}

	public function retiredTags()
	{
		return [
			'no-bok' => ['no-bok', 'NOR '],
			'no-nyn' => ['no-nyn', 'NYN '],
			'zh-min' => ['zh-min', 'ZHS '],
			'zh-min-nan' => ['zh-min-nan', 'ZHS '],
			'i-hak' => ['i-hak', 'ZHS '],
			'i-lux' => ['i-lux', 'LTZ '],
			'art-lojban' => ['art-lojban', 'JBO '],
		];
	}

	/**
	 * Ucdn::$ot_languages has no key for most extended languages. HarfBuzz's table gives most of them
	 * their macrolanguage's tag, as it gives Gulf Arabic ARA, so mPDF keeps the language subtag's.
	 */
	public function testAnExtendedLanguageWithNoTagKeepsTheLanguageSubtagsTag()
	{
		$this->assertSame('ARA ', OtlTags::language('ar-afb', 'DFLT ARA '));
	}

}
