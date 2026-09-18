<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

/**
 * Every feature a language system asks for is offered to the shaper, including features that start at
 * the same lookup.
 *
 * TTFontFile::readScriptsAndFeatures() orders a language system's features by the lookup each one
 * starts at, and features sharing one collapsed to whichever the language system listed last. A tag
 * that never reaches the table is a tag no shaper can select and CSS can neither ask for nor refuse,
 * and any lookup only the lost feature named went with it.
 *
 * One case per collision in the 133 fonts of packages/ and tests/data/ttf that mPDF will parse; the
 * sites left out are the same pair of tags in a second script or a bold cut. Every lookup list here is
 * the font's own, as fontTools reads its FeatureList and LangSys, which is what these cases add over
 * tests/data/fontcache: that master carries the two corpus rows as well, but only says the parser
 * reads what it read when the fixture was written.
 *
 * Manjari's is the collision that cost lookups: 'akhn' names 12 and 17 and 'half' names 4, none of
 * which the surviving 'haln' names, so Malayalam could reach neither the akhand ligatures nor the half
 * forms. In the rest the recovered tag's lookups are all named by the tag that survived as well, so
 * what the collision cost there was the selection alone.
 *
 * The four Aboriginal families hold twenty more collisions between them, 'ccmp' against 'liga', but
 * carry no GDEF table, so getMetrics() refuses them under useOTL and never builds a row of theirs at
 * all.
 *
 * The fonts from outside tests/data/ttf are read where they sit in packages/: everything in
 * tests/data/ttf carries four golden master fixtures, and these three fonts would be asking for twelve
 * of them to state three pairs of alias tags.
 */
class SharedFirstLookupFeatureTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider collisions
	 */
	public function testEveryFeatureSharingAFirstLookupIsOffered($file, $table, $script, $langsys, $expected)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics(__DIR__ . '/../../' . $file, uniqid('', true), 0, false, false, 0xFF);

		// Braced: $ttf->$table[...] is the property named by $table[...] on PHP 5.6, and a subscript of
		// the property from 7.0 on
		$features = $ttf->{$table};

		// array_intersect_key keeps the order of the row rather than of the expectation, so this says
		// the tags are all there, carry the font's own lookup lists, and stand in the order the language
		// system listed them - which is what decides, where Otl::_applyGSUBrules() labels each lookup
		// with a tag, which of them names the lookup they share
		$this->assertSame($expected, array_intersect_key($features[$script][$langsys], $expected));
	}

	public function collisions()
	{
		return [
			'Manjari: akhn, half and haln all start at lookup 1' => [
				'tests/data/ttf/Manjari-Regular.ttf',
				'GSUBFeatures',
				'mlym',
				'DFLT',
				['akhn' => [1, 12, 17], 'half' => [1, 4], 'haln' => [1]],
			],
			'Noto Sans: salt and ss04 both start at lookup 43' => [
				'tests/data/ttf/NotoSans-Regular.ttf',
				'GSUBFeatures',
				'cyrl',
				'DFLT',
				['salt' => [43], 'ss04' => [43]],
			],
			'FreeSerif: dist and kern both start at GPOS lookup 13' => [
				'packages/Free-Family/fonts/FreeSerif.ttf',
				'GPOSFeatures',
				'deva',
				'DFLT',
				['dist' => [13, 14], 'kern' => [13, 14]],
			],
			'UnBatang: vert and vrt2 both start at lookup 16, under KOR rather than the default' => [
				'packages/UnBatang/fonts/UnBatang.ttf',
				'GSUBFeatures',
				'hang',
				'KOR ',
				['vert' => [16], 'vrt2' => [16]],
			],
			'ayar: clig and liga name the same fifty lookups, starting at 0' => [
				'packages/Myanmar-Bundle/fonts/ayar.ttf',
				'GSUBFeatures',
				'mymr',
				'DFLT',
				['clig' => range(0, 49), 'liga' => range(0, 49)],
			],
		];
	}

}
