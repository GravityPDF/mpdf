<?php

namespace Mpdf;

/**
 * A subtable that is offered glyph after glyph is decoded from the font's bytes the first time and
 * matched against what was decoded from then on, for the life of the document. So the first run a
 * font shapes reads the subtable and every run after it reads the cache, and the two have to agree.
 *
 * Each case shapes a run twice with one shaper: once with nothing decoded, once from what the first
 * pass left. The run is long enough to reach the subtable more than once on the first pass too, so the
 * first result already mixes the two.
 */
class DecodedSubtableTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider decodedSubtables
	 *
	 * @param string      $font   A font key
	 * @param string|null $file   The fixture under tests/data/ttf it is, or null for a bundled font
	 * @param string      $text   A run that reaches the lookup type through that font
	 * @param string      $table  GSUB or GPOS
	 * @param string      $bucket Where in LuDataCache the decoded subtables are kept
	 */
	public function testARunShapesTheSameFromTheDecodedSubtableAsFromTheFont($font, $file, $text, $table, $bucket)
	{
		$config = ['mode' => 'utf-8', 'default_font' => $font];
		if ($file) {
			$config['fontDir'] = [__DIR__ . '/../data/ttf'];
			$config['fontdata'] = [$font => ['R' => $file, 'useOTL' => 0xFF]];
		}

		$mpdf = new Mpdf($config);
		$otl = $this->otl($mpdf);
		$otl->LuDataCache = [];

		$cold = $this->shape($mpdf, $otl, $text);

		$cacheKey = $font . '/' . $table;
		$this->assertNotEmpty($otl->LuDataCache[$cacheKey][$bucket], 'the run decoded no subtable of this kind');
		$decoded = $otl->LuDataCache[$cacheKey][$bucket];

		$this->assertSame($cold, $this->shape($mpdf, $otl, $text));
		$this->assertSame($decoded, $otl->LuDataCache[$cacheKey][$bucket], 'the second pass decoded again');
	}

	/**
	 * @return array[] One font and run per lookup type whose subtables are decoded once
	 */
	public function decodedSubtables()
	{
		return [
			'GSUB 4, FreeSerif' => ['freeserif', null, 'क्षत्रिय श्रृंखला द्विज र्क्ष्म्य हिन्दी', 'GSUB', 'ligatureSet'],
			// Lohit's one Format 1 context is a psts lookup covering only ZWJ
			'GSUB 5 format 1, Lohit Kannada' => ['lohitkannada', null, "ಕರ್\xE2\x80\x8Dನಾಟಕ ಕ್\xE2\x80\x8Dಷ", 'GSUB', 'plainRuleSet'],
			// U+1D148 and U+1D144 MUSICAL SYMBOL NOTEHEADs, each followed by U+1D165 COMBINING STEM
			'GSUB 5 format 2, Noto Music' => ['musicsubset', 'NotoMusic-GSUB52-Subset.ttf', "\xF0\x9D\x85\x88\xF0\x9D\x85\xA5 \xF0\x9D\x85\x84\xF0\x9D\x85\xA5", 'GSUB', 'plainClassRules'],
			// U+116AE TAKRI VOWEL SIGN I, U+1168A LETTER KA, U+116AB SIGN ANUSVARA
			'GSUB 5 format 3, Noto Sans Takri' => ['takrisubset', 'NotoSansTakri-GSUB53-Subset.ttf', "\xF0\x91\x9A\xAE\xF0\x91\x9A\x8A\xF0\x91\x9A\xAB", 'GSUB', 'plainCoverage'],
			'GSUB 6 format 1, FreeSerif' => ['freeserif', null, 'क्षत्रिय श्रृंखला द्विज र्क्ष्म्य हिन्दी', 'GSUB', 'chainedRuleSet'],
			'GSUB 6 format 2, FreeSerif' => ['freeserif', null, 'क्षत्रिय श्रृंखला द्विज र्क्ष्म्य हिन्दी', 'GSUB', 'chainedClassRules'],
			'GSUB 6 format 3, Padauk Book' => ['padaukbook', null, 'မြန်မာဘာသာစကားသည် ကျွန်ုပ် မင်္ဂလာပါ', 'GSUB', 'chainedCoverage'],
			'GPOS 2 format 1, Pothana' => ['pothana2000', null, 'తెలుగు భారతదేశంలో ఆంధ్రప్రదేశ్ స్త్రీ క్ష్మ ర్క్క', 'GPOS', 'pairSet'],
			'GPOS 2 format 2, FreeSerif' => ['freeserif', null, 'क्षत्रिय श्रृंखला द्विज र्क्ष्म्य हिन्दी', 'GPOS', 'classDef'],
			'GPOS 4, Taamey David' => ['taameydavidclm', null, 'בְּרֵאשִׁית בָּרָא אֱלֹהִים אֵת הַשָּׁמַיִם וְאֵת הָאָרֶץ', 'GPOS', 'baseAnchor'],
			'GPOS 7 format 1, Noto Sans Gurmukhi UI' => ['gurmukhitext', 'NotoSansGurmukhiUI-Subset.ttf', 'ਬੌੱਦੋਂ ਬਰਾਬਰ ਬੌੱਟੇ', 'GPOS', 'plainRuleSet'],
			// KHA with a nukta and the UU sign; KA with a nukta and the U sign
			'GPOS 7 format 2, Noto Sans Sharada' => ['sharadatext', 'NotoSansSharada-Subset.ttf', "\xF0\x91\x86\x92\xF0\x91\x87\x8A\xF0\x91\x86\xB7 \xF0\x91\x86\x91\xF0\x91\x87\x8A\xF0\x91\x86\xB6", 'GPOS', 'plainClassRules'],
			'GPOS 7 format 3, synthetic' => ['contexttext', 'NotoSans-ContextCoverage-Synthetic.ttf', 'ab bca ba', 'GPOS', 'plainCoverage'],
			// PA, virama, RA, AA, PA, virama, TA
			'GPOS 8 format 1, Noto Sans Takri' => ['takritext', 'NotoSansTakri-Subset.ttf', "\xF0\x91\x9A\x9E\xF0\x91\x9A\xB6\xF0\x91\x9A\xA4\xF0\x91\x9A\xAD\xF0\x91\x9A\x9E\xF0\x91\x9A\xB6\xF0\x91\x9A\x99", 'GPOS', 'chainedRuleSet'],
			// CA and SHA, each with the UU sign
			'GPOS 8 format 2, Noto Sans Sharada' => ['sharadatext', 'NotoSansSharada-Subset.ttf', "\xF0\x91\x86\x96\xF0\x91\x86\xB7 \xF0\x91\x86\xAD\xF0\x91\x86\xB7", 'GPOS', 'chainedClassRules'],
			'GPOS 8 format 3, Taamey David' => ['taameydavidclm', null, 'בְּרֵאשִׁית בָּרָא אֱלֹהִים אֵת הַשָּׁמַיִם וְאֵת הָאָרֶץ', 'GPOS', 'chainedCoverage'],
		];
	}

	/**
	 * @return array What the shaper returned for the run, and the glyphs, groups and positioning it
	 *               left on OTLdata
	 */
	private function shape(Mpdf $mpdf, Otl $otl, $text)
	{
		$run = str_repeat($text . ' ', 4);
		$shaped = $otl->applyOTL($run, $mpdf->CurrentFont['useOTL']);

		return [$shaped, $otl->OTLdata];
	}

	/**
	 * @return Otl The shaper the document shapes through, which holds the decoded subtables
	 */
	private function otl(Mpdf $mpdf)
	{
		$property = new \ReflectionProperty('Mpdf\Mpdf', 'otl');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		return $property->getValue($mpdf);
	}

}
