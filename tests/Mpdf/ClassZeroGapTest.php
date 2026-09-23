<?php

namespace Mpdf;

/**
 * Class 0 in a class-based chained context rule (GSUB 6.2, GPOS 8.2) leaves out every glyph the
 * ClassDef puts in another class, including classes numbered above a gap.
 *
 * NotoSans-ClassZeroGap-Synthetic is NotoSans-ClassZeroContext-Synthetic beside it with ordfeminine
 * given its codepoint U+00AA and `calt` running one chained context lookup of three Format 2
 * subtables. In each, one ClassDef puts C in class 1 and ordfeminine in class 3, with nothing in
 * class 2, and the one rule names class 0 in that ClassDef's sequence:
 *
 *   #0  covers B, lookahead ClassDef. B then class 0 becomes b.sc.
 *   #1  covers C, input ClassDef. C then class 0 becomes c.sc.
 *   #2  covers A, backtrack ClassDef. Class 0 then A becomes a.sc.
 *
 * `hb-shape` draws what each case expects.
 */
class ClassZeroGapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'NotoSans-ClassZeroGap-Synthetic';

	/** a.sc, b.sc and c.sc have no codepoint, so they are mapped into the Private Use Area */
	const A_SMALL_CAP = 0xE000;

	const B_SMALL_CAP = 0xE001;

	const C_SMALL_CAP = 0xE002;

	/**
	 * A glyph in the class above the gap is not class 0 in the backtrack, input or lookahead.
	 *
	 * @dataProvider dataRuns
	 *
	 * @param string $text       What the document is written from
	 * @param int[]  $codepoints The codepoints of the line the drawing code is handed
	 */
	public function testClassZeroLeavesOutTheClassesAboveAGap($text, array $codepoints)
	{
		$mpdf = new TextRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [strtolower(self::FONT) => [
				'R' => self::FONT . '.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => strtolower(self::FONT),
		]);
		$mpdf->WriteHTML('<p>' . $text . '</p>');

		$this->assertSame($codepoints, $mpdf->drawnCodepoints(0));
	}

	/**
	 * A run, and the codepoints it should draw.
	 *
	 * @return array
	 */
	public function dataRuns()
	{
		return [
			'lookahead: class 3 ahead' => ['Bª', [0x42, 0xAA]],
			'lookahead: class 1 ahead' => ['BC', [0x42, 0x43]],
			'lookahead: class 0 ahead' => ['BB', [self::B_SMALL_CAP, 0x42]],
			'input: class 3 second' => ['Cª', [0x43, 0xAA]],
			'input: class 1 second' => ['CC', [0x43, 0x43]],
			'input: class 0 second' => ['CB', [self::C_SMALL_CAP, 0x42]],
			'backtrack: class 3 behind' => ['ªA', [0xAA, 0x41]],
			'backtrack: class 0 behind' => ['AA', [0x41, self::A_SMALL_CAP]],
		];
	}

}
