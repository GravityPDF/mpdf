<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

/**
 * Every feature a language system asks for is offered to the shaper, including features that start at
 * the same lookup.
 *
 * TTFontFile::readScriptsAndFeatures() orders a language system's features by the lookup each one
 * starts at, and used to do it by keying an array on that index, so features sharing one collapsed to
 * whichever the language system listed last. A tag that never reaches the table is a tag no shaper can
 * select and CSS can neither ask for nor refuse, and any lookup only the lost feature named went with
 * it.
 *
 * These are every collision in the 133 fonts of packages/ and tests/data/ttf that mPDF will parse, and
 * each expected row is the font's own FeatureList and LangSys as fontTools reads them. Manjari's is the
 * one that costs lookups: 'akhn' names 12 and 17 and 'half' names 4, none of which the surviving 'haln'
 * names, so Malayalam could reach neither the akhand ligatures nor the half forms. In the rest the
 * recovered tag's lookups are all named by the tag that survived as well, so what the collision cost
 * there was the selection alone.
 *
 * The four Aboriginal families hold twenty more collisions between them, 'ccmp' against 'liga', but
 * carry no GDEF table, so getMetrics() refuses them under useOTL and never builds a row of theirs at
 * all.
 *
 * The fonts from outside tests/data/ttf are read where they sit in packages/: that directory is the
 * golden master corpus, and a subset copied into it would want four fixtures of its own to say what
 * these assertions say directly.
 */
class SharedFirstLookupFeatureTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider languageSystems
	 */
	public function testEveryFeatureOfALanguageSystemIsOfferedInLookupOrder($file, $table, $script, $langsys, $expected)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics(__DIR__ . '/../../' . $file, uniqid('', true), 0, false, false, 0xFF);

		$features = $table === 'GSUB' ? $ttf->GSUBFeatures : $ttf->GPOSFeatures;

		$this->assertSame($expected, $features[$script][$langsys]);
	}

	public function languageSystems()
	{
		return [
			'Manjari: akhn, half and haln all start at lookup 1' => [
				'tests/data/ttf/Manjari-Regular.ttf',
				'GSUB',
				'mlym',
				'DFLT',
				[
					'aalt' => [0],
					'akhn' => [1, 12, 17],
					'half' => [1, 4],
					'haln' => [1],
					'blwf' => [2],
					'pstf' => [5, 8],
					'pres' => [13],
					'blws' => [15, 11],
					'psts' => [16, 43],
					'salt' => [44],
					'zero' => [45],
					'tnum' => [46],
				],
			],
			'Noto Sans: salt and ss04 both start at lookup 43' => [
				'tests/data/ttf/NotoSans-Regular.ttf',
				'GSUB',
				'cyrl',
				'DFLT',
				[
					'aalt' => [0, 1],
					'ccmp' => [2, 3, 6],
					'subs' => [20],
					'sinf' => [21],
					'sups' => [22],
					'numr' => [23],
					'dnom' => [24],
					'frac' => [25, 26, 27],
					'ordn' => [30],
					'lnum' => [32],
					'pnum' => [33],
					'tnum' => [34],
					'onum' => [35],
					'c2sc' => [36],
					'smcp' => [37],
					'case' => [38],
					'liga' => [39],
					'rtlm' => [40],
					'zero' => [41],
					'ss03' => [42],
					'salt' => [43],
					'ss04' => [43],
					'ss06' => [44],
					'ss07' => [45],
				],
			],
			'FreeSerif: dist and kern both start at GPOS lookup 13' => [
				'packages/Free-Family/fonts/FreeSerif.ttf',
				'GPOS',
				'deva',
				'DFLT',
				[
					'mkmk' => [9, 10],
					'abvm' => [11],
					'blwm' => [12],
					'dist' => [13, 14],
					'kern' => [13, 14],
				],
			],
			'UnBatang: vert and vrt2 both start at lookup 16, under KOR rather than the default' => [
				'packages/UnBatang/fonts/UnBatang.ttf',
				'GSUB',
				'hang',
				'KOR ',
				[
					'ccmp' => [0],
					'ljmo' => [1],
					'vjmo' => [8],
					'tjmo' => [11],
					'vert' => [16],
					'vrt2' => [16],
				],
			],
			'ayar: clig and liga name the same fifty lookups, starting at 0' => [
				'packages/Myanmar-Bundle/fonts/ayar.ttf',
				'GSUB',
				'mymr',
				'DFLT',
				[
					'clig' => range(0, 49),
					'liga' => range(0, 49),
					'kern' => [44],
				],
			],
		];
	}

}
