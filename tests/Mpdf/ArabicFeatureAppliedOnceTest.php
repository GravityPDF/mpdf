<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

/**
 * The Arabic shaper applies a presentation feature once, whatever the document asks for on top of its
 * own list (#230).
 *
 * shapeArabic() applies 'rlig calt liga clig mset' a feature at a time over the list
 * _applyTagSettings() returns, which appends what font-feature-settings named - with the alternate it
 * named, 'liga2' - without reading what the list already carries. A document naming one of the five
 * left it in the list twice, and the second pass reaches the glyphs the first one made, which
 * HarfBuzz never offers a Lookup again.
 *
 * NotoSansArabic-AlternateCoverage-Synthetic is NotoSansArabic-Joining-Subset (Noto Sans Arabic
 * 2.012, OFL 1.1) with its GSUB replaced by a single 'liga' Alternate Substitution under arab whose
 * output is in its own coverage: beh takes dotless beh or dotless beh's initial form, and dotless beh
 * takes the medial or the final form after it. Alternate Substitution is the one lookup type whose
 * result depends on how the feature was asked for, so the one font pins both halves - the feature
 * applied once, and the alternate the document named still arriving.
 *
 * `hb-shape` 14.3.1 on the same font draws U+0628 as uni066E with 'liga' left alone, asked for, or
 * asked for as 1, and as uni066E.init for 2, which is what each case below expects.
 */
class ArabicFeatureAppliedOnceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+0628 ARABIC LETTER BEH, the only character the font's feature reads */
	const BEH = 0x0628;

	/** U+066E ARABIC LETTER DOTLESS BEH, the first alternate of beh */
	const DOTLESS_BEH = 0x066E;

	/** The forms below have no codepoint of their own, so the font maps them into the Private Use Area */
	const DOTLESS_BEH_INIT = 0xE000;

	const DOTLESS_BEH_MEDI = 0xE001;

	const DOTLESS_BEH_FINA = 0xE002;

	public function dataFeatureSettings()
	{
		return [
			'the shaper\'s own list, which names liga once' => [
				'',
				[self::DOTLESS_BEH],
			],
			'a document naming liga, which asks for no alternate' => [
				"font-feature-settings:'liga'",
				[self::DOTLESS_BEH],
			],
			'a document naming liga with the first alternate' => [
				"font-feature-settings:'liga' 1",
				[self::DOTLESS_BEH],
			],
			'a document naming liga with the second alternate' => [
				"font-feature-settings:'liga' 2",
				[self::DOTLESS_BEH_INIT],
			],
		];
	}

	/**
	 * @dataProvider dataFeatureSettings
	 */
	public function testTheFeatureThatMadeAGlyphIsNotOfferedItAgain($style, $expected)
	{
		$this->assertSame($expected, $this->drawn($style));
	}

	/**
	 * The duplicate is resolved where the features are applied rather than where the list is built, so
	 * the list still carries what the document asked for, alternate and all. A fix that took the
	 * appended entry out instead would drop the alternate with it.
	 */
	public function testTheTagListStillCarriesTheAlternateTheDocumentNamed()
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->OTLtags = ['FFPlus' => ' liga2'];
		$otl = new Otl($mpdf, new FontCache(new Cache(sys_get_temp_dir() . '/mpdf-arabic-feature-once')));

		$usetags = $otl->_applyTagSettings('rlig calt liga clig mset', ['liga' => [0]], '', false);

		$mpdf->cleanup();

		$this->assertSame('rlig calt liga clig mset liga2', $usetags);
	}

	/**
	 * @param string $style The font-feature-settings the document asks for, if any
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code
	 */
	private function drawn($style)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosansarabicalternatecoveragesynthetic' => [
				'R' => 'NotoSansArabic-AlternateCoverage-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansarabicalternatecoveragesynthetic',
		]);
		$mpdf->WriteHTML(sprintf('<p style="%s">&#x%04X;</p>', $style, self::BEH));

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
