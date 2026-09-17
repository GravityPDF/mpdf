<?php

namespace Mpdf;

/**
 * A GSUB feature applied one at a time is applied once, whatever else the call asks for (#212).
 *
 * Otl::_applyGSUBrulesSingly() walks its tags one at a time, but built each pass's Lookup list from
 * all of them, so a call naming two features applied both twice - and the second pass reaches the
 * glyphs the first one made, which HarfBuzz never offers a Lookup again.
 *
 * NotoSansTaiTham-LigatureContext-Synthetic is a subset of Noto Sans Tai Tham 2.002 (OFL 1.1) with
 * its script retagged lana and its Sakot ligatures moved from 'liga' to 'ccmp', which is what the
 * South East Asian shaper applies, with 'locl', before it reorders a cluster. It is
 * NotoSansTaiTham-LanaScript-Synthetic with one rule more: the font's 'ccmp' chain ends by swapping a
 * subscript High Ka that follows a High Ka for a second form of it, which in a single pass nothing
 * matches - the subscript form has no codepoint of its own and only the ligature earlier in the chain
 * makes one. The 'locl' pass made it, and the 'ccmp' pass then replaced it. `hb-shape` draws what
 * each case below expects.
 */
class FeatureAppliedOnceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+1A20 TAI THAM LETTER HIGH KA */
	const HIGH_KA = 0x1A20;

	/** U+1A21 TAI THAM LETTER HIGH KHA */
	const HIGH_KHA = 0x1A21;

	/** U+1A60 TAI THAM SIGN SAKOT, which subscripts the consonant after it */
	const SAKOT = 0x1A60;

	/** The subscript forms have no codepoint, so they are mapped into the Private Use Area */
	const SUBSCRIPT_HIGH_KHA = 0xE001;

	const SUBSCRIPT_HIGH_KA = 0xE002;

	public function dataRuns()
	{
		return [
			'a subscript High Ka the chain reads back over' => [
				[self::HIGH_KA, self::SAKOT, self::HIGH_KA],
				[self::HIGH_KA, self::SUBSCRIPT_HIGH_KA],
			],
			'two of them, the second read back over the first' => [
				[self::HIGH_KA, self::SAKOT, self::HIGH_KA, self::SAKOT, self::HIGH_KA],
				[self::HIGH_KA, self::SUBSCRIPT_HIGH_KA, self::SUBSCRIPT_HIGH_KA],
			],
			'a subscript High Ka with nothing in front of it' => [
				[self::SAKOT, self::HIGH_KA],
				[self::SUBSCRIPT_HIGH_KA],
			],
			'the subscript High Kha, which no rule reads back over' => [
				[self::HIGH_KA, self::SAKOT, self::HIGH_KHA],
				[self::HIGH_KA, self::SUBSCRIPT_HIGH_KHA],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testTheFeatureThatMadeAGlyphIsNotOfferedItAgain($codepoints, $expected)
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
			'fontdata' => ['notosanstaithamligaturecontextsynthetic' => [
				'R' => 'NotoSansTaiTham-LigatureContext-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosanstaithamligaturecontextsynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
