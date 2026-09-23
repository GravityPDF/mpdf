<?php

namespace Mpdf;

/**
 * A class 0 position in the backtrack or lookahead of a class-based chained context rule (GSUB 6.2,
 * GPOS 8.2) matches any glyph the sequence's ClassDef puts in no other class.
 *
 * NotoSans-ClassZeroContext-Synthetic is NotoSans-NullClassDef-Synthetic beside it with the chained
 * context lookup replaced by two Format 2 subtables covering B. Each has one rule, class 0 behind B
 * and class 0 ahead of it:
 *
 *   #0  no backtrack ClassDef, so every glyph is class 0 there; C alone in class 1 of the lookahead
 *       ClassDef. Substitutes b.sc for B.
 *   #1  C alone in class 1 of the backtrack ClassDef; no lookahead ClassDef. Substitutes a.sc for B.
 *
 * `calt` is the only feature and runs that lookup alone. `hb-shape` draws what each case expects.
 */
class ClassZeroContextPositionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'NotoSans-ClassZeroContext-Synthetic';

	/** a.sc and b.sc have no codepoint, so they are mapped into the Private Use Area */
	const A_SMALL_CAP = 0xE001;

	const B_SMALL_CAP = 0xE002;

	/**
	 * B is substituted only where the glyphs either side of it are in class 0 for one subtable.
	 *
	 * @dataProvider dataRuns
	 *
	 * @param string $text       What the document is written from
	 * @param int[]  $codepoints The codepoints of the line the drawing code is handed
	 */
	public function testAClassZeroPositionMatchesAnyGlyphNoOtherClassNames($text, array $codepoints)
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
			'a letter either side' => ['ABA', [0x41, self::B_SMALL_CAP, 0x41]],
			'a space either side' => ['A B A', [0x41, 0x20, self::B_SMALL_CAP, 0x20, 0x41]],
			'C behind, where no ClassDef names it' => ['CBA', [0x43, self::B_SMALL_CAP, 0x41]],
			'C ahead, where the first subtable names it' => ['ABC', [0x41, self::A_SMALL_CAP, 0x43]],
			'C either side, where both subtables name it' => ['CBC', [0x43, 0x42, 0x43]],
			'nothing ahead' => ['AB', [0x41, 0x42]],
			'nothing behind' => ['BA', [0x42, 0x41]],
		];
	}

}
