<?php

namespace Mpdf;

/**
 * The reverse Lookups a pass hoists are taken once each, under the features the forward passes read (#234).
 *
 * Otl::_applyGSUBrulesSingly() takes its type 8 Lookups over the run before anything else, because a
 * reverse Lookup cannot share the cursor the rest walk forward. It selected the features for that by
 * the substring test #212 took out of the forward passes, and it kept a set of the Lookups it had
 * taken without ever reading it, so a Lookup two of the selected features name was taken once for
 * each of them.
 *
 * NotoSansTaiTham-ReverseShared-Synthetic is a subset of Noto Sans Tai Tham 2.002 (OFL 1.1) with its
 * script retagged lana, built from NotoSansTaiTham-LigatureContext-Synthetic with a GSUB of three
 * Lookups: a single substitution of the vowel under 'locl', a reverse chaining Lookup that both
 * 'locl' and 'ccmp' name, and a second reverse chaining Lookup under the tag 'ocl ', which is a
 * substring of the 'locl ccmp' the South East Asian shaper asks for but none of its entries. The
 * shared Lookup subscripts a High Kha and then, applied to what it made, gives a second form of the
 * subscript, so a second application shows in the glyph drawn.
 */
class ReverseLookupHoistTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+1A20 TAI THAM LETTER HIGH KA */
	const HIGH_KA = 0x1A20;

	/** U+1A21 TAI THAM LETTER HIGH KHA */
	const HIGH_KHA = 0x1A21;

	/** U+1A63 TAI THAM VOWEL SIGN AA */
	const VOWEL_AA = 0x1A63;

	/** The forms no codepoint names are mapped into the Private Use Area, in glyph order */
	const SUBSCRIPT_HIGH_KA = 0xE000;

	const SUBSCRIPT_HIGH_KHA = 0xE001;

	const LOCAL_VOWEL_AA = 0xE002;

	const SUBSCRIPT_HIGH_KHA_2 = 0xE003;

	public function dataRuns()
	{
		return [
			'a reverse Lookup two selected features name' => [
				[self::HIGH_KHA],
				[self::SUBSCRIPT_HIGH_KHA],
			],
			'a reverse Lookup under a tag that is only a substring of the pass' => [
				[self::HIGH_KA],
				[self::HIGH_KA],
			],
			'both of them, either side of the vowel the forward pass substitutes' => [
				[self::HIGH_KHA, self::HIGH_KA, self::VOWEL_AA],
				[self::SUBSCRIPT_HIGH_KHA, self::HIGH_KA, self::LOCAL_VOWEL_AA],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAHoistedReverseLookupIsTakenOnceUnderTheTagsTheForwardPassesRead($codepoints, $expected)
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
			'fontdata' => ['notosanstaithamreversesharedsynthetic' => [
				'R' => 'NotoSansTaiTham-ReverseShared-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosanstaithamreversesharedsynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
