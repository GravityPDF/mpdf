<?php

namespace Mpdf;

/**
 * Each Lookup of a feature applied on its own is taken over the whole run before the next (#233).
 *
 * Otl::_applyGSUBrulesSingly() put the glyph loop outside the Lookup loop, so within one feature
 * every Lookup was offered a glyph before the cursor moved on, and the first one that applied there
 * moved it. Two things followed that HarfBuzz does not do: a later Lookup read a context to its right
 * the earlier one had not reached yet, and no Lookup was ever offered the glyph the one before it had
 * just made. hb_ot_map_t::apply() takes the stage's sorted Lookups one at a time over the whole
 * buffer, which is what the other three rule walks in Otl already do.
 *
 * NotoSansTaiTham-LookupOrder-Synthetic is a subset of Noto Sans Tai Tham 2.002 (OFL 1.1) with its
 * script retagged lana, built from NotoSansTaiTham-LigatureContext-Synthetic with a 'ccmp' of three
 * Lookups the South East Asian shaper applies together: a Sakot and a High Kha ligate as a subscript
 * High Kha, a High Ka and a subscript High Kha ligate, and a subscript High Kha on its own becomes a
 * second form of itself. Nothing in the second or third can match what the run was written with;
 * both read a glyph the first Lookup makes. `hb-shape` draws what each case below expects.
 */
class FeatureLookupOrderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+1A20 TAI THAM LETTER HIGH KA */
	const HIGH_KA = 0x1A20;

	/** U+1A21 TAI THAM LETTER HIGH KHA */
	const HIGH_KHA = 0x1A21;

	/** U+1A60 TAI THAM SIGN SAKOT, which subscripts the consonant after it */
	const SAKOT = 0x1A60;

	/** The forms no codepoint names are mapped into the Private Use Area, in glyph order */
	const HIGH_KA_SUBSCRIPT_HIGH_KHA = 0xE002;

	const SUBSCRIPT_HIGH_KHA_2 = 0xE003;

	public function dataRuns()
	{
		return [
			'a Lookup reading the subscript the Lookup before it made to its right' => [
				[self::HIGH_KA, self::SAKOT, self::HIGH_KHA],
				[self::HIGH_KA_SUBSCRIPT_HIGH_KHA],
			],
			'a Lookup offered the subscript the Lookup before it made at the cursor' => [
				[self::SAKOT, self::HIGH_KHA],
				[self::SUBSCRIPT_HIGH_KHA_2],
			],
			'both, in two syllables of one run' => [
				[self::HIGH_KA, self::SAKOT, self::HIGH_KHA, self::SAKOT, self::HIGH_KHA],
				[self::HIGH_KA_SUBSCRIPT_HIGH_KHA, self::SUBSCRIPT_HIGH_KHA_2],
			],
			'a High Kha the ligature would have to reach into the next syllable for' => [
				[self::HIGH_KA, self::HIGH_KHA],
				[self::HIGH_KA, self::HIGH_KHA],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testALookupIsTakenOverTheRunBeforeTheNextOneStarts($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosanstaithamlookupordersynthetic' => [
				'R' => 'NotoSansTaiTham-LookupOrder-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosanstaithamlookupordersynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
