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
	 * @param string $font   A bundled font key
	 * @param string $text   A run that reaches the lookup type through that font
	 * @param string $table  GSUB or GPOS
	 * @param string $bucket Where in LuDataCache the decoded subtables are kept
	 */
	public function testARunShapesTheSameFromTheDecodedSubtableAsFromTheFont($font, $text, $table, $bucket)
	{
		$mpdf = new Mpdf(['mode' => 'utf-8', 'default_font' => $font]);
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
			'GSUB 6 format 3, Padauk Book' => ['padaukbook', 'မြန်မာဘာသာစကားသည် ကျွန်ုပ် မင်္ဂလာပါ', 'GSUB', 'chainedCoverage'],
			'GSUB 4, FreeSerif' => ['freeserif', 'क्षत्रिय श्रृंखला द्विज र्क्ष्म्य हिन्दी', 'GSUB', 'ligatureSet'],
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
