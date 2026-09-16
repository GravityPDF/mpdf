<?php

namespace Mpdf;

/**
 * An Indic run in a font offering neither its v2 tag nor its original one is laid out with the
 * font's default script, not with another Indic script's original tag.
 *
 * No font in the corpus offers another script's original tag without the run's own and has glyphs
 * for the run, so NotoSansBengali-DevaScript-Synthetic is a subset of Noto Sans Bengali 3.011 (OFL
 * 1.1) with its GSUB replaced. It offers DFLT and deva and nothing for Bengali, and each has one
 * 'locl' lookup: DFLT's gives KA the glyph of GA, deva's the glyph of KHA. `hb-shape` draws GA.
 */
class IndicScriptFallbackTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const KA = 0x0995;

	const GA = 0x0997;

	public function testABengaliRunIsLaidOutWithTheDefaultScriptRatherThanDevanagari()
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosansbengalidevascriptsynthetic' => [
				'R' => 'NotoSansBengali-DevaScript-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosansbengalidevascriptsynthetic',
		]);
		$mpdf->WriteHTML(sprintf('<p>&#x%04X;</p>', self::KA));

		$drawn = array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));

		$this->assertSame([self::GA], $drawn);
	}

}
